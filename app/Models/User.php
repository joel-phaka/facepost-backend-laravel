<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\HasApiTokens;
use Plank\Metable\Metable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, Metable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'gender',
        'email',
        'password',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'meta'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'bool',
    ];

    protected $appends = [
        'profile_picture',
        'is_auth_user',
        'initials'
    ];

    protected $withCount = [
        'posts',
        'comments',
        'galleries',
        'images'
    ];

    public function userDetail()
    {
        return $this->hasOne(UserDetail::class, 'user_id', 'id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function galleries()
    {
        return $this->hasMany(Gallery::class);
    }

    public function loginLogs()
    {
        return $this->hasMany(LoginLog::class);
    }

    public function getProfilePictureAttribute()
    {
        $url = null;

        if (!!($profilePicture = Image::find($this->getMeta('profile_picture')))) {
            $url = $profilePicture->url;
        } else if ($this->providers()->count()) {
            $provider = $this->providers()->first();
            $url = !!$provider ? $provider->avatar : null;
        }

        return $url;
    }

    public function getIsAuthUserAttribute()
    {
        return $this->isAuthUser();
    }

    public function isAuthUser()
    {
        return Auth::check() &&
            $this->id == Auth::user()->id &&
            $this->email == Auth::user()->email &&
            $this->username == Auth::user()->username;
    }

    public function getInitialsAttribute()
    {
        $firstNameInitial = !!$this->first_name ? $this->first_name[0] : null;
        $lastNameArr = !!$this->last_name ? explode(' ', $this->last_name) : [];
        $lastNameInitial = empty($lastNameArr) ? null : $lastNameArr[count($lastNameArr) - 1][0];

        return "{$firstNameInitial}{$lastNameInitial}";
    }

    public function hasLiked($likeable)
    {
        return (bool)$this->likes()
            ->where('likeable_id', $likeable->id)
            ->where('likeable_type', get_class($likeable))
            ->count();
    }

    private function tryCreateUserDetail()
    {
        if (!!$this->id && !$this->user_detail) {
            return UserDetail::create(['user_id' => $this->id]);
        }

        return $this->user_detail;
    }

    public function addHobbies($hobbies)
    {
        if (is_array($hobbies) && !!count($hobbies) && $this->tryCreateUserDetail()) {
            $this->userDetail->setMeta('hobbies', $hobbies);
        }
    }

    public function providers()
    {
        return $this->hasMany(Provider::class,'user_id','id');
    }

    public static function getActiveUsers()
    {
        return static::where('is_active', 1);
    }
}
