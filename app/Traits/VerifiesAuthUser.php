<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait VerifiesAuthUser
{
    public function verifyAuthUser($throwException = false)
    {
        $exists = $this->exists();

        abort_if($throwException && $exists && $this->user_id != Auth::id(), 403, 'Forbidden');

        return $exists && $this->user_id == Auth::id();
    }
}
