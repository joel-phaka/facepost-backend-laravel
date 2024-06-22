<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
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
