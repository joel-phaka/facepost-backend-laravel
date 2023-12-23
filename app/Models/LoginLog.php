<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Plank\Metable\Metable;

class LoginLog extends Model
{
    use Metable;

    protected $table = 'login_log';

    protected $fillable = [
        'user_id',
        'access_token',
        'external_auth',
        'external_auth_provider',
        'ip',
        'user_agent',
        'device_platform',
        'location',
        'country_code',
        'region_code',
        'are_code',
        'zip_code',
        'timezone',
        'date',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
