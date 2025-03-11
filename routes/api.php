<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Models\Like;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware(['auth:api', 'auth.active'])->group(function () {
        Route::post('/', [AuthController::class, 'loginWithAccessToken']);
        Route::get('user', [AuthController::class, 'getUser']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:api', 'auth.active'])->group(function () {
    Route::apiResource('posts', PostController::class)->except(['update', 'destroy', 'show']);
    Route::get('posts/{post}', [PostController::class, 'show']);
    Route::get('posts/{post}/images', [PostController::class, 'getPostImages']);
    Route::post('posts/{post}', [PostController::class, 'update'])
        ->middleware(['verify_resource:post']);
    Route::delete('posts/{post}', [PostController::class, 'destroy'])
        ->middleware('verify_resource:post');

    Route::apiResource('gallery', GalleryController::class)->except(['update', 'destroy', 'show']);
    Route::get('gallery/{gallery}', [GalleryController::class, 'show']);
    Route::post('gallery/{gallery}', [GalleryController::class, 'update'])
        ->middleware('verify_resource:gallery');
    Route::delete('gallery/{gallery}', [GalleryController::class, 'destroy'])
        ->middleware('verify_resource:gallery');

    Route::apiResource('comments', CommentController::class)->except(['update', 'destroy', 'show']);
    Route::get('comments/{comment}', [CommentController::class, 'show']);
    Route::post('comments/{comment}', [CommentController::class, 'update'])
        ->middleware('verify_resource:comment');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])
        ->middleware('verify_resource:comment');

    Route::post('comments/reply/{comment}', [CommentController::class, 'replyToComment']);
    Route::get('comments/thread/{comment}', [CommentController::class, 'getCommentReplies']);
    Route::get('comments/replies/{comment}', [CommentController::class, 'getCommentReplies']);
    Route::get('comments/post/{post}', [CommentController::class, 'getPostComments']);

    Route::post('/likes/like/{type_name}/{type_id}', [LikeController::class, 'like'])
        ->whereIn('type_name', array_keys(Like::getLikeableTypes()));
    Route::delete('/likes/unlike/{type_name}/{type_id}', [LikeController::class, 'unlike'])
        ->whereIn('type_name', array_keys(Like::getLikeableTypes()));

    Route::get('images/{user?}', [ImageController::class, 'index']);
    Route::post('images/upload', [ImageController::class, 'upload']);
    Route::delete('images/remove', [ImageController::class, 'destroy']);

    Route::group([
        'prefix' => 'profile'
    ], function () {
        Route::get('/', [ProfileController::class, 'index']);
        Route::get('/{user}', [ProfileController::class, 'show']);
        Route::get('/{user}/images', [ProfileController::class, 'getUserImages']);
        Route::get('/{user}/galleries', [ProfileController::class, 'getUserGalleries']);
        Route::get('/{user}/posts', [ProfileController::class, 'getUserPosts']);
        Route::get('/{user}/comments', [ProfileController::class, 'getUserComments']);
        Route::get('/{user}/likes/{type_name}', [ProfileController::class, 'getUserLikes'])
            ->whereIn('type_name', array_keys(Like::getLikeableTypes()));
        Route::get('/{user}/picture', [ProfileController::class, 'getPicture']);
    });

    // Route::post('account/profile', [AccountController::class, 'updateProfile']);
    // Route::post('account/profile', [AccountController::class, 'updateProfile']);
});

/*Route::group([
    'namespace' => 'Auth',
    'middleware' => 'api',
    'prefix' => 'password'
], function () {
    Route::post('create', [PasswordResetController::class, 'create']);
    Route::get('find/{token}', [PasswordResetController::class, 'find']);
    Route::post('reset', [PasswordResetController::class, 'reset']);
});*/
