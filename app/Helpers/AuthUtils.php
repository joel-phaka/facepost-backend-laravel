<?php

namespace App\Helpers;

use App\Models\User;
use Laravel\Passport\Token;
use PeterPetrus\Auth\PassportToken;

class AuthUtils
{
    public static function generateUsername(?string $email): bool|string
    {
        if  (!filter_var($email)) return false;

        $usernameOfEmail = explode("@", $email)[0];

        if (!User::where("username", $usernameOfEmail)->exists()) {
            return $usernameOfEmail;
        }

        $namePart = preg_match("/(.+[^0-9]+)[0-9]+$/", $usernameOfEmail, $matches)
            ? ($matches[1] ?? $usernameOfEmail)
            : $usernameOfEmail;

        $numericalSuffixes = User::where("username", 'REGEXP', $namePart . '[0-9]*')
            ->whereNot('username', $namePart)
            ->orderBy('username','desc')
            ->pluck('username')
            ->map(fn($name) => str_replace( $namePart, '',  $name))
            ->toArray();

        $suffix = $numericalSuffixes[0] ?? '';

        if (!$suffix) return $namePart . '1';

        do {
            preg_match('/^([0]*)([1-9]+\d*)?$/', $suffix, $matches);

            $leadingZeros = $matches[1] ?? '';
            $trailingNumbers = $matches[2] ?? '';

            if ($leadingZeros !== '' && $trailingNumbers !== '') {
                $trailingNumbers = strval(intval($trailingNumbers) + 1);

                if (strlen($trailingNumbers) >= strlen($suffix)) {
                    $leadingZeros = '';
                } else {
                    $lengthDiff = strlen($suffix) - strlen($trailingNumbers);
                    $leadingZeros = substr_replace($leadingZeros, '', $lengthDiff);
                }

            } else if ($leadingZeros !== '' && $trailingNumbers === '') {
                if (strlen($leadingZeros) === 1) {
                    $leadingZeros = strval(1);
                } else {
                    $leadingZeros = strval(value: substr($leadingZeros, 0, strlen($leadingZeros) - 1) . '1');
                }
            } else if ($leadingZeros === '' && $trailingNumbers !== '') {
                $trailingNumbers = strval(intval($trailingNumbers) + 1);
            }

            $suffix = $leadingZeros . $trailingNumbers;

        } while($suffix !== '' && in_array($suffix, $numericalSuffixes, true));

        $generatedUsername = $namePart . $suffix;

        return $generatedUsername;
    }

    public static function getAccessTokenId(?string $accessToken): string|null
    {
        if (!$accessToken) return null;

        $tokenParts = explode('.', $accessToken);
        $tokenHeader = base64_decode($tokenParts[1] ?? '');
        $tokenHeaderParts = json_decode($tokenHeader, true);
        $tokenId = $tokenHeaderParts['jti'] ?? null;

        return  $tokenId;
    }

    public static function findUserByAccessToken(?string $accessToken): User|null
    {
        $tokenDetails = new PassportToken($accessToken);

        if ($tokenDetails->valid && $tokenDetails->token_id) {
            return !!($token = Token::find($tokenDetails->token_id)) ? $token->user : null;
        }

        return null;
    }
}
