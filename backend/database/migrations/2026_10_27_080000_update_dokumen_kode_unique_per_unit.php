<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('DOKUMEN', function (Blueprint $table): void {
            $table->dropUnique(['kode']);
            $table->unique(['unit_pemilik_id', 'kode'], 'dokumen_uk0');
        });
    }

    public function down(): void
    {
        Schema::table('DOKUMEN', function (Blueprint $table): void {
            $table->dropUnique('dokumen_uk0');
            $table->unique('kode');
        });
    }
};
