<?php

namespace App\Models;

use App\Models\Gallery;
use App\Traits\HasLikes;
use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Image extends Model
{
    use HasLikes, HasMeta;

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
        'thumb',
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
    public function getThumbAttribute() {
        return !$this->getMetaValue('thumb') ? null : config('filesystems.files_link') . '/images/' . $this->getMetaValue('thumb');
    }

    public function getIsUserImageAttribute() {
        return $this->getAttribute('user_id') == Auth::id();
    }
}
