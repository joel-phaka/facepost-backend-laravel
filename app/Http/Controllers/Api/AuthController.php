<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AccessTokenException;
use App\Helpers\AuthUtils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            'password' => config('services.passport.oauth_token_url'),
            'refresh_token' => config('services.passport.oauth_token_refresh_url')
        ];

        $url = $grantUrls[$credentials['grant_type']];

        $credentials = array_merge($credentials, [
            'client_id' => config('services.passport.client_id'),
            'client_secret' => config('services.passport.client_secret'),
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
    public function login(LoginRequest $request)
    {
        try {
            $userFromEmail = DB::table('users')
                ->select(['is_active'])
                ->where('email', $request->input('email'))
                ->first();

            if (!!$userFromEmail && !$userFromEmail->is_active) {
                return response()->json([
                    'message' => 'The user account has been deactivated.',
                    'error_code' => 'auth_account_deactivated',
                ], 401);
            }

            $credentials = array_merge(
                ['grant_type' => 'password'],
                $request->only('email', 'password')
            );

            $tokens = $this->getTokens($credentials);
            $user = AuthUtils::findUserByAccessToken($tokens['access_token']);

            abort_if(!$user, 401);

            return response()->json(array_merge($tokens, compact('user')));
        } catch (AccessTokenException $ex) {
            return response()->json($ex, 401);
        } catch (Throwable) {
            return response()->json([
                'message' => "Internal Server Error",
                'error_code' => "auth_internal_error",
            ], 500);
        }
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
        } catch (Throwable) {
            return response()->json([
                'message' => "Internal Server Error",
                'error_code' => "auth_internal_error",
            ], 500);
        }
    }

    public function register(RegisterRequest $request)
    {
        $username = AuthUtils::generateUsername($request->input('email'));

        if (!$username){
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

    public function redirectToProvider($provider)
    {
        session([
            'platform' => request()->input('platform'),
            'spa_app_url' => request()->input('spa_app_url')
        ]);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleProviderCallback($provider)
    {
        $platform = session()->get('platform');
        $spa_app_url = session()->get('spa_app_url');
        session()->forget(['platform', 'spa_app_url']);

        try {
            $externalUser = Socialite::driver($provider)->stateless()->user();

            $nameArr = preg_split('/\s+/', $externalUser->getName());
            $firstName = $nameArr[0];
            $lastName = count($nameArr) > 1 ? implode(' ', array_slice($nameArr, 1)) : null;
            $email = $externalUser->getEmail();
            $username = AuthUtils::generateUsername($email);

            if (!$username){
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
                ],
                ['avatar' => $externalUser->getAvatar()]
            );

            $tokenResult = $createdUser->createToken('auth-token');
            $this->createLoginLog($tokenResult->accessToken, ['external_auth' => true, 'external_auth_provider' => $provider]);

            return view('auth.callback', ['platform' => $platform, 'spa_app_url' => $spa_app_url, 'access_token' => $tokenResult->accessToken]);
        } catch (\Exception $exception) {
            return view('auth.callback', ['platform' => $platform, 'spa_app_url' => $spa_app_url])
                ->withErrors(['auth' => 'Failed to authenticated.']);
        }
    }

    private function createLoginLog($accessToken,  array $additionalData = array())
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
