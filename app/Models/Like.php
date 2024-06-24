<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;

    protected $table = 'likeable';

    protected $fillable = [
        'likeable_id',
        'likeable_type',
        'user_id'
    ];

    private static $likeableTypes = [
        'post' => Post::class,
        'comment' => Comment::class,
        'image' => Image::class,
    ];

    /**
     * Get the user that initiated the like.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the liked object.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function likeable()
    {
        return $this->morphTo();
    }

    public static function getLikeableType($typeName = null) {
        return !empty(self::$likeableTypes[$typeName]) ? self::$likeableTypes[$typeName] : null;
    }

    public static function getLikeableTypes() {
        return self::$likeableTypes;
    }
}
