<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function index()
    {
        Auth::user()->load('userDetail');

        return Auth::user();
    }

    public function show(User $user)
    {
        $user->load('userDetail');

        return $user;
    }

    public function getUserImages(User $user)
    {
        return Utils::paginate($user->images());
    }

    public function getUserGalleries(User $user, $activeOnly = false)
    {
        return Utils::paginate(!$activeOnly ? $user->galleries() : $user->galleries()->where('is_active', true));
    }

    public function getUserPosts(User $user)
    {
        return Utils::paginate($user->posts());
    }

    public function getUserComments(User $user)
    {
        return Utils::paginate($user->comments());
    }

    public function getUserLikes(User $user, $typeName)
    {
        return Utils::paginate($user->comments());
    }
}
