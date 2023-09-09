<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    use HasMeta;

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
        $hobbies = $this->getMetaValue('hobbies');

        return is_array($hobbies) ? $hobbies : [];
    }

    public function getInterestsAttribute()
    {
        $interests = $this->getMetaValue('interests');

        return is_array($interests) ? $interests : [];
    }
}
