<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class VerifyOwnership
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
        if (!!$modelName && ($model = $request->route($modelName)) && method_exists($model, 'verifyAuthUser')) {
            if ($model->verifyAuthUser()) {
                return response()->json(['message' => 'Forbidden'], 403);
            };
        }

        return $next($request);
    }
}
