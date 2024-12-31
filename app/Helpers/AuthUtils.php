<?php

namespace App\Helpers;

use App\Exceptions\AccessTokenException;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Bridge\AccessToken;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Bridge\UserRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\PasswordGrant;
use PeterPetrus\Auth\PassportToken;

class AuthUtils
{
    /**
     * Generate a unique username from an email address.
     *
     * This method takes an email address, extracts the username part (before the "@" symbol),
     * and ensures it is unique by checking against existing usernames in the database.
     * If the username exists, it appends or increments a numerical suffix to generate a unique username.
     *
     * @param string|null $email The email address to generate a username from.
     * @return bool|string Returns the generated username as a string, or false if the email is invalid.
     */
    public static function generateUsernameFromEmail(?string $email): bool|string
    {
        // Check if the provided email is valid
        if (!filter_var($email)) return false;

        // Extract the username part of the email
        $usernameOfEmail = explode("@", $email)[0];

        // Check if the username already exists in the database
        if (!User::where("username", $usernameOfEmail)->exists()) {
            return $usernameOfEmail; // Return it directly if unique
        }

        // Extract the base part of the username (without numerical suffix)
        $namePart = preg_match("/(.+[^0-9]+)[0-9]+$/", $usernameOfEmail, $matches)
            ? ($matches[1] ?? $usernameOfEmail)
            : $usernameOfEmail;

        // Fetch existing usernames matching the base part followed by numbers
        $numericalSuffixes = User::where("username", 'REGEXP', $namePart . '[0-9]*')
            ->whereNot('username', $namePart) // Exclude the exact base part
            ->orderBy('username', 'desc') // Sort by descending order for suffix comparison
            ->pluck('username') // Get the usernames
            ->map(fn($name) => str_replace($namePart, '', $name)) // Remove the base part
            ->toArray();

        // If no suffix exists, start with 1
        $suffix = $numericalSuffixes[0] ?? '';
        if (!$suffix) return $namePart . '1';

        // Generate a unique suffix
        do {
            // Break down the suffix into leading zeros and numeric parts
            preg_match('/^(0*)([1-9]+\d*)?$/', $suffix, $matches);
            $leadingZeros = $matches[1] ?? '';
            $trailingNumbers = $matches[2] ?? '';

            if ($leadingZeros !== '' && $trailingNumbers !== '') {
                // Case: Both leading zeros and numeric part exist
                $trailingNumbers = strval(intval($trailingNumbers) + 1); // Increment the numeric part

                // Adjust leading zeros if the new numeric part exceeds the suffix length
                if (strlen($trailingNumbers) >= strlen($suffix)) {
                    $leadingZeros = '';
                } else {
                    $lengthDiff = strlen($suffix) - strlen($trailingNumbers);
                    $leadingZeros = substr_replace($leadingZeros, '', $lengthDiff);
                }
            } else if ($leadingZeros !== '' && $trailingNumbers === '') {
                // Case: Only leading zeros exist
                if (strlen($leadingZeros) === 1) {
                    $leadingZeros = strval(1);
                } else {
                    $leadingZeros = substr($leadingZeros, 0, strlen($leadingZeros) - 1) . '1';
                }
            } else if ($leadingZeros === '' && $trailingNumbers !== '') {
                // Case: Only numeric part exists
                $trailingNumbers = strval(intval($trailingNumbers) + 1);
            }

            // Reconstruct the suffix
            $suffix = $leadingZeros . $trailingNumbers;

        } while ($suffix !== '' && in_array($suffix, $numericalSuffixes, true)); // Ensure uniqueness

        // Append the suffix to the base part and return the generated username
        $generatedUsername = $namePart . $suffix;

        return $generatedUsername;
    }

    /**
     * Find a user associated with a given access token.
     *
     * This method takes an access token, parses it using the `PassportToken` class,
     * and attempts to find the corresponding user based on the token's ID.
     *
     * @param string|null $accessToken The access token to look up.
     * @return ?User Returns the associated `User` object if the token is valid and linked to a user, or null otherwise.
     */
    public static function findUserByAccessToken(?string $accessToken): ?User
    {
        // Parse the access token to extract details using the PassportToken class
        $tokenDetails = new PassportToken($accessToken);

        // Check if the token is valid and has a token ID
        if ($tokenDetails->valid && $tokenDetails->token_id) {
            // Attempt to find the token in the database
            $token = Token::find($tokenDetails->token_id);

            // If the token exists, return the associated user; otherwise, return null
            return $token ? $token->user : null;
        }

        // Return null if the token is invalid or doesn't have a token ID
        return null;
    }

    public static function issueAccessTokenData(string $accessToken): array
    {
        $customGrant = new class extends PasswordGrant
        {
            public function __construct()
            {
                parent::__construct(app(UserRepository::class), app(RefreshTokenRepository::class));

                $authorizationServer = app(AuthorizationServer::class);

                $this->setRefreshTokenTTL(Passport::refreshTokensExpireIn());
                $this->setAccessTokenRepository(app(AccessTokenRepository::class));
                $this->setClientRepository(app(ClientRepository::class));
                $this->setScopeRepository(app(ScopeRepository::class));
                $this->setPrivateKey($this->makeCryptKey());
                $this->setEncryptionKey(app('encrypter')->getKey());
                $this->setEmitter($authorizationServer->getEmitter());
            }

            public function issueAccessTokenData(string $accessToken): array
            {
                $passportToken = new PassportToken($accessToken);

                if (!$passportToken->existsValid()) {
                    throw new AccessTokenException();
                }

                $token = Token::find($passportToken->token_id);

                if (!$token || !$token->user || !$token->user->is_active) {
                    throw new AccessTokenException();
                }

                $passwordClient = DB::table('oauth_clients')
                    ->where('password_client', 1)
                    ->first();

                if (!$passwordClient) {
                    throw new AccessTokenException();
                }

                $client = $this->clientRepository->getClientEntity($passwordClient->id);

                /*
                client = $this->clientRepository->getClientEntity($token->client_id);
                $accessTokenEntity = new AccessToken($token->user->id, [], $client);
                $accessTokenEntity->setIdentifier($token->id);
                $accessTokenEntity->setExpiryDateTime((new DateTimeImmutable())->add(Passport::tokensExpireIn()));
                $accessTokenEntity->setPrivateKey($this->privateKey);
                */

                $accessTokenEntity = $this->issueAccessToken(Passport::tokensExpireIn(), $client, $token->user->id);

                $refreshTokenEntity = $this->issueRefreshToken($accessTokenEntity);
                $refreshTokenPayload = json_encode([
                    'client_id'        => $accessTokenEntity->getClient()->getIdentifier(),
                    'refresh_token_id' => $refreshTokenEntity->getIdentifier(),
                    'access_token_id'  => $accessTokenEntity->getIdentifier(),
                    'scopes'           => $accessTokenEntity->getScopes(),
                    'user_id'          => $accessTokenEntity->getUserIdentifier(),
                    'expire_time'      => $refreshTokenEntity->getExpiryDateTime()->getTimestamp(),
                ]);

                $accessTokenExpiryTimestamp = $accessTokenEntity->getExpiryDateTime()->getTimestamp();
                $refreshToken = $this->encrypt($refreshTokenPayload);

                // Delete existing refresh tokens
                DB::table('oauth_refresh_tokens')
                    ->where('access_token_id', $token->id)
                    ->delete();

                // Delete the token
                $token->delete();

                return [
                    'token_type'    => 'Bearer',
                    'expires_in'    => $accessTokenExpiryTimestamp - time(),
                    'expires_at'    => $accessTokenExpiryTimestamp,
                    'access_token'  => (string) $accessTokenEntity,
                    'refresh_token' => $refreshToken,
                    'user'          => $token->user
                ];
            }

            private function makeCryptKey(): CryptKey
            {
                $key = str_replace('\\n', "\n", config('passport.'. 'private' .'_key') ?? '') ?: 'file://'.Passport::keyPath('oauth-'. 'private' .'.key');

                return new CryptKey($key, null, Passport::$validateKeyPermissions && !windows_os());
            }
        };

        return $customGrant->issueAccessTokenData($accessToken);
    }
}
