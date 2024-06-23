<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        URL::forceRootUrl(config('app.url'));
        if (strpos(config('app.url'), "https") === 0) {
            URL::forceScheme('https');
        }

        Paginator::currentPathResolver(function () {
            $path = trim(request()->path(), '/');

            if (!!$path && strpos($path, '?') !== 0) {
                $path = '/' . $path;
            }

            return config('app.url') . $path;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
