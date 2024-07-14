<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\CreateCommentRequest;
use App\Http\Requests\Comment\ReplyToCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Helpers\Utils;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function index()
    {
        $comments = Comment::ofActiveUsers()
            ->whereNull('parent_id')
            ->latest();

        return response()->json(Utils::paginate($comments));
    }

    public function store(CreateCommentRequest $request)
    {
        $comment = Comment::create([
            'post_id' => $request->input('post_id'),
            'user_id' => Auth::id(),
            'content' => $request->input('content')
        ]);

        $comment = Comment::find($comment->id);

        return response()->json($comment);
    }

    public function show(Comment $comment)
    {
        return response()->json($comment);
    }

    public function update(Comment $comment, UpdateCommentRequest $request)
    {
        $comment->update($request->only(['content'])) ;

        return response()->json($comment);
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();

        /* Delete replies
        Comment::where('parent_id', $comment->id)->delete();
        */

        // Delete likes
        Like::where('likeable_type', Comment::class)
            ->where('likeable_id', $comment->id)
            ->delete();

        return response()->json($comment);
    }

    public function getPostComments(Post $post)
    {
        $comments = Comment::ofActiveUsers()
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->latest();

        return response()->json(Utils::paginate($comments));
    }

    public function thread(Comment $comment)
    {
        $comments = Comment::ofActiveUsers()
            ->where('parent_id', $comment->id)
            ->latest();

        return response()->json(Utils::paginate($comments));
    }

    public function replyToComment(Comment $comment, ReplyToCommentRequest $request)
    {
        $reply = Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $comment->post_id,
            'parent_id' => $comment->id,
            'content' => $request->input('content')
        ]);
        $reply->load('user');

        return response()->json($reply->refresh());
    }
}
