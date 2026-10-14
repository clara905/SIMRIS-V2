<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ScopeUnit;
use App\Services\TotpVerifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request, AuditService $audit, ScopeUnit $scope, TotpVerifier $totp): MeResource|JsonResponse
    {
        $key = 'login:'.hash('sha256', strtolower($request->string('login')->toString()).'|'.$request->ip());
        $ipKey = 'login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, config('security.login_attempts')) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return response()->json(['message' => 'Terlalu banyak percobaan. Coba lagi setelah penguncian berakhir.', 'code' => 'login_terkunci'], 429)->header('Retry-After', (string) max(RateLimiter::availableIn($key), RateLimiter::availableIn($ipKey)));
        }
        $user = DB::transaction(function () use ($request, $audit, $scope, $totp): ?User {
            $user = User::where(fn ($q) => $q->where('username', $request->input('login'))->orWhere('email_login', $request->input('login')))->lockForUpdate()->first();
            $valid = Hash::check($request->input('password'), $user?->password_hash ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (! $user || ! $valid || ! $user->aktif || (config('security.super_admin_totp') && $scope->isSuper($user) && ! $totp->verify($user, $request->input('totp')))) {
                $audit->record('USERS', $user?->id ?? 0, 'LOGIN_FAILED');

                return null;
            }
            $audit->record('USERS', $user->id, 'LOGIN', null, null, $user->id);

            return $user;
        });
        if (! $user) {
            RateLimiter::hit($key, config('security.login_lock_seconds'));
            RateLimiter::hit($ipKey, config('security.login_lock_seconds'));

            return response()->json(['message' => 'Kredensial tidak valid atau akun tidak dapat digunakan.'], 401);
        }
        RateLimiter::clear($key);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->password_hash);
        $request->session()->forget('auth.password_confirmed_at');

        return new MeResource($user);
    }

    /**
     * @throws AuthenticationException
     */
    public function me(Request $request): MeResource
    {
        return new MeResource($request->user());
    }

    /**
     * @throws AuthenticationException
     */
    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        DB::transaction(fn () => $audit->record('USERS', $request->user()->id, 'LOGOUT'));
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function password(PasswordRequest $request, AuditService $audit): JsonResponse
    {
        $user = DB::transaction(function () use ($request, $audit): User {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($request->input('current_password'), $user->password_hash)) {
                throw ValidationException::withMessages(['current_password' => ['Password saat ini tidak sesuai.']]);
            }
            $user->update(['password_hash' => $request->input('password'), 'wajib_ganti_password' => false]);
            $audit->record('USERS', $user->id, 'PASSWORD_CHANGE');

            return $user;
        });
        Auth::guard('web')->setUser($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->forget('auth.password_confirmed_at');
            $request->session()->put('password_hash_web', $user->password_hash);
        }

        return response()->json(['message' => 'Password berhasil diganti.']);
    }

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function confirm(ConfirmPasswordRequest $request): JsonResponse
    {
        if (! Hash::check($request->input('password'), $request->user()->password_hash)) {
            throw ValidationException::withMessages(['password' => ['Password tidak sesuai.']]);
        }
        $request->session()->put('auth.password_confirmed_at', now()->timestamp);

        return response()->json(['message' => 'Password terkonfirmasi.']);
    }
}
