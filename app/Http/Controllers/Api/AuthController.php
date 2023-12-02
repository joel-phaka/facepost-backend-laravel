<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AuthUtils;
use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;
use Laravel\Socialite\Facades\Socialite;
use Stevebauman\Location\Facades\Location;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only(['email', 'password']))) {
            return response()->json(['message' => 'Incorrect email or password'], 401);
        }
        $user = Auth::user();
        $tokenResult = $user->createToken('auth-token');
        $this->createLoginLog($tokenResult->accessToken);

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $tokenResult->token->expires_at,
            'user' => $user
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'username' => AuthUtils::generateUsername($request->input('email')),
            'email' => $request->input('email'),
            'password' => bcrypt($request->input('password')),
        ]);

        return response()->json($user);
    }

    public function user()
    {
        return Auth::user();
    }

    public function logout()
    {
        Auth::user()->token()->revoke();

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
            $first_name = $nameArr[0];
            $last_name = count($nameArr) > 1 ? implode(' ', array_slice($nameArr, 1)) : null;

            $createdUser = User::firstOrCreate(['email' => $externalUser->getEmail()], [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => AuthUtils::generateUsername($externalUser->getEmail()),
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
            dd($exception);
            return view('auth.callback', ['platform' => $platform, 'spa_app_url' => $spa_app_url])
                ->withErrors(['auth' => 'Failed to authenticated.']);
        }
    }

    private function createLoginLog($accessToken, array $additionalMeta = array())
    {
        if (($user = AuthUtils::findByAccessToken($accessToken))) {
            $loginLog = new LoginLog();
            $loginLog->user_id = $user->id;
            $loginLog->access_token = $accessToken;
            $loginLog->ip = request()->ip();
            $loginLog->user_agent = request()->header('user-agent');
            $loginLog->date = Carbon::now();

            $agent = new Agent();
            if ($agent->isiOS() || $agent->isiPhone()) $loginLog->device = 'ios';
            else if ($agent->isiPadOS() || $agent->isiPad()) $loginLog->device = 'ipados';
            else if ($agent->isAndroidOS()) $loginLog->device = 'android';
            else if ($agent->iswebOS()) $loginLog->device = 'webos';
            else if (stripos($loginLog->user_agent, 'kaios') !== false) $loginLog->device = 'kaios';
            else if ($agent->isDesktop()) $loginLog->device = 'web';

            $loginLog->save();

            //$location = Location::get($loginLog->ip);
            $location = Location::get('102.220.209.244');

            if (!!$location && !$location->isEmpty() && !!$location->countryCode) {
                $loginLog->location = $location->countryName . (!!$location->regionName ? ", {$location->regionName}" : '') . (!!$location->cityName ? ", {$location->cityName}" : '');

                $loginLog->setMeta('country_code', $location->countryCode);

                if (!!$location->regionCode) $loginLog->setMeta('region_code', $location->regionCode);
                if (!!$location->areaCode) $loginLog->setMeta('are_code', $location->areaCode);
                if (!!$location->zipCode) $loginLog->setMeta('zip_code', $location->zipCode);
                if (!!$location->timezone) $loginLog->setMeta('timezone', $location->timezone);
            }

            $additionalMeta = array_filter(
                is_array($additionalMeta) ? $additionalMeta : [],
                function ($v, $k) {
                    return !!trim($k) && (!!trim($v) || is_numeric($v) && intval($v));
                },
                ARRAY_FILTER_USE_BOTH
            );

            $loginLog->setManyMeta($additionalMeta);

        }
    }
}
