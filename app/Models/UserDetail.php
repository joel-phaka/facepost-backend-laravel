<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;

class UserDetail extends Model
{
    use Metable;

    protected $primaryKey = 'user_id';

    protected $table = 'user_details';

    protected $fillable = [
        'user_id',
        'country_code',
        'biography',
        'meta'
    ];

    protected $hidden = ['meta'];

    protected $appends = ['hobbies', 'interests'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getHobbiesAttribute()
    {
        return ($hobbies = json_decode($this->getMeta('hobbies'), true)) && is_array($hobbies) ? $hobbies : [];
    }

    public function getInterestsAttribute()
    {
        return ($interests = json_decode($this->getMeta('interests'), true)) && is_array($interests) ? $interests : [];
    }
}
