<?php

use Illuminate\Http\Request;
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

Route::get('/files/{path}', function (Request $request, $path) {
    $filepath = storage_path("app" . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . $path);
    $basename = pathinfo($filepath, PATHINFO_BASENAME);
    $extension = pathinfo($filepath, PATHINFO_EXTENSION);
    $allowedExtensions = config('filesystems.files_link.allowed_extensions');


    if (file_exists($filepath) && !str_starts_with($basename, '.') && in_array($extension, $allowedExtensions)) {
        $mimeType = mime_content_type($filepath);
        $fileStream = fopen($filepath, 'rb');
        $headers = [
            'Content-Type' => $mimeType,
        ];

        if ($request->has('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="' . $basename . '"';
        }

        return response()->stream(
            function () use ($fileStream) {
                fpassthru($fileStream);
                fclose($fileStream);
            },
            200,
            $headers
        );
    }

    abort(404, "Not Found");
})->where([
    'path' => '.+',
]);;

Route::group([
    'prefix' => 'login'
], function () {
    Route::get('/{provider}', 'App\Http\Controllers\Api\AuthController@redirectToProvider')
        ->whereIn('provider', config('services.providers_list'));
    Route::get('/{provider}/callback', 'App\Http\Controllers\Api\AuthController@handleProviderCallback')
        ->whereIn('provider', config('services.providers_list'));
});

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
