<?php

declare(strict_types=1);

namespace App\Enums;

/** Nilai persis sama dengan roles.kode di ERD v3. Hanya enam role. */
enum RoleKode: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case AdminSatker = 'ADMIN_SATKER';
    case Kasatker = 'KASATKER';
    case Kabid = 'KABID';
    case Kasub = 'KASUB';
    case Staf = 'STAFF';

    public function nama(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminSatker => 'Admin Satker',
            self::Kasatker => 'Kepala Satker',
            self::Kabid => 'Kepala Bidang',
            self::Kasub => 'Kepala Sub Bidang',
            self::Staf => 'Staf',
        };
    }
}