<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('VERSI_METODE_RISIKO', function (Blueprint $table): void {
            $table->boolean('profil_penilaian')->default(false);
        });

        Schema::table('SKALA_DAMPAK', function (Blueprint $table): void {
            $table->text('kriteria_kerahasiaan')->nullable();
            $table->text('kriteria_integritas')->nullable();
            $table->text('kriteria_ketersediaan')->nullable();
            $table->text('kriteria_kepatuhan')->nullable();
            $table->text('kriteria_operasional')->nullable();
        });

        Schema::table('TINGKAT_RISIKO', function (Blueprint $table): void {
            $table->unsignedSmallInteger('batas_min_skor')->nullable();
            $table->unsignedSmallInteger('batas_max_skor')->nullable();
            $table->boolean('perlu_rtp')->nullable();
            $table->boolean('perlu_persetujuan_pemilik')->nullable();
            $table->text('kriteria_penerimaan')->nullable();
        });

        Schema::table('RISIKO', function (Blueprint $table): void {
            $table->string('konteks', 30)->nullable();
            $table->text('konsekuensi_kerahasiaan')->nullable();
            $table->text('konsekuensi_integritas')->nullable();
            $table->text('konsekuensi_ketersediaan')->nullable();
            $table->text('konsekuensi_kepatuhan')->nullable();
            $table->text('konsekuensi_operasional')->nullable();
        });

        Schema::table('PENILAIAN_RISIKO', function (Blueprint $table): void {
            $table->text('alasan_perubahan_level')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('PENILAIAN_RISIKO', function (Blueprint $table): void {
            $table->dropColumn('alasan_perubahan_level');
        });

        Schema::table('RISIKO', function (Blueprint $table): void {
            $table->dropColumn([
                'konteks',
                'konsekuensi_kerahasiaan',
                'konsekuensi_integritas',
                'konsekuensi_ketersediaan',
                'konsekuensi_kepatuhan',
                'konsekuensi_operasional',
            ]);
        });

        Schema::table('TINGKAT_RISIKO', function (Blueprint $table): void {
            $table->dropColumn([
                'batas_min_skor',
                'batas_max_skor',
                'perlu_rtp',
                'perlu_persetujuan_pemilik',
                'kriteria_penerimaan',
            ]);
        });

        Schema::table('SKALA_DAMPAK', function (Blueprint $table): void {
            $table->dropColumn([
                'kriteria_kerahasiaan',
                'kriteria_integritas',
                'kriteria_ketersediaan',
                'kriteria_kepatuhan',
                'kriteria_operasional',
            ]);
        });

        Schema::table('VERSI_METODE_RISIKO', function (Blueprint $table): void {
            $table->dropColumn('profil_penilaian');
        });
    }
};
