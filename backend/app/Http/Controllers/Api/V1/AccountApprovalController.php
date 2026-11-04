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

        $submission = DB::table('PENGAJUAN_ROLE as a')
            ->leftJoin('UNIT_ORGANISASI as un', 'un.id', '=', 'a.unit_id')
            ->where('a.user_id', $request->user()->id)
            ->orderByDesc('a.id')
            ->select('a.*', 'un.nama as unit_nama')
            ->first();

        return response()->json(['data' => [
            'has_role' => $hasRole,
            'pengajuan' => $submission,
        ]]);
    }

    public function unitOptions(Request $request)
    {
        $role = $request->query('role');
        $employeeId = $request->user()->pegawai_id;
        $placement = $employeeId ? DB::table('PENEMPATAN_PEGAWAI')
            ->where('pegawai_id', $employeeId)
            ->where('utama', true)
            ->where('mulai', '<=', today())
            ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))
            ->first() : null;
        $units = collect();
        if ($placement && $role === 'KASATKER') {
            $units = DB::table('UNIT_ORGANISASI')->where('id', $placement->unit_id)->where('jenis', 'SATKER')->where('aktif', true)->get(['id', 'nama', 'jenis', 'parent_id', 'kode']);
        } elseif ($placement && $role === 'KABID') {
            $units = DB::table('UNIT_ORGANISASI')->where('parent_id', $placement->unit_id)->where('jenis', 'BIDANG')->where('aktif', true)->orderBy('kode')->get(['id', 'nama', 'jenis', 'parent_id', 'kode']);
        } elseif ($placement && $role === 'KASUB') {
            $units = DB::table('UNIT_ORGANISASI as sub')
                ->join('UNIT_ORGANISASI as bidang', 'bidang.id', '=', 'sub.parent_id')
                ->where('bidang.parent_id', $placement->unit_id)
                ->where('sub.jenis', 'SUBBIDANG')->where('sub.aktif', true)
                ->orderBy('sub.kode')
                ->get(['sub.id', 'sub.nama', 'sub.jenis', 'sub.parent_id', 'sub.kode']);
        } elseif ($placement && $role === 'STAFF') {
            $units = DB::table('UNIT_ORGANISASI')->where('id', $placement->unit_id)->get(['id', 'nama', 'jenis', 'parent_id', 'kode']);
        }

        return response()->json(['data' => $units]);
    }

    public function submit(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'role_kode' => ['required', Rule::in(self::ROLE_DIAJUKAN)],
            'unit_id' => ['required', 'integer', Rule::exists('UNIT_ORGANISASI', 'id')],
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
        $selectedUnit = DB::table('UNIT_ORGANISASI')->where('id', $data['unit_id'])->first();
        $expectedKind = match ($data['role_kode']) {
            'KASATKER' => 'SATKER',
            'KABID' => 'BIDANG',
            'KASUB' => 'SUBBIDANG',
            default => null,
        };
        if ($expectedKind !== null && (! $selectedUnit || $selectedUnit->jenis !== $expectedKind)) {
            throw ValidationException::withMessages(['unit_id' => 'Unit yang dipilih tidak sesuai dengan role yang diajukan.']);
        }
        if ($expectedKind === null) {
            $data['unit_id'] = $placement->unit_id;
        }
        if ($expectedKind === 'BIDANG' && (int) $selectedUnit->parent_id !== (int) $placement->unit_id) {
            throw ValidationException::withMessages(['unit_id' => 'Bidang harus berada di bawah Satker penempatan aktif Anda.']);
        }
        if ($expectedKind === 'SUBBIDANG') {
            $parent = DB::table('UNIT_ORGANISASI')->where('id', $selectedUnit->parent_id)->first();
            $satkerId = $parent && $parent->jenis === 'SATKER'
                ? $parent->id
                : ($parent ? DB::table('UNIT_ORGANISASI')->where('id', $parent->parent_id)->where('jenis', 'SATKER')->value('id') : null);
            if (! $satkerId || (int) $satkerId !== (int) $placement->unit_id) {
                throw ValidationException::withMessages(['unit_id' => 'Subbidang harus berada di bawah Satker penempatan aktif Anda.']);
            }
        }
        if ($expectedKind === 'SATKER' && (int) $selectedUnit->id !== (int) $placement->unit_id) {
            throw ValidationException::withMessages(['unit_id' => 'Satker yang dipilih harus sesuai dengan penempatan aktif Anda.']);
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
            'unit_id' => $data['unit_id'],
            'role_kode' => $data['role_kode'],
            'informasi_pendukung' => trim($data['informasi_pendukung']),
            'status' => 'MENUNGGU',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $audit->record('PENGAJUAN_ROLE', $id, 'CREATE', null, [
            'user_id' => $user->id,
            'unit_id' => $data['unit_id'],
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
