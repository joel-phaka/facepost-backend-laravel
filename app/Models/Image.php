<?php

namespace App\Models;

use App\Models\Gallery;
use App\Traits\HasLikes;
use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Plank\Metable\Metable;

class Image extends Model
{
    use HasLikes, Metable;

    protected $fillable = [
        'caption',
        'name',
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
        'is_user_image',
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
        return config('filesystems.files_link') . '/images/' . $this->name;
    }

    public function getThumbUrlAttribute()
    {
        return config('filesystems.files_link') . '/images/' . $this->thumb_name;
    }

    public function getIsUserImageAttribute() {
        return $this->getAttribute('user_id') == Auth::id();
    }
}
