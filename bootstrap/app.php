<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Session\TokenMismatchException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Trust the reverse proxy in front of the app container so
        // request()->isSecure() reflects the original HTTPS request.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function ($exceptions) {
        $exceptions->render(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has expired. Please refresh the page.'], 419);
            }

            return redirect('/login');
        });
    })
    ->create();
