<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->aktif) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            abort(401, 'Akun tidak aktif atau sesi telah berakhir.');
        }
        if ($request->user()->wajib_ganti_password && ! $request->routeIs('auth.me', 'auth.password', 'auth.logout')) {
            return response()->json(['message' => 'Ganti password terlebih dahulu.', 'code' => 'wajib_ganti_password'], 403);
        }

        return $next($request);
    }
}
