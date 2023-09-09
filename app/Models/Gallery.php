<?php

namespace App\Models;

use App\Traits\VerifiesAuthUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Gallery extends Model
{
    use VerifiesAuthUser;

    protected $table = 'gallery';
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'user_id'
    ];

    protected $with = ['images', 'user'];

    protected $withCount = ['images'];

    protected $appends = ['thumbnail_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function getThumbnailUrlAttribute()
    {
        $image = $this->thumb_image;

        return $image ? $image->url : null;
    }

    public function getThumbImageAttribute()
    {
        return $this->images()->first();
    }

    public function copy(array $data = array(), array &$result = null)
    {
        if ($this->user_id != Auth::id()) return null;

        $galleryData = Arr::only($data, ['name', 'description', 'is_active']);

        if (!count($galleryData)) return null;

        $newGallery = new Gallery(array_merge($this->toArray(), $galleryData));

        if (!$newGallery->save()) return null;

        $result = [
            'gallery' => ['old' => $this->id, 'new' => $newGallery->id],
            'images' => []
        ];

        $imageCaptions = isset($data['image_captions']) && is_array($data['image_captions']) ? $data['image_captions'] : [];
        $imageExclude = isset($data['image_exclude']) && is_array($data['image_exclude']) ? $data['image_exclude'] : [];

        foreach ($this->images as $image) {
            if (in_array($image->id, $imageExclude)) continue;

            $dateToday = Carbon::now()->format('Ymd');
            $name = $dateToday . '-' . $newGallery->user_id  . '-' . Str::random(32) . '-' . $newGallery->id . '.' . pathinfo($image->name, PATHINFO_EXTENSION);

            if (Storage::disk('images')->copy($image->name, $name)) {
                $newImage = new Image(array_merge(
                    Arr::except($image->toArray(), ['id', 'name', 'gallery_id', 'created_at', 'updated_at']),
                    ['name' => $name, 'gallery_id' => $newGallery->id]
                ));

                if (array_key_exists($image->id, $imageCaptions)) {
                    $newImage->caption = $imageCaptions[$image->id];
                }

                if ($newImage->save()) {
                    $result['images'][$image->id] = $newImage->id;
                }
            } else {
                throw new \Exception("Could not copy images");
            }
        }

        return $newGallery->refresh();
    }
}
