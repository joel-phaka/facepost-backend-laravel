<?php

namespace App\Helpers;

use App\Models\User;
use Laravel\Passport\Token;
use PeterPetrus\Auth\PassportToken;

class AuthUtils
{
    public static function generateUsername($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(500, "Internal Server Error: Invalid Email");
        }

        $usernameOfEmail = explode("@", $email)[0];

        preg_match('/^(.*[^0-9])([0-9]*)$/', $usernameOfEmail, $matches1);

        if (empty($matches1[1])) {
            return $usernameOfEmail;
        }

        $namePart = $matches1[1];

        $usernames = User::where('username', 'REGEXP', $namePart . '[0-9]*')
            ->latest()
            ->pluck('username')
            ->toArray();

        rsort($usernames);

        if (!count($usernames)) {
            $generatedUsername = $usernameOfEmail;
        } else {
            $perfMatch = $usernames[0];

            preg_match('/^(.*[^0-9])([0-9]*)$/', $perfMatch, $matches2);

            if (!$matches2[2]) {
                $generatedUsername = $namePart . '1';
            } else {
                $numberPart = $matches2[2];

                preg_match('/^(0*)([1-9][0-9]*)/', $numberPart, $matches3);

                $zeros = $matches3[1];
                $numbers = intval($matches3[2]) + 1;
                $numberPart = $zeros . $numbers;

                $generatedUsername = $namePart . $numberPart;
            }

        }

        return $generatedUsername;
    }

    public static function getAccessTokenId($access_token)
    {
        if (!$access_token) return null;

        $token_parts = explode('.', $access_token);
        $token_header = $token_parts[1];
        $token_header_json = base64_decode($token_header);
        $token_header_array = json_decode($token_header_json, true);
        $token_id = $token_header_array['jti'];

        return  $token_id;
    }

    public static function findUserByAccessToken($access_token)
    {
        $tokenDetails = new PassportToken($access_token);

        if ($tokenDetails->valid && $tokenDetails->token_id) {
            return !!($token = Token::find($tokenDetails->token_id)) ? $token->user : null;
        }

        return null;
    }
}
