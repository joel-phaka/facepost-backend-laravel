<?php

namespace App\Models;

use App\Traits\RelationOfActiveUsers;
use App\Traits\VerifiesAuthUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Gallery extends Model
{
    use VerifiesAuthUser, HasFactory, RelationOfActiveUsers, VerifiesAuthUser;

    protected $table = 'gallery';
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'user_id'
    ];

    protected $with = [];

    protected $withCount = [
        'images'
    ];

    protected $appends = [
        'thumbnail_url',
        'belongs_to_auth_user'
    ];

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
        $this->verifyAuthUser();

        $galleryData = array_map('trim', Arr::only($data, ['name', 'description', 'is_active']));

        if (!count($galleryData) || empty($galleryData['name'])) {
            throw new \Exception("Gallery name is required");
        }

        $newGallery = new Gallery(array_merge($this->toArray(), $galleryData));

        if (!$newGallery->save()) {
            throw new \Exception("Could not create gallery copy.");
        };

        $result = [
            'gallery' => ['old' => $this->id, 'new' => $newGallery->id],
            'images' => []
        ];

        $imageCaptions = isset($data['image_captions']) && is_array($data['image_captions']) ? $data['image_captions'] : [];
        $imageExclude = isset($data['image_exclude']) && is_array($data['image_exclude']) ? $data['image_exclude'] : [];
        $imageExclude = array_unique(array_filter(array_filter(array_map('intval', $imageExclude), 'intval'), 'ctype_digit'));

        foreach ($this->images as $image) {
            if (in_array($image->id, $imageExclude)) continue;

            $dateToday = Carbon::now()->format('Ymd');
            $tmpName = $dateToday . '-' . $newGallery->user_id  . '-' . Str::random(32) . '-' . $newGallery->id;
            $name = "{$tmpName}." . pathinfo($image->name, PATHINFO_EXTENSION);
            $thumb_name = "{$tmpName}_thumb." . pathinfo($image->name, PATHINFO_EXTENSION);

            if (Storage::disk('images')->copy($image->name, $name) && (!$image->thumb_name || Storage::disk('images')->copy($image->thumb_name, $thumb_name))) {
                $newImage = new Image(array_merge(
                    Arr::except($image->toArray(), ['id', 'name', 'gallery_id', 'created_at', 'updated_at']),
                    ['name' => $name, 'thumb_name' => $thumb_name, 'gallery_id' => $newGallery->id]
                ));

                if (array_key_exists($image->id, $imageCaptions)) {
                    $newImage->caption = $imageCaptions[$image->id];
                }

                if ($newImage->save()) {
                    $result['images'][$image->id] = $newImage;
                }
            }
        }

        return $newGallery->refresh();
    }
}
