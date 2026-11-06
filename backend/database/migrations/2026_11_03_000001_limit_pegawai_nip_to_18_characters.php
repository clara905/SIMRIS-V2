<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->changeNipLength(18);
    }

    public function down(): void
    {
        $this->changeNipLength(255);
    }

    private function changeNipLength(int $length): void
    {
        // SQLite rebuilds the table for change(); the dependent view must be
        // removed first or the temporary-table rename fails.
        DB::statement('DROP VIEW IF EXISTS v_pegawai_unit');

        try {
            Schema::table('PEGAWAI', function (Blueprint $table) use ($length): void {
                $table->string('nip', $length)->nullable()->change();
            });
        } catch (\Throwable $exception) {
            $this->createPegawaiUnitView();
            throw $exception;
        }

        $this->createPegawaiUnitView();
    }

    private function createPegawaiUnitView(): void
    {
        DB::statement(<<<'SQL'
CREATE VIEW v_pegawai_unit AS
WITH RECURSIVE jalur AS (
    SELECT p.pegawai_id, u.id, u.parent_id, u.jenis, u.nama, 0 AS kedalaman
    FROM PENEMPATAN_PEGAWAI p
    JOIN UNIT_ORGANISASI u ON u.id = p.unit_id
    WHERE p.utama = 1 AND p.selesai IS NULL AND p.mulai <= CURRENT_DATE
    UNION ALL
    SELECT j.pegawai_id, u.id, u.parent_id, u.jenis, u.nama, j.kedalaman + 1
    FROM jalur j
    JOIN UNIT_ORGANISASI u ON u.id = j.parent_id
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
FROM PEGAWAI p
LEFT JOIN jalur j ON j.pegawai_id = p.id
GROUP BY p.id
SQL);
    }
};
