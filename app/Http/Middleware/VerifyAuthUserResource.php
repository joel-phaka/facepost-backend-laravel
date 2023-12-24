<?php

namespace App\Http\Middleware;

use Closure;

class VerifyAuthUserResource
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $modelName = null)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && !!$modelName && ($model = $request->route($modelName)) && method_exists($model, 'verifyAuthUser')) {
            $model->verifyAuthUser(true, false);
        }

        return $next($request);
    }
}
