<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait VerifiesAuthUser
{
    public function verifyAuthUser($throwException = false, $checkExists = true): bool
    {
        $exists = !$checkExists || $this->exists();
        $isAuthUser = $exists && Auth::check() && $this->user_id == Auth::id();

        abort_if($throwException && !$isAuthUser, 403, 'Forbidden');

        return $isAuthUser;
    }

    public function getBelongsToAuthUserAttribute(): bool
    {
        return $this->verifyAuthUser(false, false);
    }
}
