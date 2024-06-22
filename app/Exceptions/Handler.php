<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Str;
use PeterPetrus\Auth\PassportToken;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AuthenticationException) {
            if (in_array('auth:api', $request->route()->gatherMiddleware())) {
                $accessToken = $request->bearerToken() ?? (!!($t = substr($request->server('HTTP_AUTHORIZATION'), 7)) ? $t : null);

                if (!!$accessToken) {
                    $accessTokenInfo = new PassportToken($accessToken);

                    if ($accessTokenInfo->expired) {
                        return response()->json([
                            "message" => $exception->getMessage(),
                            "error_code" => "expired_token",
                            "context" => "access_token"
                        ], 401);
                    }
                }
            }
        }


        return parent::render($request, $exception);
    }
}
