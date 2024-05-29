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
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('login', 'Api\AuthController@login')->name('login');
    Route::post('register', 'Api\AuthController@register')->name('register');

    Route::middleware(['auth:api', 'auth.active'])->group(function () {
        Route::get('user', 'Api\AuthController@user');
        Route::post('logout', 'Api\AuthController@logout');
    });
});

Route::middleware(['auth:api', 'auth.active'])->group(function () {
    Route::apiResource('posts', 'Api\PostController')->except(['update,destroy']);
    Route::post('posts/{post}', 'Api\PostController@update')
        ->middleware('verify_auth_user_resource:post')
        ->name('posts.update');
    Route::delete('posts/{post}', 'Api\PostController@destroy')
        ->middleware('verify_auth_user_resource:post')
        ->name('posts.destroy');

    Route::apiResource('gallery', 'Api\GalleryController')->except(['update,destroy']);
    Route::post('gallery/{gallery}', 'Api\GalleryController@update')
        ->middleware('auth.owner:gallery')
        ->name('gallery.update');
    Route::delete('gallery/{gallery}', 'Api\GalleryController@update')
        ->middleware('auth.owner:gallery')
        ->name('gallery.destroy');

    Route::apiResource('comments', 'Api\CommentController')->except(['update,destroy']);
    Route::post('comments/{comment}', 'Api\CommentController@update')
        ->middleware('auth.owner:comment')
        ->name('comments.update');
    Route::delete('comments/{comment}', 'Api\CommentController@destroy')
        ->middleware('auth.owner:comment')
        ->name('comments.destroy');

    Route::post('comments/reply/{comment}', 'Api\CommentController@replyToComment');
    Route::get('comments/thread/{comment}', 'Api\CommentController@thread');
    Route::get('comments/replies/{comment}', 'Api\CommentController@thread');
    Route::get('comments/post/{post}', 'Api\CommentController@getPostComments');

    Route::post('/likes/like/{type_name}/{type_id}', 'Api\LikeController@like')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');
    Route::delete('/likes/unlike/{type_name}/{type_id}', 'Api\LikeController@unlike')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');

    Route::get('images/{user?}', 'Api\ImageController@index');
    Route::post('images/upload', 'Api\ImageController@upload');
    Route::delete('images/remove', 'Api\ImageController@destroy');

    Route::get('profile', 'Api\ProfileController@index');
    Route::get('profile/{user}', 'Api\ProfileController@show');
    Route::get('profile/{user}/images', 'Api\ProfileController@getUserImages');
    Route::get('profile/{user}/galleries', 'Api\ProfileController@getUserGalleries');
    Route::get('profile/{user}/posts', 'Api\ProfileController@getUserPosts');
    Route::get('profile/{user}/comments', 'Api\ProfileController@getUserComments');
    Route::get('profile/{user}/likes/{type_name}', 'Api\ProfileController@getUserLikes')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');

    //Route::post('account/profile', 'Api\AccountController@updateProfile');
    //Route::post('account/profile', 'Api\AccountController@updateProfile');
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
