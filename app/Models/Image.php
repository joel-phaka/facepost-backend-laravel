<?php

namespace App\Models;

use App\Models\Gallery;
use App\Traits\HasLikes;
use App\Traits\HasMeta;
use App\Traits\VerifiesAuthUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Plank\Metable\Metable;

class Image extends Model
{
    use HasLikes, Metable, HasFactory, VerifiesAuthUser;

    protected $fillable = [
        'caption',
        'name',
        'thumb_name',
        'type',
        'width',
        'height',
        'user_id',
        'gallery_id',
        'meta'
    ];

    protected $hidden = ['meta'];

    protected $appends = [
        'url',
        'thumb_url',
        'belongs_to_auth_user',
    ];

    public function user()
    {
        return $this->belongsTo(Post::class);
    }

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function getUrlAttribute()
    {
        return config('filesystems.files_link.url') ? config('filesystems.files_link.url') . '/images/' . $this->name : null;
    }

    public function getThumbUrlAttribute()
    {
        return $this->thumb_name && config('filesystems.files_link.url') ? config('filesystems.files_link.url') . '/images/' . $this->thumb_name : null;
    }
}
