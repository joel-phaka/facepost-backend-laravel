<?php

namespace App\Models;

use App\Traits\HasLikes;
use App\Traits\RelationOfActiveUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasLikes, HasFactory, RelationOfActiveUsers;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'post_id' ,
        'parent_id',
        'content'
    ];

    protected $with = ['user'];

    protected $withCount = ['likes'];

    protected $appends = [
        'is_reply',
        'is_liked',
        'replies_count',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parentComment()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function getIsReplyAttribute()
    {
        return !!$this->parent_id;
    }

    public function getRepliesCountAttribute()
    {
        return $this->replies()->count();
    }
}
