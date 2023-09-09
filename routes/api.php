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

    Route::middleware('auth:api')->group(function () {
        Route::get('user', 'Api\AuthController@user');
        Route::post('logout', 'Api\AuthController@logout');
    });
});

Route::middleware('auth:api')->group(function () {
    Route::apiResource('posts', 'Api\PostController')->except(['update']);
    Route::post('posts/{post}', 'Api\PostController@update')->name('posts.update ');

    Route::apiResource('gallery', 'Api\GalleryController')->except(['update']);
    Route::post('gallery/{gallery}', 'Api\GalleryController@update')->name('gallery.update ');

    Route::apiResource('comments', 'Api\CommentController');
    Route::post('comments/reply/{comment}', 'Api\CommentController@replyToComment');
    Route::get('comments/thread/{comment}', 'Api\CommentController@thread');
    Route::get('comments/replies/{comment}', 'Api\CommentController@thread');
    Route::get('comments/post/{post}', 'Api\CommentController@getPostComments');

    Route::post('/likes/like/{type_name}/{type_id}', 'Api\LikeController@like')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');
    Route::delete('/likes/unlike/{type_name}/{type_id}', 'Api\LikeController@unlike')
        ->where('type_name', '(' . implode('|', array_keys(Like::getLikeableTypes())) . ')');

    Route::post('images/upload', 'Api\ImageController@upload');
    Route::delete('images/remove', 'Api\ImageController@destroy');

    Route::get('profile', 'Api\ProfileController@index');
    Route::get('profile/{user:username}', 'Api\ProfileController@show');
    Route::get('profile/{user:username}/images', 'Api\ProfileController@getUserImages');
    Route::get('profile/{user:username}/galleries', 'Api\ProfileController@getUserGalleries');
    Route::get('profile/{user:username}/posts', 'Api\ProfileController@getUserPosts');
    Route::get('profile/{user:username}/comments', 'Api\ProfileController@getUserComments');
    Route::get('profile/{user:username}/likes/{type_name}', 'Api\ProfileController@getUserLikes')
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
