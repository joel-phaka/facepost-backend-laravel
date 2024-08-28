<?php

use Illuminate\Http\Request;
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
    Route::post('login', 'App\Http\Controllers\Api\AuthController@login');
    Route::post('refresh', 'App\Http\Controllers\Api\AuthController@refresh');
    Route::post('register', 'App\Http\Controllers\Api\AuthController@register');

    Route::middleware(['auth:api', 'auth.active'])->group(function () {
        Route::get('user', 'App\Http\Controllers\Api\AuthController@user');
        Route::post('logout', 'App\Http\Controllers\Api\AuthController@logout');
    });
});

Route::middleware(['auth:api', 'auth.active'])->group(function () {
    Route::apiResource('posts', 'App\Http\Controllers\Api\PostController')->except(['update', 'destroy', 'show']);
    Route::get('posts/{post}', 'App\Http\Controllers\Api\PostController@show');
    Route::post('posts/{post}', 'App\Http\Controllers\Api\PostController@update')
        ->middleware(['verify_resource:post']);
    Route::delete('posts/{post}', 'App\Http\Controllers\Api\PostController@destroy')
        ->middleware('verify_resource:post');

    Route::apiResource('gallery', 'App\Http\Controllers\Api\GalleryController')->except(['update', 'destroy', 'show']);
    Route::get('gallery/{gallery}', 'App\Http\Controllers\Api\GalleryController@show');
    Route::post('gallery/{gallery}', 'App\Http\Controllers\Api\GalleryController@update')
        ->middleware('verify_resource:gallery');
    Route::delete('gallery/{gallery}', 'App\Http\Controllers\Api\GalleryController@destroy')
        ->middleware('verify_resource:gallery');

    Route::apiResource('comments', 'App\Http\Controllers\Api\CommentController')->except(['update', 'destroy', 'show']);
    Route::get('comments/{comment}', 'App\Http\Controllers\Api\CommentController@show');
    Route::post('comments/{comment}', 'App\Http\Controllers\Api\CommentController@update')
        ->middleware('verify_resource:comment');
    Route::delete('comments/{comment}', 'App\Http\Controllers\Api\CommentController@destroy')
        ->middleware('verify_resource:comment');

    Route::post('comments/reply/{comment}', 'App\Http\Controllers\Api\CommentController@replyToComment');
    Route::get('comments/thread/{comment}', 'App\Http\Controllers\Api\CommentController@thread');
    Route::get('comments/replies/{comment}', 'App\Http\Controllers\Api\CommentController@thread');
    Route::get('comments/post/{post}', 'App\Http\Controllers\Api\CommentController@getPostComments');

    Route::post('/likes/like/{type_name}/{type_id}', 'App\Http\Controllers\Api\LikeController@like')
        ->whereIn('type_name', array_keys(Like::getLikeableTypes()));
    Route::delete('/likes/unlike/{type_name}/{type_id}', 'App\Http\Controllers\Api\LikeController@unlike')
        ->whereIn('type_name', array_keys(Like::getLikeableTypes()));

    Route::get('images/{user?}', 'App\Http\Controllers\Api\ImageController@index');
    Route::post('images/upload', 'App\Http\Controllers\Api\ImageController@upload');
    Route::delete('images/remove', 'App\Http\Controllers\Api\ImageController@destroy');

    Route::group([
        'prefix' => 'profile'
    ], function () {
        Route::get('/', 'App\Http\Controllers\Api\ProfileController@index');
        Route::get('/{user}', 'App\Http\Controllers\Api\ProfileController@show');
        Route::get('/{user}/images', 'App\Http\Controllers\Api\ProfileController@getUserImages');
        Route::get('/{user}/galleries', 'App\Http\Controllers\Api\ProfileController@getUserGalleries');
        Route::get('/{user}/posts', 'App\Http\Controllers\Api\ProfileController@getUserPosts');
        Route::get('/{user}/comments', 'App\Http\Controllers\Api\ProfileController@getUserComments');
        Route::get('/{user}/likes/{type_name}', 'App\Http\Controllers\Api\ProfileController@getUserLikes')
            ->whereIn('type_name', array_keys(Like::getLikeableTypes()));
    });

    //Route::post('account/profile', 'App\Http\Controllers\Api\AccountController@updateProfile');
    //Route::post('account/profile', 'App\Http\Controllers\Api\AccountController@updateProfile');
});

/*Route::group([
    'namespace' => 'Auth',
    'middleware' => 'api',
    'prefix' => 'password'
], function () {
    Route::post('create', 'PasswordResetController@create');
    Route::get('find/{token}', 'PasswordResetController@find');
    Route::post('reset', 'PasswordResetController@reset');
});*/
