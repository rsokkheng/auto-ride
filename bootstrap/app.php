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
    // POST /api/v1/broadcasting/auth — the app authorizes private Reverb
    // channels with its normal bearer api_token.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api/v1', 'middleware' => ['api', \App\Http\Middleware\AuthenticateApiToken::class]],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SetApiLocale::class,
            // No-op unless the request sends an Idempotency-Key header.
            \App\Http\Middleware\EnsureIdempotency::class,
        ]);

        // Uses the 'api' limiter defined in AppServiceProvider.
        $middleware->throttleApi();

        $middleware->alias([
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return clean JSON for API routes when a model is not found
        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "{$model} not found.",
                ], 404);
            }
        });

        // Return clean JSON for API routes on 404
        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        // A Spatie permission/role middleware rejection on an admin page should
        // send staff back to the dashboard with an explanation, not a bare 403.
        $exceptions->render(function (
            \Spatie\Permission\Exceptions\UnauthorizedException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->is('admin/*') && ! $request->expectsJson()) {
                return redirect()->route('admin.dashboard')
                    ->with('error', 'You don\'t have permission to access that section.');
            }
        });
    })->create();
