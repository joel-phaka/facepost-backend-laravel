<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait RelationOfActiveUsers
{
    public static function ofActiveUsers()
    {
        return static::whereHas('user', function (Builder $query) {
            $query->where('is_active', 1);
        });
    }
}
