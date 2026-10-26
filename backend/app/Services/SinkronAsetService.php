<?php

namespace App\Services;

use App\Contracts\AsetSyncDriver;
use App\Exceptions\BusinessRuleException;
use App\Models\Aset;
use App\Models\KategoriAset;
use App\Models\SinkronAset;
use App\Models\SumberAset;
use App\Models\UnitOrganisasi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SinkronAsetService
{
    public const LOCK = 'simris.aset.sinkron';

    public function enqueue(?int $actor): SinkronAset
    {
        $lock = Cache::lock(self::LOCK, (int) config('aset.lock_seconds'));
        if (! $lock->get()) {
            throw new BusinessRuleException('sinkron_sedang_berjalan', 'Sinkronisasi aset sedang berjalan atau menunggu antrean.');
        }
        try {
            $source = SumberAset::where('kode', 'API_ASET')->firstOrFail();
            $run = SinkronAset::create(['sumber_id' => $source->id, 'dipicu_oleh' => $actor, 'dimulai_pada' => now(), 'status' => 'BERJALAN', 'peringatan' => [], 'jumlah_diterima' => 0, 'jumlah_dibuat' => 0, 'jumlah_diubah' => 0, 'jumlah_tidak_berubah' => 0, 'jumlah_tidak_ditemukan' => 0, 'jumlah_ditolak' => 0]);
            \App\Jobs\SinkronAset::dispatch($run->id, $lock->owner());

            return $run->refresh();
        } catch (\Throwable $e) {
            if (isset($run)) {
                $run->update(['status' => 'GAGAL', 'galat' => 'Job sinkronisasi tidak dapat dimasukkan ke antrean.', 'selesai_pada' => now()]);
            } $lock->release();
            throw $e;
        }
    }

    public function run(int $runId, string $owner, AsetSyncDriver $driver): void
    {
        $run = SinkronAset::findOrFail($runId);
        if ($run->status !== 'BERJALAN') {
            return;
        }
        if (! Cache::restoreLock(self::LOCK, $owner)->isOwnedByCurrentProcess()) {
            $run->update(['status' => 'GAGAL', 'galat' => 'Kunci sinkronisasi kedaluwarsa sebelum job dimulai.', 'selesai_pada' => now()]);

            return;
        }
        try {
            $unit = UnitOrganisasi::where('kode', config('aset.unit_bawaan_kode'))->where('jenis', 'SATKER')->where('aktif', true)->first();
            $category = KategoriAset::where('kode', 'BELUM_DIKATEGORIKAN')->first();
            if (! $unit || ! $category) {
                throw new BusinessRuleException('konfigurasi_aset_belum_lengkap', 'Satker bawaan atau kategori belum tersedia.');
            }
            $rows = $driver->fetch();
            $stats = ['jumlah_diterima' => count($rows), 'jumlah_dibuat' => 0, 'jumlah_diubah' => 0, 'jumlah_tidak_berubah' => 0, 'jumlah_tidak_ditemukan' => 0, 'jumlah_ditolak' => 0];
            $warnings = [];
            $seen = [];
            $serials = [];
            $inventories = [];
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::transaction(function () use ($chunk, $run, $unit, $category, &$stats, &$warnings, &$seen, &$serials, &$inventories): void {
                    foreach ($chunk as $row) {
                        $idx = is_array($row) ? ($row['idx'] ?? null) : null;
                        if (is_int($idx)) {
                            $already = isset($seen[$idx]);
                            $seen[$idx] = true;
                        } else {
                            $already = false;
                        }
                        $name = is_array($row) ? $this->text($row['nama'] ?? null, 255) : null;
                        $stock = is_array($row) ? ($row['stock'] ?? null) : null;
                        if (! is_int($idx) || $idx < 0 || ! $name || ! is_int($stock) || $stock < 1 || $stock > 2147483647 || $already) {
                            $stats['jumlah_ditolak']++;
                            $warnings[] = ['idx' => is_int($idx) ? $idx : null, 'pesan' => 'Baris tidak valid atau idx duplikat.'];

                            continue;
                        }
                        $inventory = $this->text($row['noinven'] ?? null, 255);
                        $serial = $this->text($row['snumber'] ?? null, 255);
                        $condition = mb_strtolower($this->text($row['kondisi'] ?? null, 255) ?? '');
                        $normalizedCondition = match ($condition) {
                            'baik' => 'BAIK','rusak biasa' => 'RUSAK_BIASA','rusak berat' => 'RUSAK_BERAT',default => 'TIDAK_DIKETAHUI'
                        };
                        if ($normalizedCondition === 'TIDAK_DIKETAHUI' && $condition !== '') {
                            $warnings[] = ['idx' => $idx, 'pesan' => 'Kondisi tidak dikenal.'];
                        }
                        foreach (['inventaris' => $inventory, 'seri' => $serial] as $type => $value) {
                            $column = $type === 'inventaris' ? 'nomor_inventaris' : 'nomor_seri';
                            $existing = $type === 'inventaris' ? $inventories : $serials;
                            if ($value && (isset($existing[$value]) || Aset::where($column, $value)->where(fn ($q) => $q->where('sumber_id', '!=', $run->sumber_id)->orWhere('external_id', '!=', (string) $idx))->exists())) {
                                $warnings[] = ['idx' => $idx, 'pesan' => 'Nomor '.$type.' ganda.'];
                            }
                        }
                        if ($inventory) {
                            $inventories[$inventory] = true;
                        } if ($serial) {
                            $serials[$serial] = true;
                        }
                        $year = $this->text($row['lokasi'] ?? null, 255);
                        $yearValid = $year && preg_match('/^[0-9]{4}$/', $year);
                        if (! $yearValid) {
                            $warnings[] = ['idx' => $idx, 'pesan' => 'Lokasi sumber bukan tahun empat digit.'];
                        }
                        $note = $this->text($row['keterangan'] ?? null, 1000);
                        if ($note && str_contains(mb_strtolower($note), 'hilang')) {
                            $warnings[] = ['idx' => $idx, 'pesan' => 'Keterangan memuat hilang; perlu diperiksa.'];
                        }
                        $normalized = ['nomor_inventaris' => $inventory, 'nama' => $name, 'merk' => $this->text($row['merk'] ?? null, 255), 'nomor_seri' => $serial, 'jumlah' => $stock, 'kondisi' => $normalizedCondition, 'nama_pengadaan' => $this->text($row['pengadaan'] ?? null, 255), 'tahun_pengadaan' => $yearValid ? (int) $year : null, 'keterangan' => $note];
                        $hash = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                        $asset = Aset::where('sumber_id', $run->sumber_id)->where('external_id', (string) $idx)->lockForUpdate()->first();
                        if (! $asset) {
                            $code = $inventory && ! Aset::where('kode_aset', $inventory)->exists() ? $inventory : 'API_ASET-'.$idx;
                            if (Aset::where('kode_aset', $code)->exists()) {
                                $stats['jumlah_ditolak']++;
                                $warnings[] = ['idx' => $idx, 'pesan' => 'Kode aset bentukan sudah digunakan; perlu keputusan penamaan.'];

                                continue;
                            }
                            Aset::create([...$normalized, 'sumber_id' => $run->sumber_id, 'external_id' => (string) $idx, 'kode_aset' => $code, 'unit_pemilik_id' => $unit->id, 'kategori_id' => $category->id, 'klasifikasi_informasi_id' => null, 'lokasi' => '', 'deskripsi' => '', 'tanggal_pengadaan' => null, 'status' => 'AKTIF', 'last_synced_at' => now(), 'data_mentah' => $row, 'hash_data' => $hash, 'perlu_ditinjau' => true]);
                            $stats['jumlah_dibuat']++;
                        } elseif ($asset->hash_data !== $hash) {
                            $asset->fill([...$normalized, 'status' => 'AKTIF', 'data_mentah' => $row, 'hash_data' => $hash, 'last_synced_at' => now()])->save();
                            $stats['jumlah_diubah']++;
                        } elseif ($asset->status !== 'AKTIF') {
                            $asset->update(['status' => 'AKTIF']);
                            $stats['jumlah_diubah']++;
                        } else {
                            $stats['jumlah_tidak_berubah']++;
                        }
                    }
                    $run->update([...$stats, 'peringatan' => $warnings]);
                });
            }
            if ($seen !== []) {
                Aset::where('sumber_id', $run->sumber_id)->where('status', '!=', 'TIDAK_DITEMUKAN')->whereNotIn('external_id', array_map('strval', array_keys($seen)))->chunkById(200, function ($assets) use (&$stats): void {
                    DB::transaction(function () use ($assets, &$stats): void {
                        foreach ($assets as $asset) {
                            $asset->update(['status' => 'TIDAK_DITEMUKAN']);
                            $stats['jumlah_tidak_ditemukan']++;
                        }
                    });
                });
            }
            $run->update([...$stats, 'peringatan' => $warnings, 'status' => $stats['jumlah_ditolak'] > 0 ? 'SEBAGIAN' : 'SUKSES', 'selesai_pada' => now()]);
        } catch (\Throwable $e) {
            $message = $e instanceof BusinessRuleException ? json_decode($e->getResponse()->getContent(), true)['message'] : 'Sinkronisasi gagal; periksa konfigurasi dan integritas data.';
            $run->update(['status' => 'GAGAL', 'galat' => $message, 'selesai_pada' => now()]);
        } finally {
            Cache::restoreLock(self::LOCK, $owner)->release();
        }
    }

    private function text(mixed $value, int $limit): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $text = trim(preg_replace('/[\p{Cc}\p{Cf}]/u','',(string) $value) ?? '');

        return $text === '' || $text === '-' ? null : mb_substr($text,0,$limit);
    }
}
