<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleKode;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Satu-satunya jalan membuat SUPER_ADMIN. Hanya boleh berhasil sekali:
 * tidak ada UI atau API untuk membuat, memberikan, atau mengubah role ini.
 */
class BuatSuperAdmin extends Command
{
    protected $signature = 'simris:buat-super-admin
        {email : Email login (harus sama dengan akun di Firebase)}
        {--username=superadmin : Username}';

    protected $description = 'Membuat satu-satunya akun SUPER_ADMIN (hanya bisa dijalankan sekali)';

    public function handle(AuditService $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $username = trim((string) $this->option('username'));

        $validator = Validator::make(
            ['email' => $email, 'username' => $username],
            [
                'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email_login'],
                'username' => ['required', 'alpha_dash', 'max:255', 'unique:users,username'],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        $hasil = DB::transaction(function () use ($email, $username, $audit): ?int {
            $role = Role::query()->where('kode', RoleKode::SuperAdmin->value)->lockForUpdate()->first();
            if ($role === null) {
                $this->error('Role belum ada. Jalankan: php artisan db:seed --class=RoleSeeder');

                return null;
            }

            // Cek seluruh riwayat penugasan (aktif atau sudah selesai): SUPER_ADMIN hanya satu seumur sistem.
            if (UserRoleScope::query()->where('role_id', $role->id)->exists()) {
                $this->error('Akun SUPER_ADMIN sudah ada. Role ini tidak bisa dibuat atau diberikan lagi.');

                return null;
            }

            $user = new User;
            $user->username = $username;
            $user->email_login = $email;
            $user->password_hash = Hash::make(Str::random(64)); // tidak dipakai; kredensial ada di Firebase
            $user->aktif = true;
            $user->save();

            $penugasan = new UserRoleScope;
            $penugasan->user_id = $user->id;
            $penugasan->role_id = $role->id;
            $penugasan->unit_id = null; // NULL hanya untuk SUPER_ADMIN
            $penugasan->mulai = now();
            $penugasan->save();

            $audit->record('users', $user->id, 'CREATE', null, [
                'username' => $username,
                'email_login' => $email,
                'role' => RoleKode::SuperAdmin->value,
            ]);

            return $user->id;
        });

        if ($hasil === null) {
            return self::FAILURE;
        }

        $this->info("Akun SUPER_ADMIN dibuat (id {$hasil}) untuk {$email}.");
        $this->line('Langkah berikutnya:');
        $this->line('  1. Buat akun dengan email yang sama di Firebase Console (Authentication > Users).');
        $this->line('  2. Buka halaman login, pilih "Lupa kata sandi", lalu atur kata sandi dari email yang masuk.');

        return self::SUCCESS;
    }
}
