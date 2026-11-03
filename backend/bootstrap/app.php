<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePasswordConfirmation;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Autentikasi SPA React menggunakan cookie dan Sanctum.
        $middleware->statefulApi();

        // Security headers untuk seluruh request.
        $middleware->append(SecurityHeaders::class);

        // Alias middleware.
        $middleware->alias([
            'aktif' => EnsureUserIsActive::class,
            'account.active' => EnsureActiveAccount::class,
            'password.confirmed' => RequirePasswordConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API dan endpoint autentikasi selalu memberikan respons JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*', 'login', 'logout', 'daftar')
                || $request->expectsJson(),
        );

        // Respons ketika pengguna belum terautentikasi.
        $exceptions->render(
            function (
                AuthenticationException $exception,
                Request $request
            ) {
                return response()->json([
                    'message' => 'Silakan masuk terlebih dahulu.',
                ], 401);
            }
        );

        // Respons ketika validasi gagal.
        $exceptions->render(
            function (
                ValidationException $exception,
                Request $request
            ) {
                return response()->json([
                    'message' => 'Data yang dikirim tidak valid.',
                    'errors' => $exception->errors(),
                ], 422);
            }
        );
    })
    ->create();
