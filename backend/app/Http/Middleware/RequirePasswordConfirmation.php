<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmed = $request->hasSession() ? (int) $request->session()->get('auth.password_confirmed_at', 0) : 0;
        if ($confirmed <= 0 || now()->timestamp - $confirmed > config('security.password_confirmation_seconds')) {
            return response()->json(['message' => 'Konfirmasi ulang password untuk tindakan ini.', 'code' => 'konfirmasi_password_diperlukan'], 403);
        }

        return $next($request);
    }
}
