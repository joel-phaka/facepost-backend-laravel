<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyResource
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $modelName = null): Response
    {
        if (!!$modelName && ($model = $request->route($modelName))) {
            $isForbidden = false;

            if (!empty($model->user) && !$model->user->is_active) {
                $isForbidden = true;
            } else if (method_exists($model, 'verifyAuthUser') && !$model->verifyAuthUser()) {
                $isForbidden = true;
            }

            if ($isForbidden) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return $next($request);
    }
}
