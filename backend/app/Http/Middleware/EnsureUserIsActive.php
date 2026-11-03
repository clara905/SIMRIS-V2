<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Menolak (dan mengeluarkan) sesi milik akun yang dinonaktifkan setelah login. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->aktif) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['message' => 'Akun Anda dinonaktifkan. Hubungi admin satker Anda.'], 403);
        }

        return $next($request);
    }
}