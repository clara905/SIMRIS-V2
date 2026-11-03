<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use Auditable, SerializesLocalDates;

    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $table = 'ROLES';

    protected $fillable = [
        'kode',
        'nama',
    ];
}
