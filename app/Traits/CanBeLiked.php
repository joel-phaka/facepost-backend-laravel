<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait CanBeLiked
{
    public function isLiked(): bool
    {
        return Auth::check() && !!Auth::user()->likes()
                ->where('likeable_type', static::class)
                ->where('likeable_id', $this->id)
                ->count();

    }
}
