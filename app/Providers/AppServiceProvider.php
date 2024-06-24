<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));
        if (str_starts_with(config('app.url'), "https")) {
            URL::forceScheme('https');
        }

        Paginator::currentPathResolver(function () {
            $path = trim(request()->path(), '/');

            if (!!$path && !str_starts_with($path, '?')) {
                $path = '/' . $path;
            }

            return config('app.url') . $path;
        });
    }
}
