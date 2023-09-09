<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasLikes
{
    public function getIsLikedAttribute()
    {
        return Auth::check() ? Auth::user()->hasLiked($this) : false;
    }
}
