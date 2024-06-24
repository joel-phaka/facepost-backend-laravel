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
    Route::apiResource('posts', 'App\Http\Controllers\Api\PostController')->except(['update,destroy']);
    Route::post('posts/{post}', 'App\Http\Controllers\Api\PostController@update')
        ->middleware('verify_auth_user_resource:post');
    Route::delete('posts/{post}', 'App\Http\Controllers\Api\PostController@destroy')
        ->middleware('verify_auth_user_resource:post');

    Route::apiResource('gallery', 'App\Http\Controllers\Api\GalleryController')->except(['update,destroy']);
    Route::post('gallery/{gallery}', 'App\Http\Controllers\Api\GalleryController@update')
        ->middleware('auth.owner:gallery');
    Route::delete('gallery/{gallery}', 'App\Http\Controllers\Api\GalleryController@update')
        ->middleware('auth.owner:gallery');

    Route::apiResource('comments', 'App\Http\Controllers\Api\CommentController')->except(['update,destroy']);
    Route::post('comments/{comment}', 'App\Http\Controllers\Api\CommentController@update')
        ->middleware('auth.owner:comment');
    Route::delete('comments/{comment}', 'App\Http\Controllers\Api\CommentController@destroy')
        ->middleware('auth.owner:comment');

    Route::post('comments/reply/{comment}', 'App\Http\Controllers\Api\CommentController@replyToComment');
    Route::get('comments/thread/{comment}', 'App\Http\Controllers\Api\CommentController@thread');
    Route::get('comments/replies/{comment}', 'App\Http\Controllers\Api\CommentController@thread');
    Route::get('comments/post/{post}', 'App\Http\Controllers\Api\CommentController@getPostComments');

    Route::post('/likes/like/{type_name}/{type_id}', 'App\Http\Controllers\Api\LikeController@like')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');
    Route::delete('/likes/unlike/{type_name}/{type_id}', 'App\Http\Controllers\Api\LikeController@unlike')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');

    Route::get('images/{user?}', 'App\Http\Controllers\Api\ImageController@index');
    Route::post('images/upload', 'App\Http\Controllers\Api\ImageController@upload');
    Route::delete('images/remove', 'App\Http\Controllers\Api\ImageController@destroy');

    Route::get('profile', 'App\Http\Controllers\Api\ProfileController@index');
    Route::get('profile/{user}', 'App\Http\Controllers\Api\ProfileController@show');
    Route::get('profile/{user}/images', 'App\Http\Controllers\Api\ProfileController@getUserImages');
    Route::get('profile/{user}/galleries', 'App\Http\Controllers\Api\ProfileController@getUserGalleries');
    Route::get('profile/{user}/posts', 'App\Http\Controllers\Api\ProfileController@getUserPosts');
    Route::get('profile/{user}/comments', 'App\Http\Controllers\Api\ProfileController@getUserComments');
    Route::get('profile/{user}/likes/{type_name}', 'App\Http\Controllers\Api\ProfileController@getUserLikes')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');

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
