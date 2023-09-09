<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait VerifiesAuthUser
{
    public function verifyAuthUser($throwException = false)
    {
        abort_if($throwException && $this->user_id == Auth::id(), 403, 'Forbidden');

        return $this->user_id == Auth::id();
    }
}
