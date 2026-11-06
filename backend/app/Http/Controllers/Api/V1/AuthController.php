<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FirebaseLoginRequest;
use App\Http\Requests\PreflightRegisterRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LupaKataSandiRequest;
use App\Http\Resources\PenggunaResource;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LoginService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const RELASI = [
        'pegawai',
        'penugasanAktif.role',
        'penugasanAktif.unit',
    ];

    /**
     * Authenticate with Firebase, then establish a local Sanctum session.
     */
    public function login(
        FirebaseLoginRequest $request,
        LoginService $login
    ): JsonResponse {
        if (! $request->hasSession()) {
            return response()->json([
                'message' => 'Permintaan harus berasal dari aplikasi SIMRIS.',
            ], 400);
        }

        $user = $login->masuk(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('captcha_token'),
            (string) $request->ip(),
        );

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return (new PenggunaResource(
            $user->load(self::RELASI)
        ))->response();
    }

    /**
     * Memulihkan kata sandi lokal setelah email diverifikasi oleh Firebase.
     */
    public function lupaKataSandi(
        LupaKataSandiRequest $request,
        LoginService $login
    ): JsonResponse {
        $login->lupaKataSandi($request->validated(), (string) $request->ip());

        return response()->json([
            'message' => 'Jika email terdaftar, Firebase akan mengirim tautan untuk mengatur ulang kata sandi.',
        ], 202);
    }

    public function register(RegisterRequest $request, LoginService $login): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Permintaan harus berasal dari aplikasi SIMRIS.'], 400);
        }
        $user = $login->daftar($request->validated(), (string) $request->ip());

        return response()->json([
            'message' => 'Email terverifikasi dan akun berhasil dibuat. Silakan masuk untuk mengajukan role.',
            'data' => ['id' => $user->id, 'aktif' => true],
        ], 201);
    }

    public function preflightRegister(PreflightRegisterRequest $request, LoginService $login): JsonResponse
    {
        $login->validasiPendaftaran(
            $request->validated('nip'),
            $request->validated('nama_lengkap'),
        );

        return response()->json(['message' => 'Data pegawai valid untuk pendaftaran.']);
    }

    /**
     * Mengambil informasi pengguna yang sedang login.
     *
     * @throws AuthenticationException
     */
    public function me(Request $request): PenggunaResource
    {
        return new PenggunaResource(
            $request->user()->load(self::RELASI)
        );
    }

    /**
     * Memperbarui profil pengguna.
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function updateProfile(
        Request $request,
        AuditService $audit
    ): PenggunaResource {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email_kontak' => ['required', 'email', 'max:255'],
            'nip' => ['nullable', 'string', 'regex:/^[0-9]{18}$/'],
            'jenis_kepegawaian' => ['nullable', 'string', 'max:255'],
        ]);

        $updatedUser = DB::transaction(
            function () use ($request, $validated, $audit): User {
                $user = User::query()
                    ->whereKey($request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $pegawai = $user->pegawai_id
                    ? Pegawai::query()
                        ->whereKey($user->pegawai_id)
                        ->lockForUpdate()
                        ->first()
                    : null;

                if ($pegawai) {
                    $pegawai->update([
                        'nama' => $validated['nama'],
                        'email_kontak' => $validated['email_kontak'],
                        'nip' => $validated['nip'] ?? $pegawai->nip,
                        'jenis_kepegawaian' =>
                            $validated['jenis_kepegawaian']
                            ?? $pegawai->jenis_kepegawaian,
                    ]);
                } else {
                    $pegawai = Pegawai::create([
                        'nama' => $validated['nama'],
                        'email_kontak' => $validated['email_kontak'],
                        'nip' => $validated['nip'] ?? null,
                        'jenis_kepegawaian' =>
                            $validated['jenis_kepegawaian'] ?? 'PNS',
                        'aktif' => true,
                    ]);

                    $user->pegawai_id = $pegawai->id;
                    $user->save();
                }

                $audit->record(
                    'USERS',
                    $user->id,
                    'PROFILE_UPDATE',
                    null,
                    $validated
                );

                return $user;
            }
        );

        return new PenggunaResource(
            $updatedUser->load(self::RELASI)
        );
    }

    /**
     * Logout dan mengakhiri session pengguna.
     *
     * @throws AuthenticationException
     */
    public function logout(
        Request $request,
        AuditService $audit
    ): Response {
        DB::transaction(
            fn () => $audit->record(
                'USERS',
                $request->user()->id,
                'LOGOUT'
            )
        );

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
