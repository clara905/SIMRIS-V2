<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\ScopeUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountApprovalController extends Controller
{
    private const ROLE_DIAJUKAN = ['STAFF', 'KASUB', 'KABID', 'KASATKER'];

    public function status(Request $request)
    {
        $hasRole = DB::table('USER_ROLE_SCOPE as s')
            ->join('ROLES as r', 'r.id', '=', 's.role_id')
            ->where('s.user_id', $request->user()->id)
            ->where('s.mulai', '<=', now())
            ->where(fn ($q) => $q->whereNull('s.selesai')->orWhere('s.selesai', '>=', now()))
            ->exists();

        $submission = DB::table('PENGAJUAN_ROLE')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->first();

        return response()->json(['data' => [
            'has_role' => $hasRole,
            'pengajuan' => $submission,
        ]]);
    }

    public function submit(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'role_kode' => ['required', Rule::in(self::ROLE_DIAJUKAN)],
            'informasi_pendukung' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $userId = $request->user()->id;

        return DB::transaction(function () use ($userId, $data, $audit) {
        $user = DB::table('USERS')->where('id', $userId)->lockForUpdate()->first();
        if (! $user || ! $user->pegawai_id) {
            throw ValidationException::withMessages(['pegawai' => 'Akun belum terhubung dengan data pegawai.']);
        }

        $placement = DB::table('PENEMPATAN_PEGAWAI')
            ->where('pegawai_id', $user->pegawai_id)
            ->where('utama', true)
            ->where('mulai', '<=', today())
            ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))
            ->first();
        if (! $placement) {
            throw ValidationException::withMessages(['pegawai' => 'Pegawai belum memiliki penempatan utama yang aktif.']);
        }

        $hasRole = DB::table('USER_ROLE_SCOPE')
            ->where('user_id', $user->id)
            ->where('mulai', '<=', now())
            ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', now()))
            ->exists();
        if ($hasRole) {
            throw ValidationException::withMessages(['role' => 'Akun sudah memiliki role aktif.']);
        }

        $pending = DB::table('PENGAJUAN_ROLE')
            ->where('user_id', $user->id)
            ->where('status', 'MENUNGGU')
            ->exists();
        if ($pending) {
            throw ValidationException::withMessages(['pengajuan' => 'Pengajuan role Anda sedang ditinjau.']);
        }

        $id = DB::table('PENGAJUAN_ROLE')->insertGetId([
            'user_id' => $user->id,
            'unit_id' => $placement->unit_id,
            'role_kode' => $data['role_kode'],
            'informasi_pendukung' => trim($data['informasi_pendukung']),
            'status' => 'MENUNGGU',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $audit->record('PENGAJUAN_ROLE', $id, 'CREATE', null, [
            'user_id' => $user->id,
            'unit_id' => $placement->unit_id,
            'role_kode' => $data['role_kode'],
        ]);

        return response()->json(['message' => 'Pengajuan role berhasil dikirim.', 'data' => ['id' => $id]], 201);
        });
    }

    public function index(Request $request, ScopeUnit $scope)
    {
        abort_unless($scope->hasRole($request->user(), ['ADMIN_SATKER']), 403);
        $units = $scope->idsForRoles($request->user(), ['ADMIN_SATKER']);

        return response()->json(['data' => DB::table('PENGAJUAN_ROLE as a')
            ->join('USERS as u', 'u.id', '=', 'a.user_id')
            ->join('PEGAWAI as p', 'p.id', '=', 'u.pegawai_id')
            ->join('UNIT_ORGANISASI as un', 'un.id', '=', 'a.unit_id')
            ->where('a.status', 'MENUNGGU')
            ->whereIn('a.unit_id', $units)
            ->select('a.id', 'a.role_kode', 'a.informasi_pendukung', 'a.created_at', 'u.id as user_id', 'p.nama', 'p.nip', 'un.nama as unit_nama', 'a.unit_id')
            ->orderBy('a.created_at')
            ->get()]);
    }

    public function approve(Request $request, int $id, ScopeUnit $scope, AuditService $audit)
    {
        abort_unless($scope->hasRole($request->user(), ['ADMIN_SATKER']), 403);

        return DB::transaction(function () use ($request, $id, $scope, $audit) {
            $application = DB::table('PENGAJUAN_ROLE')->where('id', $id)->where('status', 'MENUNGGU')->lockForUpdate()->first();
            $units = $scope->idsForRoles($request->user(), ['ADMIN_SATKER']);
            if (! $application || ! in_array((int) $application->unit_id, $units, true)) {
                throw ValidationException::withMessages(['pengajuan' => 'Pengajuan tidak tersedia untuk ditinjau.']);
            }

            $user = DB::table('USERS')->where('id', $application->user_id)->lockForUpdate()->first();
            if (! $user || ! $user->aktif || $user->id == $request->user()->id) {
                throw ValidationException::withMessages(['akun' => 'Akun tidak dapat diberi role ini.']);
            }

            $role = DB::table('ROLES')->where('kode', $application->role_kode)->first();
            if (! $role || ! in_array($application->role_kode, self::ROLE_DIAJUKAN, true)) {
                throw ValidationException::withMessages(['role' => 'Role yang diajukan tidak dapat diberikan.']);
            }

            $now = now();
            DB::table('USER_ROLE_SCOPE')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'unit_id' => $application->unit_id,
                'mulai' => $now,
                'selesai' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('USERS')->where('id', $user->id)->update(['updated_at' => $now]);
            DB::table('PENGAJUAN_ROLE')->where('id', $id)->update([
                'status' => 'DISETUJUI',
                'reviewer_id' => $request->user()->id,
                'ditinjau_pada' => $now,
                'updated_at' => $now,
            ]);
            $audit->record('PENGAJUAN_ROLE', $id, 'APPROVE', ['status' => 'MENUNGGU'], ['role_kode' => $application->role_kode, 'unit_id' => $application->unit_id]);

            return response()->json(['message' => 'Pengajuan role disetujui.']);
        });
    }

    public function reject(Request $request, int $id, ScopeUnit $scope, AuditService $audit)
    {
        abort_unless($scope->hasRole($request->user(), ['ADMIN_SATKER']), 403);
        $data = $request->validate(['alasan_penolakan' => ['required', 'string', 'min:5', 'max:1000']]);
        $units = $scope->idsForRoles($request->user(), ['ADMIN_SATKER']);

        return DB::transaction(function () use ($request, $id, $units, $data, $audit) {
            $application = DB::table('PENGAJUAN_ROLE')
                ->where('id', $id)
                ->where('status', 'MENUNGGU')
                ->whereIn('unit_id', $units)
                ->lockForUpdate()
                ->first();
            if (! $application) {
                throw ValidationException::withMessages(['pengajuan' => 'Pengajuan tidak tersedia untuk ditinjau.']);
            }

            $reason = trim($data['alasan_penolakan']);
            $now = now();
            DB::table('PENGAJUAN_ROLE')->where('id', $id)->update([
                'status' => 'DITOLAK',
                'alasan_penolakan' => $reason,
                'reviewer_id' => $request->user()->id,
                'ditinjau_pada' => $now,
                'updated_at' => $now,
            ]);
            $audit->record('PENGAJUAN_ROLE', $id, 'UPDATE', ['status' => 'MENUNGGU'], [
                'status' => 'DITOLAK',
                'alasan_penolakan' => $reason,
            ]);

            return response()->json(['message' => 'Pengajuan role ditolak.']);
        });
    }
}
