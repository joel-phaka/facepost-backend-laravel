<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AccessTokenException;
use App\Helpers\AuthUtils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Image;
use App\Models\LoginLog;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jenssegers\Agent\Agent;
use Laravel\Socialite\Facades\Socialite;
use PeterPetrus\Auth\PassportToken;
use Stevebauman\Location\Facades\Location;
use Throwable;

class AuthController extends Controller
{
    private function getTokens(array $credentials)
    {
        if (empty($credentials) || !in_array($credentials['grant_type'] ?? null, ['password', 'refresh_token'])) {
            throw new InvalidArgumentException('grant_type is required and must be one of "password", "refresh_token".');
        }

        if ($credentials['grant_type'] == 'password') {
            $credentials['username'] = $credentials['username'] ?? $credentials['email'] ?? null;
            data_forget($credentials, 'email');

            if (empty($credentials['username']) || empty($credentials['password'])) {
                $usernameField = !empty($credentials['username']) ? 'username' : 'email';

                throw new InvalidArgumentException("{$usernameField} and password are required.");
            }
        } else if ($credentials['grant_type'] == 'refresh_token') {
            if (empty($credentials['refresh_token'])) {
                throw new InvalidArgumentException('refresh_token is required.');
            }
        }

        $grantUrls = [
            'password' => config('passport.oauth_token_url'),
            'refresh_token' => config('passport.oauth_token_refresh_url')
        ];

        $url = $grantUrls[$credentials['grant_type']];

        $credentials = array_merge($credentials, [
            'client_id' => config('passport.password_client.id'),
            'client_secret' => config('passport.password_client.secret'),
        ]);

        $options = ['verify' => !config('app.debug')];

        if (config('app.debug')) {
            $options['timeout'] = 240;
        }

        $response = Http::withOptions($options)
            ->acceptJson()
            ->post($url, $credentials);

        if ($response->failed()) {
            if ($response->status() == 400) {
                throw new AccessTokenException($response->json());
            } else {
                throw $response->toException();
            }
        }

        $tokenData = $response->json();

        $accessTokenDetails = new PassportToken($tokenData['access_token']);
        $tokenData['expires_at'] = Carbon::parse($accessTokenDetails->expires_at)->timestamp;

        return [
            'token_type' => $tokenData['token_type'],
            'expires_in' => $tokenData['expires_in'],
            'expires_at' => $tokenData['expires_at'],
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
        ];
    }

    public function loginWithEmailAndPassword(array $credentials)
    {
        try {
            $userFromEmail = DB::table('users')
                ->select(['is_active'])
                ->where('email', $credentials['email'])
                ->first();

            if (!!$userFromEmail && !$userFromEmail->is_active) {
                return response()->json([
                    'message' => 'The user account has been deactivated.',
                    'error_code' => 'auth_account_deactivated',
                ], 401);
            }

            $credentials = [
                'grant_type' => 'password',
                ...Arr::only($credentials, ['email', 'password'])
            ];

            $tokens = $this->getTokens($credentials);
            $user = AuthUtils::findUserByAccessToken($tokens['access_token']);

            abort_if(!$user, 401);

            auth()->login($user);

            return response()->json(array_merge($tokens, compact('user')));
        } catch (AccessTokenException $ex) {
            return response()->json($ex, 401);
        } catch (Throwable $th) {
            return response()->json([
                'message' => "Internal Server Error",
                'error_code' => "auth_internal_error",
            ], 500);
        }
    }

    public function login(LoginRequest $request)
    {
        return $this->loginWithEmailAndPassword($request->only('email', 'password'));
    }

    public function loginWithAccessToken()
    {
        return AuthUtils::issueAccessTokenData(request()->bearerToken() ?? '');
    }

    public function refresh(RefreshTokenRequest $request)
    {
        try {
            $credentials = array_merge(
                ['grant_type' => 'refresh_token'],
                $request->only('refresh_token')
            );

            $tokens = $this->getTokens($credentials);
            $user = AuthUtils::findUserByAccessToken($tokens['access_token']);

            if (!$user) {
                throw new AccessTokenException(null, 'auth_invalid_user');
            }

            return response()->json(array_merge($tokens, compact('user')));
        } catch (AccessTokenException $ex) {
            return response()->json($ex, 401);
        } catch (Throwable $ex) {
            return response()->json([
                'message' => $ex->getMessage(),//"Internal Server Error",
                'error_code' => "auth_internal_error",
            ], 500);
        }
    }

    public function register(RegisterRequest $request)
    {
        $username = AuthUtils::generateUsernameFromEmail($request->input('email'));

        if (!$username) {
            return response()->json([
                'message' => 'Registration failed'
            ]);
        }

        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'username' => $username,
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        return response()->json($user);
    }

    public function getUser()
    {
        return auth()->user();
    }

    public function logout()
    {
        $token = auth()->user()->token();

        if (!!$token) $token->revoke();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function redirectToProvider(Request $request, $provider)
    {
        session(['return_to' => filter_var($request->query('return_to'), FILTER_SANITIZE_URL)]);

        return Socialite::driver($provider)
            ->stateless()
            ->redirect();
    }

    public function handleProviderCallback(Request $request, $provider)
    {
        # parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $query);
        # $request->mergeIfMissing($query);

        $returnTo = filter_var(session('return_to'), FILTER_SANITIZE_URL);
        session()->forget(['return_to']);

        try {
            $externalUser = Socialite::driver($provider)
                ->stateless()
                ->user();

            $nameArr = preg_split('/\s+/', $externalUser->getName());
            $firstName = $nameArr[0];
            $lastName = count($nameArr) > 1 ? implode(' ', array_slice($nameArr, 1)) : null;
            $email = $externalUser->getEmail();
            $username = AuthUtils::generateUsernameFromEmail($email);

            if (!$username) {
                response()->json([
                    'message' => 'Registration failed'
                ]);
            }

            $createdUser = User::firstOrCreate(['email' => $email], [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $username,
            ]);

            $createdUser->providers()->updateOrCreate([
                'provider' => $provider,
                'provider_id' => $externalUser->getId(),
            ]);

            $tokenResult = $createdUser->createToken('Personal Access Token');

            $this->createLoginLog($tokenResult->accessToken, [
                'external_auth' => true,
                'external_auth_provider' => $provider
            ]);

            if (!!$externalUser->getAvatar() &&
                ($avatarContent = @file_get_contents($externalUser->getAvatar())) &&
                ($avatarInfo = @getimagesizefromstring($avatarContent)) &&
                ($avatarInfo[0] > 0 && $avatarInfo[1] > 0) &&
                in_array($avatarInfo['mime'], array_values(config('const.images.mimetypes')))
            ) {
                $imageExtension = array_flip(config('const.images.mimetypes'))[$avatarInfo['mime']];
                $baseImageName = date('Ymd') . '-' . $createdUser->id . '-' . Str::random(32);
                $imageName = $baseImageName . '.' . $imageExtension;

                $image = Image::create([
                    'name' => $imageName,
                    'type' => $avatarInfo['mime'],
                    'caption' => $createdUser->first_name . ' ' . $createdUser->last_name,
                    'width' => $avatarInfo[0],
                    'height' => $avatarInfo[1],
                    'user_id' => $createdUser->id,
                ]);

                if (!!$image && Storage::disk('images')->put($imageName, $avatarContent)) {
                    $createdUser->setMeta('profile_picture', $image->id);
                } else {
                    Storage::disk('images')->delete($imageName);
                    $image?->delete();
                }
            }

            $returnTo = http_build_url(
                url: $returnTo,
                parts: [
                    'query' => http_build_query([
                        'oauth' => 'true',
                        'token' => $tokenResult->accessToken,
                    ])
                ],
                flags: HTTP_URL_JOIN_QUERY
            );
        } catch (Exception $ex) {

        } finally {
            return view('auth.callback', ['return_to' => $returnTo]);
        }
    }

    private function createLoginLog($accessToken, array $additionalData = array())
    {
        if (($user = AuthUtils::findUserByAccessToken($accessToken))) {
            $loginLog = new LoginLog();
            $loginLog->user_id = $user->id;
            $loginLog->access_token = $accessToken;
            $loginLog->ip = request()->ip();
            $loginLog->user_agent = request()->header('user-agent');
            $loginLog->date = Carbon::now();
            $loginLog->external_auth = !!data_get($additionalData, 'external_auth');

            if (data_get($additionalData, 'external_auth_provider')) {
                $loginLog->external_auth_provider = data_get($additionalData, 'external_auth_provider');
            }

            $agent = new Agent();
            if ($agent->isiOS() || $agent->isiPhone()) $loginLog->device_platform = 'ios';
            else if ($agent->isiPadOS() || $agent->isiPad()) $loginLog->device_platform = 'ipados';
            else if ($agent->isAndroidOS()) $loginLog->device_platform = 'android';
            else if ($agent->iswebOS()) $loginLog->device_platform = 'webos';
            else if (stripos($loginLog->user_agent, 'kaios') !== false) $loginLog->device_platform = 'kaios';
            else if ($agent->isDesktop()) $loginLog->device_platform = 'web';

            $loginLog->save();

            $location = Location::get($loginLog->ip);

            if (!!$location && !$location->isEmpty() && !!$location->countryCode) {
                $loginLog->location = $location->countryName . (!!$location->regionName ? ", {$location->regionName}" : '') . (!!$location->cityName ? ", {$location->cityName}" : '');
                $loginLog->country_code = $location->countryCode;

                if (!!$location->regionCode) $loginLog->region_code = $location->regionCode;
                if (!!$location->areaCode) $loginLog->are_code = $location->areaCode;
                if (!!$location->zipCode) $loginLog->zip_code = $location->zipCode;
                if (!!$location->timezone) $loginLog->timezone = $location->timezone;

                $loginLog->save();
            }
        }
    }
}
