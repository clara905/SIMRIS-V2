<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\CaptchaVerifier;
use App\Contracts\FirebaseAuthGateway;
use App\Enums\RoleKode;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\RoleSeeder;

trait MembuatPengguna
{
    protected FakeFirebaseAuthGateway $firebase;

    protected FakeCaptchaVerifier $captcha;

    protected function siapkan(): void
    {
        $this->seed(RoleSeeder::class);

        $this->firebase = new FakeFirebaseAuthGateway();
        $this->captcha = new FakeCaptchaVerifier();
        $this->app->instance(FirebaseAuthGateway::class, $this->firebase);
        $this->app->instance(CaptchaVerifier::class, $this->captcha);
    }

    /**
     * Membuat user lengkap (unit, pegawai, penugasan) dan mendaftarkannya di Firebase palsu.
     *
     * @param array{email?: string, aktif?: bool, pegawai_aktif?: bool, role?: RoleKode, mulai?: \DateTimeInterface, selesai?: ?\DateTimeInterface, verified?: bool} $o
     */
    protected function buatPengguna(array $o = []): User
    {
        $email = $o['email'] ?? 'budi@simris.test';
        $role = $o['role'] ?? RoleKode::Staf;

        $unit = UnitOrganisasi::query()->where('kode', 'SAT01')->first()
            ?? UnitOrganisasi::forceCreate(['parent_id' => null, 'kode' => 'SAT01', 'nama' => 'Satker Satu', 'jenis' => 'SATKER', 'aktif' => true]);

        $pegawai = Pegawai::forceCreate([
            'nip' => (string) random_int(100000000, 999999999),
            'nama' => 'Budi Santoso',
            'email_kontak' => $email,
            'jenis_kepegawaian' => 'PNS',
            'aktif' => $o['pegawai_aktif'] ?? true,
        ]);

        $user = new User();
        $user->pegawai_id = $pegawai->id;
        $user->username = explode('@', $email)[0];
        $user->email_login = $email;
        $user->password_hash = 'tidak-dipakai';
        $user->aktif = $o['aktif'] ?? true;
        $user->save();

        $penugasan = new UserRoleScope();
        $penugasan->user_id = $user->id;
        $penugasan->role_id = Role::query()->where('kode', $role->value)->value('id');
        $penugasan->unit_id = $role === RoleKode::SuperAdmin ? null : $unit->id;
        $penugasan->mulai = $o['mulai'] ?? now()->subDay();
        $penugasan->selesai = $o['selesai'] ?? null;
        $penugasan->save();

        $this->firebase->tambah($email, 'Rahasia#123', $o['verified'] ?? true);

        return $user;
    }
}