<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.refresh.custom' => \App\Http\Middleware\RefreshJwtToken::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = 500;
            $message = 'Server Error';
            $errors = null;

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $status  = 422;
                $message = 'The given data was invalid.';
                $errors  = $e->errors();
            } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $status  = 401;
                $message = 'Unauthenticated.';
            } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $status  = 403;
                $message = 'Forbidden.';
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $status  = 404;
                $message = 'Resource not found.';
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                $status  = 405;
                $message = 'Method not allowed.';
            } elseif ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                $status  = 429;
                $message = 'Too many requests. Please try again later.';
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $status  = $e->getStatusCode();
                $message = $e->getMessage() ?: $message;
           } elseif ($e instanceof \Tymon\JWTAuth\Exceptions\JWTException) {
        $status  = 401;
    $message = 'Unauthenticated or token expired.';
}


            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], $status);
        });

    })->create();
