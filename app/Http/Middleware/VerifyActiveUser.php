<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class VerifyActiveUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (auth()->check() && !auth()->user()->is_active) {
            auth()->user()->tokens
                ->where('revoked', false)
                ->update(['revoked' => true]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
