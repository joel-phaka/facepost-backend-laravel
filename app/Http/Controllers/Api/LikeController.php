<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Image;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    public const LIKEABLE_MAPPING = [
        'post' => Post::class,
        'comment' => Comment::class,
        'image' => Image::class,
    ];

    public function like($typeName, $typeId)
    {
        $typeClass = Like::getLikeableType($typeName);

        if (!!$typeClass) {
            $typeObject = call_user_func_array([$typeClass, 'findOrFail'], [$typeId]);

            $success = false;

            if (!$typeObject->isLiked) {
                $like = new Like([
                    'likeable_type' => $typeClass,
                    'likeable_id' => $typeId,
                    'user_id' => Auth::id()
                ]);

                $like->save();
                $success  = true;
            }
            $typeObject = call_user_func([$typeObject, 'refresh']);

            return response()->json(['success' => $success, $typeName => $typeObject]);
        }

        return response()->json(['message' => 'An error occurred'], 500);
    }

    public function unlike($typeName, $typeId)
    {
        $typeClass = Like::getLikeableType($typeName);

        if (!!$typeClass) {
            $typeObject = call_user_func_array([$typeClass, 'findOrFail'], [$typeId]);

            if ($typeObject->user_id != Auth::id()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $success = false;

            if ($typeObject->isLiked) {
                $success = (bool)Auth::user()->likes()
                    ->where('likeable_type', $typeClass)
                    ->where('likeable_id', $typeId)
                    ->delete();
            }
            $typeObject = call_user_func([$typeObject, 'refresh']);

            return response()->json(['success' => $success, $typeName => $typeObject]);
        }

        return response()->json(['message' => 'An error occurred'], 500);
    }
}
