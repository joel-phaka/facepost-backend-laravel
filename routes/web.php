<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/files/{path}', function ($path) {
    $filepath = storage_path("app" . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . $path);
    $pathInfo = pathinfo($filepath);

    if (file_exists($filepath) && isset($pathInfo['basename']) && strpos($pathInfo['basename'], '.') !== 0) {
        $mimeType = mime_content_type($filepath);
        return response()->file($filepath, ['Content-Type' => $mimeType]);
    }

    abort(404, "Not Found");
})->where([
    'path' => '.+',
]);;

Route::get('/login/{provider}', 'App\Http\Controllers\Api\AuthController@redirectToProvider')
    ->where('provider', '(' . implode('|', config('services.providers_list')) . ')');

Route::get('/login/{provider}/callback', 'App\Http\Controllers\Api\AuthController@handleProviderCallback')
    ->where('provider', '(' . implode('|', config('services.providers_list')) . ')');

Route::group([
    'prefix' => 'oauth'
], function () {
    Route::post('/token', [
        'uses' => '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken',
        'as' => 'token',
        'middleware' => 'throttle',
    ]);

    Route::post('/token/refresh', [
        'uses' => '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken',
        'as' => 'token.refresh',
    ]);
});
