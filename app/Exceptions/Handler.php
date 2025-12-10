<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Validation\AuthenticationException;

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
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        // Handle API / JSON requests with a unified error shape
        if ($request->expectsJson() || $request->is('api/*')) {
            $status = 500;

            // Special handling for certain exception types if needed
            if ($exception instanceof AuthenticationException) {
                $status = 401;
            }

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request.',

                // Hide in production for security reasons
                'error' => config('app.debug') ? $exception->getMessage() : null,
            ], $status);
        }

        // Else, fall back to default HTML/non-API handling
        return parent::render($request, $exception);
    }
}
