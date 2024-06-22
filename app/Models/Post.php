<?php

namespace App\Models;

use App\Models\Gallery;
use App\Traits\HasLikes;
use App\Traits\HasMeta;
use App\Traits\VerifiesAuthUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Plank\Metable\Metable;

class Post extends Model
{
    use HasLikes, Metable, VerifiesAuthUser;

    protected $fillable = [
        'title',
        'content',
        'user_id',
        'gallery_id',
        'meta',
    ];

    protected $hidden = ['meta'];

    protected $with = ['user'];

    protected $withCount = ['likes', 'comments'];

    protected $appends = ['poster_image', 'poster_image_thumb', 'gallery_images_count'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function getPosterImage()
    {
        return Image::find($this->getMeta('poster_image'));
    }
    public function getPosterImageAttribute()
    {
        if (!!($posterImage = $this->getPosterImage())) {
            return $posterImage->url;
        }
        return null;
    }

    public function getPosterImageThumbAttribute()
    {
        if (!!($posterImage = $this->getPosterImage())) {
            return $posterImage->thumb_url;
        }
        return null;
    }

    public function getGalleryImagesCountAttribute()
    {
        return !$this->gallery_id ? 0 : Image::where('gallery_id', $this->gallery_id)->count();
    }
}
