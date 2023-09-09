<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $fillable = [
        'provider_id',
        'provider',
        'user_id',
        'avatar'
    ];
}
