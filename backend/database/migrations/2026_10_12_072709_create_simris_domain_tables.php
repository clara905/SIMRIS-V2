<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') || Schema::hasTable('USERS')) {
            throw new RuntimeException('Skema SIMRIS memerlukan database kosong; users lama harus dimigrasikan secara terencana.');
        }

        DB::statement('DROP VIEW IF EXISTS v_pegawai_unit');

        Schema::create('UNIT_ORGANISASI', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('jenis');
            $table->boolean('aktif')->default(true);
            $table->foreign('parent_id', 'unit_organisasi_parent_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('PEGAWAI', function (Blueprint $table): void {
            $table->id();
            $table->string('nip')->nullable()->unique();
            $table->string('nama');
            $table->string('email_kontak');
            $table->string('jenis_kepegawaian');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('PENEMPATAN_PEGAWAI', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pegawai_id');
            $table->unsignedBigInteger('unit_id');
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->boolean('utama')->default(true);
            $expression = DB::getDriverName() === 'mysql'
                ? 'IF(utama = 1 AND selesai IS NULL, pegawai_id, NULL)'
                : 'CASE WHEN utama = 1 AND selesai IS NULL THEN pegawai_id ELSE NULL END';
            $table->unsignedBigInteger('kunci_utama_aktif')->nullable()->storedAs($expression)->unique();
            $table->foreign('pegawai_id', 'penempatan_pegawai_pegawai_id_fk')->references('id')->on('PEGAWAI')->restrictOnDelete();
            $table->foreign('unit_id', 'penempatan_pegawai_unit_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('USERS', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pegawai_id')->nullable()->unique();
            $table->string('username')->unique();
            $table->string('email_login')->unique();
            $table->string('password_hash');
            $table->boolean('aktif')->default(true);
            $table->boolean('wajib_ganti_password')->default(false);
            $table->foreign('pegawai_id', 'users_pegawai_id_fk')->references('id')->on('PEGAWAI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ROLES', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('USER_ROLE_SCOPE', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->dateTime('mulai');
            $table->dateTime('selesai')->nullable();
            $table->foreign('user_id', 'user_role_scope_user_id_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->foreign('role_id', 'user_role_scope_role_id_fk')->references('id')->on('ROLES')->restrictOnDelete();
            $table->foreign('unit_id', 'user_role_scope_unit_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('LOG_AUDIT', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('entitas');
            $table->unsignedBigInteger('entitas_id');
            $table->string('aksi');
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->string('ip_address');
            $table->dateTime('waktu');
            $table->foreign('user_id', 'log_audit_user_id_fk')->references('id')->on('USERS')->restrictOnDelete();
        });

        Schema::create('SUMBER_ASET', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('KATEGORI_ASET', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->foreign('parent_id', 'kategori_aset_parent_id_fk')->references('id')->on('KATEGORI_ASET')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('KLASIFIKASI_INFORMASI', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi');
            $table->timestamps();
        });

        Schema::create('ASET', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sumber_id');
            $table->string('external_id');
            $table->unsignedBigInteger('unit_pemilik_id');
            $table->unsignedBigInteger('kategori_id');
            $table->unsignedBigInteger('klasifikasi_informasi_id')->nullable();
            $table->string('kode_aset')->unique();
            $table->string('nama');
            $table->text('deskripsi');
            $table->string('merk')->nullable();
            $table->string('nomor_seri')->nullable();
            $table->integer('jumlah');
            $table->string('kondisi');
            $table->string('lokasi');
            $table->date('tanggal_pengadaan')->nullable();
            $table->string('status');
            $table->dateTime('last_synced_at');
            $table->string('nomor_inventaris')->nullable();
            $table->string('nama_pengadaan')->nullable();
            $table->unsignedSmallInteger('tahun_pengadaan')->nullable();
            $table->text('keterangan')->nullable();
            $table->json('data_mentah');
            $table->char('hash_data', 64);
            $table->boolean('perlu_ditinjau')->default(true);
            $table->unique(['sumber_id', 'external_id'], 'aset_uk0');
            $table->foreign('sumber_id', 'aset_sumber_id_fk')->references('id')->on('SUMBER_ASET')->restrictOnDelete();
            $table->foreign('unit_pemilik_id', 'aset_unit_pemilik_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->foreign('kategori_id', 'aset_kategori_id_fk')->references('id')->on('KATEGORI_ASET')->restrictOnDelete();
            $table->foreign('klasifikasi_informasi_id', 'aset_klasifikasi_informasi_id_fk')->references('id')->on('KLASIFIKASI_INFORMASI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('PENUGASAN_ASET', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('aset_id');
            $table->unsignedBigInteger('pegawai_id');
            $table->string('jenis_tanggung_jawab');
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->foreign('aset_id', 'penugasan_aset_aset_id_fk')->references('id')->on('ASET')->restrictOnDelete();
            $table->foreign('pegawai_id', 'penugasan_aset_pegawai_id_fk')->references('id')->on('PEGAWAI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('PERIODE_PENILAIAN', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->timestamps();
        });

        Schema::create('VERSI_METODE_RISIKO', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('versi');
            $table->text('referensi');
            $table->boolean('dibekukan')->default(false);
            $table->timestamps();
        });

        Schema::create('SKALA_KEMUNGKINAN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('versi_metode_id');
            $table->integer('nilai');
            $table->string('label');
            $table->string('label_persentase');
            $table->string('label_frekuensi');
            $table->text('kriteria');
            $table->unique(['versi_metode_id', 'nilai'], 'skala_kemungkinan_uk0');
            $table->foreign('versi_metode_id', 'skala_kemungkinan_versi_metode_id_fk')->references('id')->on('VERSI_METODE_RISIKO')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SKALA_DAMPAK', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('versi_metode_id');
            $table->integer('nilai');
            $table->string('label');
            $table->text('kriteria');
            $table->unique(['versi_metode_id', 'nilai'], 'skala_dampak_uk0');
            $table->foreign('versi_metode_id', 'skala_dampak_versi_metode_id_fk')->references('id')->on('VERSI_METODE_RISIKO')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('AREA_DAMPAK', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('KRITERIA_DAMPAK', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('skala_dampak_id');
            $table->unsignedBigInteger('area_dampak_id');
            $table->string('arah_dampak');
            $table->text('deskripsi');
            $table->unique(['skala_dampak_id', 'area_dampak_id', 'arah_dampak'], 'kriteria_dampak_uk0');
            $table->foreign('skala_dampak_id', 'kriteria_dampak_skala_dampak_id_fk')->references('id')->on('SKALA_DAMPAK')->restrictOnDelete();
            $table->foreign('area_dampak_id', 'kriteria_dampak_area_dampak_id_fk')->references('id')->on('AREA_DAMPAK')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('TINGKAT_RISIKO', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('versi_metode_id');
            $table->string('kode');
            $table->string('nama');
            $table->string('warna');
            $table->unique(['versi_metode_id', 'kode'], 'tingkat_risiko_uk0');
            $table->foreign('versi_metode_id', 'tingkat_risiko_versi_metode_id_fk')->references('id')->on('VERSI_METODE_RISIKO')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('MATRIKS_RISIKO', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('versi_metode_id');
            $table->unsignedBigInteger('kemungkinan_id');
            $table->unsignedBigInteger('dampak_id');
            $table->unsignedBigInteger('tingkat_risiko_id');
            $table->integer('skor');
            $table->string('rekomendasi_keputusan');
            $table->unique(['versi_metode_id', 'kemungkinan_id', 'dampak_id'], 'matriks_risiko_uk0');
            $table->foreign('versi_metode_id', 'matriks_risiko_versi_metode_id_fk')->references('id')->on('VERSI_METODE_RISIKO')->restrictOnDelete();
            $table->foreign('kemungkinan_id', 'matriks_risiko_kemungkinan_id_fk')->references('id')->on('SKALA_KEMUNGKINAN')->restrictOnDelete();
            $table->foreign('dampak_id', 'matriks_risiko_dampak_id_fk')->references('id')->on('SKALA_DAMPAK')->restrictOnDelete();
            $table->foreign('tingkat_risiko_id', 'matriks_risiko_tingkat_risiko_id_fk')->references('id')->on('TINGKAT_RISIKO')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('JENIS_ANCAMAN', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('SUMBER_ANCAMAN', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi');
            $table->timestamps();
        });

        Schema::create('ANCAMAN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jenis_ancaman_id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi');
            $table->foreign('jenis_ancaman_id', 'ancaman_jenis_ancaman_id_fk')->references('id')->on('JENIS_ANCAMAN')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ANCAMAN_SUMBER', function (Blueprint $table): void {
            $table->unsignedBigInteger('ancaman_id');
            $table->unsignedBigInteger('sumber_ancaman_id');
            $table->primary(['ancaman_id', 'sumber_ancaman_id'], 'ancaman_sumber_pk');
            $table->foreign('ancaman_id', 'ancaman_sumber_ancaman_id_fk')->references('id')->on('ANCAMAN')->restrictOnDelete();
            $table->foreign('sumber_ancaman_id', 'ancaman_sumber_sumber_ancaman_id_fk')->references('id')->on('SUMBER_ANCAMAN')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('JENIS_KERENTANAN', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('KERENTANAN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jenis_kerentanan_id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi');
            $table->foreign('jenis_kerentanan_id', 'kerentanan_jenis_kerentanan_id_fk')->references('id')->on('JENIS_KERENTANAN')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RENCANA_KERJA', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->text('uraian_template');
            $table->timestamps();
        });

        Schema::create('STANDAR_KONTROL', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('versi');
            $table->text('referensi');
            $table->timestamps();
        });

        Schema::create('KONTROL', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('standar_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('jenis');
            $table->text('deskripsi');
            $table->foreign('standar_id', 'kontrol_standar_id_fk')->references('id')->on('STANDAR_KONTROL')->restrictOnDelete();
            $table->foreign('parent_id', 'kontrol_parent_id_fk')->references('id')->on('KONTROL')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('REGULASI', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('referensi');
            $table->timestamps();
        });

        Schema::create('KONTROL_INTERNAL', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('unit_pemilik_id');
            $table->string('kode');
            $table->string('nama');
            $table->string('jenis');
            $table->text('deskripsi');
            $table->boolean('aktif')->default(true);
            $table->foreign('unit_pemilik_id', 'kontrol_internal_unit_pemilik_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('KONTROL_INTERNAL_STANDAR', function (Blueprint $table): void {
            $table->unsignedBigInteger('kontrol_internal_id');
            $table->unsignedBigInteger('kontrol_id');
            $table->text('keterangan');
            $table->primary(['kontrol_internal_id', 'kontrol_id'], 'kontrol_internal_standar_pk');
            $table->foreign('kontrol_internal_id', 'kontrol_internal_standar_kontrol_internal_id_fk')->references('id')->on('KONTROL_INTERNAL')->restrictOnDelete();
            $table->foreign('kontrol_id', 'kontrol_internal_standar_kontrol_id_fk')->references('id')->on('KONTROL')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('DOKUMEN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('unit_pemilik_id');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->string('kode')->unique();
            $table->string('judul');
            $table->string('jenis');
            $table->dateTime('dibuat_pada');
            $table->foreign('unit_pemilik_id', 'dokumen_unit_pemilik_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->foreign('dibuat_oleh', 'dokumen_dibuat_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOP', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('unit_pemilik_id');
            $table->string('kode');
            $table->string('judul');
            $table->unique(['unit_pemilik_id', 'kode'], 'sop_uk0');
            $table->foreign('unit_pemilik_id', 'sop_unit_pemilik_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOA', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('periode_id');
            $table->unsignedBigInteger('standar_id');
            $table->text('ruang_lingkup');
            $table->foreign('unit_id', 'soa_unit_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->foreign('periode_id', 'soa_periode_id_fk')->references('id')->on('PERIODE_PENILAIAN')->restrictOnDelete();
            $table->foreign('standar_id', 'soa_standar_id_fk')->references('id')->on('STANDAR_KONTROL')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOA_VERSI', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('soa_id');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->string('nomor_versi');
            $table->string('status');
            $table->dateTime('dibuat_pada');
            $table->dateTime('dibekukan_pada')->nullable();
            $table->foreign('soa_id', 'soa_versi_soa_id_fk')->references('id')->on('SOA')->restrictOnDelete();
            $table->foreign('dibuat_oleh', 'soa_versi_dibuat_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOA_DETAIL', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('soa_versi_id');
            $table->unsignedBigInteger('kontrol_id');
            $table->unsignedBigInteger('penanggung_jawab_id')->nullable();
            $table->boolean('berlaku')->default(false);
            $table->text('alasan');
            $table->string('status_penerapan');
            $table->foreign('soa_versi_id', 'soa_detail_soa_versi_id_fk')->references('id')->on('SOA_VERSI')->restrictOnDelete();
            $table->foreign('kontrol_id', 'soa_detail_kontrol_id_fk')->references('id')->on('KONTROL')->restrictOnDelete();
            $table->foreign('penanggung_jawab_id', 'soa_detail_penanggung_jawab_id_fk')->references('id')->on('PEGAWAI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOA_BENCHMARK', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('soa_detail_id');
            $table->unsignedBigInteger('regulasi_id');
            $table->string('rujukan');
            $table->foreign('soa_detail_id', 'soa_benchmark_soa_detail_id_fk')->references('id')->on('SOA_DETAIL')->restrictOnDelete();
            $table->foreign('regulasi_id', 'soa_benchmark_regulasi_id_fk')->references('id')->on('REGULASI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOA_REVIEW', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('soa_versi_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->string('tahap');
            $table->string('keputusan');
            $table->text('catatan');
            $table->dateTime('waktu');
            $table->foreign('soa_versi_id', 'soa_review_soa_versi_id_fk')->references('id')->on('SOA_VERSI')->restrictOnDelete();
            $table->foreign('reviewer_id', 'soa_review_reviewer_id_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SERI_ASESMEN', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('ASESMEN_RISIKO', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seri_asesmen_id');
            $table->unsignedBigInteger('versi_sebelumnya_id')->nullable();
            $table->unsignedBigInteger('periode_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('versi_metode_id');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->text('tujuan');
            $table->text('ruang_lingkup');
            $table->string('status');
            $table->integer('nomor_versi');
            $table->dateTime('dibuat_pada');
            $table->dateTime('dibekukan_pada')->nullable();
            $table->dateTime('dibekukan_awal_pada')->nullable();
            $table->unique(['seri_asesmen_id', 'nomor_versi'], 'asesmen_risiko_uk0');
            $table->foreign('seri_asesmen_id', 'asesmen_risiko_seri_asesmen_id_fk')->references('id')->on('SERI_ASESMEN')->restrictOnDelete();
            $table->foreign('versi_sebelumnya_id', 'asesmen_risiko_versi_sebelumnya_id_fk')->references('id')->on('ASESMEN_RISIKO')->restrictOnDelete();
            $table->foreign('periode_id', 'asesmen_risiko_periode_id_fk')->references('id')->on('PERIODE_PENILAIAN')->restrictOnDelete();
            $table->foreign('unit_id', 'asesmen_risiko_unit_id_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->foreign('versi_metode_id', 'asesmen_risiko_versi_metode_id_fk')->references('id')->on('VERSI_METODE_RISIKO')->restrictOnDelete();
            $table->foreign('dibuat_oleh', 'asesmen_risiko_dibuat_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RISIKO', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asesmen_id');
            $table->unsignedBigInteger('ancaman_id');
            $table->unsignedBigInteger('kerentanan_id');
            $table->unsignedBigInteger('area_dampak_id');
            $table->unsignedBigInteger('pemilik_risiko_id');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->string('kode');
            $table->text('skenario');
            $table->text('uraian_kerentanan');
            $table->text('uraian_dampak');
            $table->string('arah_dampak');
            $table->dateTime('dibuat_pada');
            $table->unique(['asesmen_id', 'kode'], 'risiko_uk0');
            $table->foreign('asesmen_id', 'risiko_asesmen_id_fk')->references('id')->on('ASESMEN_RISIKO')->restrictOnDelete();
            $table->foreign('ancaman_id', 'risiko_ancaman_id_fk')->references('id')->on('ANCAMAN')->restrictOnDelete();
            $table->foreign('kerentanan_id', 'risiko_kerentanan_id_fk')->references('id')->on('KERENTANAN')->restrictOnDelete();
            $table->foreign('area_dampak_id', 'risiko_area_dampak_id_fk')->references('id')->on('AREA_DAMPAK')->restrictOnDelete();
            $table->foreign('pemilik_risiko_id', 'risiko_pemilik_risiko_id_fk')->references('id')->on('PEGAWAI')->restrictOnDelete();
            $table->foreign('dibuat_oleh', 'risiko_dibuat_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ASESMEN_ASET', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asesmen_id');
            $table->unsignedBigInteger('aset_id');
            $table->string('nama_snapshot');
            $table->string('kategori_snapshot');
            $table->string('klasifikasi_informasi_snapshot');
            $table->text('jalur_unit_snapshot');
            $table->string('lokasi_snapshot');
            $table->decimal('nilai_kekritisan', 15, 4);
            $table->text('dasar_kekritisan');
            $table->dateTime('direkam_pada');
            $table->unique(['asesmen_id', 'aset_id'], 'asesmen_aset_uk0');
            $table->foreign('asesmen_id', 'asesmen_aset_asesmen_id_fk')->references('id')->on('ASESMEN_RISIKO')->restrictOnDelete();
            $table->foreign('aset_id', 'asesmen_aset_aset_id_fk')->references('id')->on('ASET')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RISIKO_ASET', function (Blueprint $table): void {
            $table->unsignedBigInteger('risiko_id');
            $table->unsignedBigInteger('asesmen_aset_id');
            $table->primary(['risiko_id', 'asesmen_aset_id'], 'risiko_aset_pk');
            $table->foreign('risiko_id', 'risiko_aset_risiko_id_fk')->references('id')->on('RISIKO')->restrictOnDelete();
            $table->foreign('asesmen_aset_id', 'risiko_aset_asesmen_aset_id_fk')->references('id')->on('ASESMEN_ASET')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('PENILAIAN_RISIKO', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('risiko_id');
            $table->unsignedBigInteger('matriks_id');
            $table->unsignedBigInteger('dinilai_oleh');
            $table->string('tahap');
            $table->text('alasan_kemungkinan');
            $table->text('alasan_dampak');
            $table->text('kesimpulan');
            $table->dateTime('dinilai_pada');
            $table->foreign('risiko_id', 'penilaian_risiko_risiko_id_fk')->references('id')->on('RISIKO')->restrictOnDelete();
            $table->foreign('matriks_id', 'penilaian_risiko_matriks_id_fk')->references('id')->on('MATRIKS_RISIKO')->restrictOnDelete();
            $table->foreign('dinilai_oleh', 'penilaian_risiko_dinilai_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('USULAN_KEPUTUSAN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('penilaian_id');
            $table->unsignedBigInteger('diusulkan_oleh');
            $table->string('keputusan');
            $table->text('alasan');
            $table->dateTime('diusulkan_pada');
            $table->foreign('penilaian_id', 'usulan_keputusan_penilaian_id_fk')->references('id')->on('PENILAIAN_RISIKO')->restrictOnDelete();
            $table->foreign('diusulkan_oleh', 'usulan_keputusan_diusulkan_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('KEPUTUSAN_RISIKO', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('penilaian_id')->unique();
            $table->unsignedBigInteger('diputuskan_oleh');
            $table->string('keputusan');
            $table->text('alasan');
            $table->dateTime('diputuskan_pada');
            $table->foreign('penilaian_id', 'keputusan_risiko_penilaian_id_fk')->references('id')->on('PENILAIAN_RISIKO')->restrictOnDelete();
            $table->foreign('diputuskan_oleh', 'keputusan_risiko_diputuskan_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('REVIEW_ASESMEN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asesmen_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->string('tahap');
            $table->string('keputusan');
            $table->text('catatan');
            $table->dateTime('waktu');
            $table->string('kembali_ke')->nullable();
            $table->foreign('asesmen_id', 'review_asesmen_asesmen_id_fk')->references('id')->on('ASESMEN_RISIKO')->restrictOnDelete();
            $table->foreign('reviewer_id', 'review_asesmen_reviewer_id_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RENCANA_PENANGANAN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asesmen_id');
            $table->unsignedBigInteger('rencana_kerja_id')->nullable();
            $table->unsignedBigInteger('pic_id');
            $table->string('kode');
            $table->text('uraian');
            $table->date('target_selesai');
            $table->string('status');
            $table->foreign('asesmen_id', 'rencana_penanganan_asesmen_id_fk')->references('id')->on('ASESMEN_RISIKO')->restrictOnDelete();
            $table->foreign('rencana_kerja_id', 'rencana_penanganan_rencana_kerja_id_fk')->references('id')->on('RENCANA_KERJA')->restrictOnDelete();
            $table->foreign('pic_id', 'rencana_penanganan_pic_id_fk')->references('id')->on('PEGAWAI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RISIKO_PENANGANAN', function (Blueprint $table): void {
            $table->unsignedBigInteger('risiko_id');
            $table->unsignedBigInteger('rencana_penanganan_id');
            $table->primary(['risiko_id', 'rencana_penanganan_id'], 'risiko_penanganan_pk');
            $table->foreign('risiko_id', 'risiko_penanganan_risiko_id_fk')->references('id')->on('RISIKO')->restrictOnDelete();
            $table->foreign('rencana_penanganan_id', 'risiko_penanganan_rencana_penanganan_id_fk')->references('id')->on('RENCANA_PENANGANAN')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('PROGRES_PENANGANAN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rencana_penanganan_id');
            $table->unsignedBigInteger('dilaporkan_oleh');
            $table->text('uraian');
            $table->string('status');
            $table->dateTime('dilaporkan_pada');
            $table->foreign('rencana_penanganan_id', 'progres_penanganan_rencana_penanganan_id_fk')->references('id')->on('RENCANA_PENANGANAN')->restrictOnDelete();
            $table->foreign('dilaporkan_oleh', 'progres_penanganan_dilaporkan_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RISIKO_KONTROL', function (Blueprint $table): void {
            $table->unsignedBigInteger('risiko_id');
            $table->unsignedBigInteger('kontrol_id');
            $table->text('alasan_relevansi');
            $table->primary(['risiko_id', 'kontrol_id'], 'risiko_kontrol_pk');
            $table->foreign('risiko_id', 'risiko_kontrol_risiko_id_fk')->references('id')->on('RISIKO')->restrictOnDelete();
            $table->foreign('kontrol_id', 'risiko_kontrol_kontrol_id_fk')->references('id')->on('KONTROL')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RISIKO_KONTROL_INTERNAL', function (Blueprint $table): void {
            $table->unsignedBigInteger('risiko_id');
            $table->unsignedBigInteger('kontrol_internal_id');
            $table->string('status_penerapan');
            $table->text('kondisi_penerapan');
            $table->dateTime('diperiksa_pada');
            $table->primary(['risiko_id', 'kontrol_internal_id'], 'risiko_kontrol_internal_pk');
            $table->foreign('risiko_id', 'risiko_kontrol_internal_risiko_id_fk')->references('id')->on('RISIKO')->restrictOnDelete();
            $table->foreign('kontrol_internal_id', 'risiko_kontrol_internal_kontrol_internal_id_fk')->references('id')->on('KONTROL_INTERNAL')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOA_DETAIL_RISIKO', function (Blueprint $table): void {
            $table->unsignedBigInteger('soa_detail_id');
            $table->unsignedBigInteger('risiko_id');
            $table->primary(['soa_detail_id', 'risiko_id'], 'soa_detail_risiko_pk');
            $table->foreign('soa_detail_id', 'soa_detail_risiko_soa_detail_id_fk')->references('id')->on('SOA_DETAIL')->restrictOnDelete();
            $table->foreign('risiko_id', 'soa_detail_risiko_risiko_id_fk')->references('id')->on('RISIKO')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('DOKUMEN_VERSI', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dokumen_id');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->integer('nomor_versi');
            $table->text('isi_teks')->nullable();
            $table->string('format_teks');
            $table->string('status');
            $table->dateTime('dibuat_pada');
            $table->dateTime('dibekukan_pada')->nullable();
            $table->unique(['dokumen_id', 'nomor_versi'], 'dokumen_versi_uk0');
            $table->foreign('dokumen_id', 'dokumen_versi_dokumen_id_fk')->references('id')->on('DOKUMEN')->restrictOnDelete();
            $table->foreign('dibuat_oleh', 'dokumen_versi_dibuat_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOP_VERSI', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sop_id');
            $table->unsignedBigInteger('dokumen_versi_id');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->string('nomor_versi');
            $table->date('tanggal_berlaku');
            $table->string('status');
            $table->dateTime('dibuat_pada');
            $table->foreign('sop_id', 'sop_versi_sop_id_fk')->references('id')->on('SOP')->restrictOnDelete();
            $table->foreign('dokumen_versi_id', 'sop_versi_dokumen_versi_id_fk')->references('id')->on('DOKUMEN_VERSI')->restrictOnDelete();
            $table->foreign('dibuat_oleh', 'sop_versi_dibuat_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOP_REVIEW', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sop_versi_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->string('tahap');
            $table->string('keputusan');
            $table->text('catatan');
            $table->dateTime('waktu');
            $table->foreign('sop_versi_id', 'sop_review_sop_versi_id_fk')->references('id')->on('SOP_VERSI')->restrictOnDelete();
            $table->foreign('reviewer_id', 'sop_review_reviewer_id_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('KONTROL_INTERNAL_SOP', function (Blueprint $table): void {
            $table->unsignedBigInteger('kontrol_internal_id');
            $table->unsignedBigInteger('sop_versi_id');
            $table->primary(['kontrol_internal_id', 'sop_versi_id'], 'kontrol_internal_sop_pk');
            $table->foreign('kontrol_internal_id', 'kontrol_internal_sop_kontrol_internal_id_fk')->references('id')->on('KONTROL_INTERNAL')->restrictOnDelete();
            $table->foreign('sop_versi_id', 'kontrol_internal_sop_sop_versi_id_fk')->references('id')->on('SOP_VERSI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOP_KONTROL', function (Blueprint $table): void {
            $table->unsignedBigInteger('sop_versi_id');
            $table->unsignedBigInteger('kontrol_id');
            $table->primary(['sop_versi_id', 'kontrol_id'], 'sop_kontrol_pk');
            $table->foreign('sop_versi_id', 'sop_kontrol_sop_versi_id_fk')->references('id')->on('SOP_VERSI')->restrictOnDelete();
            $table->foreign('kontrol_id', 'sop_kontrol_kontrol_id_fk')->references('id')->on('KONTROL')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('PENANGANAN_SOP', function (Blueprint $table): void {
            $table->unsignedBigInteger('rencana_penanganan_id');
            $table->unsignedBigInteger('sop_versi_id');
            $table->primary(['rencana_penanganan_id', 'sop_versi_id'], 'penanganan_sop_pk');
            $table->foreign('rencana_penanganan_id', 'penanganan_sop_rencana_penanganan_id_fk')->references('id')->on('RENCANA_PENANGANAN')->restrictOnDelete();
            $table->foreign('sop_versi_id', 'penanganan_sop_sop_versi_id_fk')->references('id')->on('SOP_VERSI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SOA_DETAIL_SOP', function (Blueprint $table): void {
            $table->unsignedBigInteger('soa_detail_id');
            $table->unsignedBigInteger('sop_versi_id');
            $table->primary(['soa_detail_id', 'sop_versi_id'], 'soa_detail_sop_pk');
            $table->foreign('soa_detail_id', 'soa_detail_sop_soa_detail_id_fk')->references('id')->on('SOA_DETAIL')->restrictOnDelete();
            $table->foreign('sop_versi_id', 'soa_detail_sop_sop_versi_id_fk')->references('id')->on('SOP_VERSI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('BUKTI_PENILAIAN', function (Blueprint $table): void {
            $table->unsignedBigInteger('penilaian_id');
            $table->unsignedBigInteger('dokumen_versi_id');
            $table->text('keterangan');
            $table->primary(['penilaian_id', 'dokumen_versi_id'], 'bukti_penilaian_pk');
            $table->foreign('penilaian_id', 'bukti_penilaian_penilaian_id_fk')->references('id')->on('PENILAIAN_RISIKO')->restrictOnDelete();
            $table->foreign('dokumen_versi_id', 'bukti_penilaian_dokumen_versi_id_fk')->references('id')->on('DOKUMEN_VERSI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('BUKTI_SOA', function (Blueprint $table): void {
            $table->unsignedBigInteger('soa_detail_id');
            $table->unsignedBigInteger('dokumen_versi_id');
            $table->text('keterangan');
            $table->primary(['soa_detail_id', 'dokumen_versi_id'], 'bukti_soa_pk');
            $table->foreign('soa_detail_id', 'bukti_soa_soa_detail_id_fk')->references('id')->on('SOA_DETAIL')->restrictOnDelete();
            $table->foreign('dokumen_versi_id', 'bukti_soa_dokumen_versi_id_fk')->references('id')->on('DOKUMEN_VERSI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('BUKTI_PENANGANAN', function (Blueprint $table): void {
            $table->unsignedBigInteger('progres_penanganan_id');
            $table->unsignedBigInteger('dokumen_versi_id');
            $table->text('keterangan');
            $table->primary(['progres_penanganan_id', 'dokumen_versi_id'], 'bukti_penanganan_pk');
            $table->foreign('progres_penanganan_id', 'bukti_penanganan_progres_penanganan_id_fk')->references('id')->on('PROGRES_PENANGANAN')->restrictOnDelete();
            $table->foreign('dokumen_versi_id', 'bukti_penanganan_dokumen_versi_id_fk')->references('id')->on('DOKUMEN_VERSI')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('RISIKO_KONTROL_SOP', function (Blueprint $table): void {
            $table->unsignedBigInteger('risiko_id');
            $table->unsignedBigInteger('kontrol_internal_id');
            $table->unsignedBigInteger('sop_versi_id');
            $table->primary(['risiko_id', 'kontrol_internal_id', 'sop_versi_id'], 'risiko_kontrol_sop_pk');
            $table->foreign('risiko_id', 'risiko_kontrol_sop_risiko_id_fk')->references('id')->on('RISIKO')->restrictOnDelete();
            $table->foreign('kontrol_internal_id', 'risiko_kontrol_sop_kontrol_internal_id_fk')->references('id')->on('KONTROL_INTERNAL')->restrictOnDelete();
            $table->foreign('sop_versi_id', 'risiko_kontrol_sop_sop_versi_id_fk')->references('id')->on('SOP_VERSI')->restrictOnDelete();
            $table->foreign(['risiko_id', 'kontrol_internal_id'], 'risiko_kontrol_sop_acuan_fk')->references(['risiko_id', 'kontrol_internal_id'])->on('RISIKO_KONTROL_INTERNAL')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('FILE_LAMPIRAN', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('diunggah_oleh');
            $table->string('nama_asli');
            $table->string('storage_key')->unique();
            $table->string('ekstensi');
            $table->string('mime_type_terdeteksi');
            $table->unsignedBigInteger('ukuran_byte');
            $table->string('checksum_sha256');
            $table->string('status_pemeriksaan');
            $table->dateTime('diunggah_pada');
            $table->foreign('diunggah_oleh', 'file_lampiran_diunggah_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('DOKUMEN_VERSI_FILE', function (Blueprint $table): void {
            $table->unsignedBigInteger('dokumen_versi_id');
            $table->unsignedBigInteger('file_id');
            $table->string('keterangan');
            $table->integer('urutan');
            $table->primary(['dokumen_versi_id', 'file_id'], 'dokumen_versi_file_pk');
            $table->foreign('dokumen_versi_id', 'dokumen_versi_file_dokumen_versi_id_fk')->references('id')->on('DOKUMEN_VERSI')->restrictOnDelete();
            $table->foreign('file_id', 'dokumen_versi_file_file_id_fk')->references('id')->on('FILE_LAMPIRAN')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('SINKRON_ASET', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sumber_id');
            $table->unsignedBigInteger('dipicu_oleh')->nullable();
            $table->dateTime('dimulai_pada');
            $table->dateTime('selesai_pada')->nullable();
            $table->string('status');
            $table->integer('jumlah_diterima')->default(0);
            $table->integer('jumlah_dibuat')->default(0);
            $table->integer('jumlah_diubah')->default(0);
            $table->integer('jumlah_tidak_berubah')->default(0);
            $table->integer('jumlah_tidak_ditemukan')->default(0);
            $table->integer('jumlah_ditolak')->default(0);
            $table->json('peringatan');
            $table->text('galat')->nullable();
            $table->foreign('sumber_id', 'sinkron_aset_sumber_id_fk')->references('id')->on('SUMBER_ASET')->restrictOnDelete();
            $table->foreign('dipicu_oleh', 'sinkron_aset_dipicu_oleh_fk')->references('id')->on('USERS')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
CREATE VIEW v_pegawai_unit AS
WITH RECURSIVE jalur AS (
    SELECT p.pegawai_id, u.id, u.parent_id, u.jenis, u.nama, 0 AS kedalaman
    FROM PENEMPATAN_PEGAWAI p JOIN UNIT_ORGANISASI u ON u.id = p.unit_id
    WHERE p.utama = 1 AND p.selesai IS NULL AND p.mulai <= CURRENT_DATE
    UNION ALL
    SELECT j.pegawai_id, u.id, u.parent_id, u.jenis, u.nama, j.kedalaman + 1
    FROM jalur j JOIN UNIT_ORGANISASI u ON u.id = j.parent_id
    WHERE j.kedalaman < 100
)
SELECT p.id AS pegawai_id,
    MAX(CASE WHEN j.jenis = 'SATKER' THEN j.id END) AS satker_id,
    MAX(CASE WHEN j.jenis = 'SATKER' THEN j.nama END) AS satker,
    MAX(CASE WHEN j.jenis = 'BIDANG' THEN j.id END) AS bidang_id,
    MAX(CASE WHEN j.jenis = 'BIDANG' THEN j.nama END) AS bidang,
    MAX(CASE WHEN j.jenis = 'BAGIAN' THEN j.id END) AS bagian_id,
    MAX(CASE WHEN j.jenis = 'BAGIAN' THEN j.nama END) AS bagian,
    MAX(CASE WHEN j.jenis = 'SUBBIDANG' THEN j.id END) AS subbidang_id,
    MAX(CASE WHEN j.jenis = 'SUBBIDANG' THEN j.nama END) AS subbidang,
    MAX(CASE WHEN j.jenis = 'SUBBAGIAN' THEN j.id END) AS subbagian_id,
    MAX(CASE WHEN j.jenis = 'SUBBAGIAN' THEN j.nama END) AS subbagian
FROM PEGAWAI p LEFT JOIN jalur j ON j.pegawai_id = p.id
GROUP BY p.id
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_pegawai_unit');
        Schema::dropIfExists('SINKRON_ASET');
        Schema::dropIfExists('DOKUMEN_VERSI_FILE');
        Schema::dropIfExists('FILE_LAMPIRAN');
        Schema::dropIfExists('RISIKO_KONTROL_SOP');
        Schema::dropIfExists('BUKTI_PENANGANAN');
        Schema::dropIfExists('BUKTI_SOA');
        Schema::dropIfExists('BUKTI_PENILAIAN');
        Schema::dropIfExists('SOA_DETAIL_SOP');
        Schema::dropIfExists('PENANGANAN_SOP');
        Schema::dropIfExists('SOP_KONTROL');
        Schema::dropIfExists('KONTROL_INTERNAL_SOP');
        Schema::dropIfExists('SOP_REVIEW');
        Schema::dropIfExists('SOP_VERSI');
        Schema::dropIfExists('DOKUMEN_VERSI');
        Schema::dropIfExists('SOA_DETAIL_RISIKO');
        Schema::dropIfExists('RISIKO_KONTROL_INTERNAL');
        Schema::dropIfExists('RISIKO_KONTROL');
        Schema::dropIfExists('PROGRES_PENANGANAN');
        Schema::dropIfExists('RISIKO_PENANGANAN');
        Schema::dropIfExists('RENCANA_PENANGANAN');
        Schema::dropIfExists('REVIEW_ASESMEN');
        Schema::dropIfExists('KEPUTUSAN_RISIKO');
        Schema::dropIfExists('USULAN_KEPUTUSAN');
        Schema::dropIfExists('PENILAIAN_RISIKO');
        Schema::dropIfExists('RISIKO_ASET');
        Schema::dropIfExists('ASESMEN_ASET');
        Schema::dropIfExists('RISIKO');
        Schema::dropIfExists('ASESMEN_RISIKO');
        Schema::dropIfExists('SERI_ASESMEN');
        Schema::dropIfExists('SOA_REVIEW');
        Schema::dropIfExists('SOA_BENCHMARK');
        Schema::dropIfExists('SOA_DETAIL');
        Schema::dropIfExists('SOA_VERSI');
        Schema::dropIfExists('SOA');
        Schema::dropIfExists('SOP');
        Schema::dropIfExists('DOKUMEN');
        Schema::dropIfExists('KONTROL_INTERNAL_STANDAR');
        Schema::dropIfExists('KONTROL_INTERNAL');
        Schema::dropIfExists('REGULASI');
        Schema::dropIfExists('KONTROL');
        Schema::dropIfExists('STANDAR_KONTROL');
        Schema::dropIfExists('RENCANA_KERJA');
        Schema::dropIfExists('KERENTANAN');
        Schema::dropIfExists('JENIS_KERENTANAN');
        Schema::dropIfExists('ANCAMAN_SUMBER');
        Schema::dropIfExists('ANCAMAN');
        Schema::dropIfExists('SUMBER_ANCAMAN');
        Schema::dropIfExists('JENIS_ANCAMAN');
        Schema::dropIfExists('MATRIKS_RISIKO');
        Schema::dropIfExists('TINGKAT_RISIKO');
        Schema::dropIfExists('KRITERIA_DAMPAK');
        Schema::dropIfExists('AREA_DAMPAK');
        Schema::dropIfExists('SKALA_DAMPAK');
        Schema::dropIfExists('SKALA_KEMUNGKINAN');
        Schema::dropIfExists('VERSI_METODE_RISIKO');
        Schema::dropIfExists('PERIODE_PENILAIAN');
        Schema::dropIfExists('PENUGASAN_ASET');
        Schema::dropIfExists('ASET');
        Schema::dropIfExists('KLASIFIKASI_INFORMASI');
        Schema::dropIfExists('KATEGORI_ASET');
        Schema::dropIfExists('SUMBER_ASET');
        Schema::dropIfExists('LOG_AUDIT');
        Schema::dropIfExists('USER_ROLE_SCOPE');
        Schema::dropIfExists('ROLES');
        Schema::dropIfExists('USERS');
        Schema::dropIfExists('PENEMPATAN_PEGAWAI');
        Schema::dropIfExists('PEGAWAI');
        Schema::dropIfExists('UNIT_ORGANISASI');
    }
};
