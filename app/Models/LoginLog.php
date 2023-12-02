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
        'ip',
        'user_agent',
        'device',
        'location',
        'meta',
        'date',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
