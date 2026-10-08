<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Handler khusus AuthenticationException — default Laravel mencoba
        // redirect(route('login')) yang crash 500 karena route `login` tidak
        // ada (SPA). Untuk request /api/* balikkan 401 JSON; untuk browser
        // yang membuka URL langsung (mis. admin klik "Lihat Dokumen" di tab
        // baru), redirect ke halaman login frontend.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // FRONTEND_URL bisa berisi beberapa URL comma-separated untuk multi-origin CORS;
            // ambil yang pertama saja untuk redirect.
            $frontend = rtrim(explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000'))[0], '/');

            return redirect($frontend.'/login');
        });
    })->create();
