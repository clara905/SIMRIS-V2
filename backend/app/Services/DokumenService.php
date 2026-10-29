<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Jobs\PeriksaFileLampiran;
use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\FileLampiran;
use App\Models\UnitOrganisasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenService
{
    public function __construct(private ScopeUnit $scope, private KatalogService $catalog) {}

    public function documents(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('dokumen.view');

        $units = $this->scope->isSuper($actor)
            ? $this->scope->ids($actor)
            : ($this->scope->hasRole($actor, ['ADMIN_SATKER'])
                ? $this->scope->idsForRoles($actor, ['ADMIN_SATKER'])
                : $this->scope->ids($actor));

        return Dokumen::whereIn('unit_pemilik_id', $units);
    }

    public function versions(User $actor): Builder
    {
        return DokumenVersi::whereIn('dokumen_id', $this->documents($actor)->select('id'));
    }

    public function files(User $actor): Builder
    {
        return FileLampiran::whereIn('id', DB::table('DOKUMEN_VERSI_FILE')->select('file_id')->whereIn('dokumen_versi_id', $this->versions($actor)->select('id')));
    }

    public function query(string $resource, User $actor): Builder
    {
        return match ($resource) {
            'dokumen-versi' => $this->versions($actor),'file-lampiran' => $this->files($actor),default => $this->documents($actor)
        };
    }

    public function listing(string $resource, User $actor, array $input, ?int $document = null): mixed
    {
        $query = $this->query($resource, $actor);
        if ($document) {
            $this->documents($actor)->findOrFail($document);
            $query->where('dokumen_id', $document);
        }
        $allowed = match ($resource) {
            'dokumen-versi' => ['id', 'dokumen_id', 'nomor_versi', 'status', 'dibuat_oleh'],'file-lampiran' => ['id', 'diunggah_oleh', 'nama_asli', 'ekstensi', 'status_pemeriksaan'],default => ['id', 'kode', 'judul', 'jenis', 'unit_pemilik_id', 'dibuat_oleh']
        };

        return $this->catalog->filtered($query, $input, $allowed)->paginate((int) ($input['per_page'] ?? 20))->withQueryString();
    }

    public function create(array $data, User $actor): Dokumen
    {
        return DB::transaction(function () use ($data, $actor): Dokumen {
            $unit = UnitOrganisasi::whereIn('id', $this->scope->idsForRoles($actor, ['ADMIN_SATKER', 'STAFF']))->findOrFail($data['unit_pemilik_id']);
            Gate::forUser($actor)->authorize('dokumen.write', [(int) $unit->id]);

            return Dokumen::create([...$data, 'dibuat_oleh' => $actor->id, 'dibuat_pada' => now()]);
        });
    }

    public function createVersion(int $document, array $data, User $actor): DokumenVersi
    {
        return DB::transaction(function () use ($document, $data, $actor): DokumenVersi {
            $doc = $this->documents($actor)->whereKey($document)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('dokumen.write', [(int) $doc->unit_pemilik_id]);

            return DokumenVersi::create([...$data, 'dokumen_id' => $doc->id, 'nomor_versi' => (int) DokumenVersi::where('dokumen_id', $doc->id)->max('nomor_versi') + 1, 'dibuat_oleh' => $actor->id, 'dibuat_pada' => now(), 'status' => 'DRAFT']);
        });
    }

    public function draft(int $id, User $actor): DokumenVersi
    {
        $version = $this->versions($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
        $doc = $this->documents($actor)->findOrFail($version->dokumen_id);
        Gate::forUser($actor)->authorize('dokumen.write', [(int) $doc->unit_pemilik_id]);
        if ($version->status !== 'DRAFT' || $version->dibekukan_pada) {
            throw new BusinessRuleException('dokumen_final', 'Versi FINAL tidak dapat diubah.');
        }

return $version;
    }

    public function updateVersion(int $id, array $data, User $actor, bool $final = false): DokumenVersi
    {
        return DB::transaction(function () use ($id, $data, $actor, $final): DokumenVersi {
            $version = $this->draft($id, $actor);
            if ($final) {
                $this->assertReady($version);
                $version->update(['status' => 'FINAL', 'dibekukan_pada' => now()]);
            } else {
                $version->fill($data)->save();
            }

            return $version;
        });
    }

    public function assertReady(DokumenVersi $version): void
    {
        $files = FileLampiran::whereIn('id', DB::table('DOKUMEN_VERSI_FILE')->select('file_id')->where('dokumen_versi_id', $version->id))->get();
        if ($files->contains(fn (FileLampiran $file): bool => $file->status_pemeriksaan !== 'AMAN')) {
            throw new BusinessRuleException('lampiran_belum_aman', 'Semua lampiran harus AMAN sebelum finalisasi.');
        }
        if (trim($version->isi_teks ?? '') === '' && $files->isEmpty()) {
            throw new BusinessRuleException('dokumen_kosong', 'Isi teks atau lampiran AMAN diperlukan untuk finalisasi.');
        }
    }

    /** R17: reused by evidence/SOP services; call in their transaction. */
    public function finalVersion(int $id, User $actor): DokumenVersi
    {
        $version = $this->versions($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($version->status !== 'FINAL') {
            throw new BusinessRuleException('dokumen_belum_final', 'Bukti harus mengacu ke versi dokumen FINAL.');
        }
        $this->assertReady($version);

        return $version;
    }

    public function upload(int $versionId, UploadedFile $upload, array $data, User $actor): FileLampiran
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload->getRealPath());
        if (! in_array($mime, config('lampiran.mime.'.$extension, []), true)) {
            throw ValidationException::withMessages(['file' => ['Ekstensi atau MIME isi berkas tidak diizinkan.']]);
        }
        $key = null;
        try {
            $file = DB::transaction(function () use ($versionId, $upload, $data, $actor, $extension, $mime, &$key): FileLampiran {
                $version = $this->draft($versionId, $actor);
                $key = $upload->store('lampiran', config('lampiran.disk'));
                $name = mb_substr(preg_replace('/[\p{Cc}\p{Cf}]/u', '', basename(str_replace('\\', '/', $upload->getClientOriginalName()))) ?? 'lampiran', 0, 255);
                $file = FileLampiran::create(['diunggah_oleh' => $actor->id, 'nama_asli' => $name, 'storage_key' => $key, 'ekstensi' => $extension, 'mime_type_terdeteksi' => $mime, 'ukuran_byte' => $upload->getSize(), 'checksum_sha256' => hash_file('sha256', $upload->getRealPath()), 'status_pemeriksaan' => 'PENDING', 'diunggah_pada' => now()]);
                $link = ['dokumen_versi_id' => $version->id, 'file_id' => $file->id, 'keterangan' => $data['keterangan'] ?? '', 'urutan' => $data['urutan'] ?? 1, 'created_at' => now(), 'updated_at' => now()];
                DB::table('DOKUMEN_VERSI_FILE')->insert($link);
                app(AuditService::class)->record('DOKUMEN_VERSI_FILE', $version->id, 'CREATE', null, ['file_id' => $file->id]);

                return $file;
            });
        } catch (\Throwable $e) {
            if ($key) {
                Storage::disk(config('lampiran.disk'))->delete($key);
            } throw $e;
        }
        PeriksaFileLampiran::dispatch($file->id);

        return $file->refresh();
    }

    public function detach(int $versionId, int $fileId, User $actor): void
    {
        DB::transaction(function () use ($versionId, $fileId, $actor): void {
            $version = $this->draft($versionId, $actor);
            $link = DB::table('DOKUMEN_VERSI_FILE')->where('dokumen_versi_id', $version->id)->where('file_id', $fileId);
            abort_unless($link->exists(), 404, 'Lampiran tidak ditemukan.');
            $link->delete();
            app(AuditService::class)->record('DOKUMEN_VERSI_FILE', $version->id, 'DELETE', ['file_id' => $fileId]);
        });
    }

    public function download(int $id, User $actor): StreamedResponse
    {
        $file = $this->files($actor)->findOrFail($id);
        if ($file->status_pemeriksaan !== 'AMAN') {
            throw new BusinessRuleException('lampiran_belum_aman', 'Berkas belum dinyatakan AMAN.');
        }
        $disk = Storage::disk(config('lampiran.disk'));
        abort_unless($disk->exists($file->storage_key), 404, 'Berkas tidak ditemukan.');
        if (! hash_equals($file->checksum_sha256,hash_file('sha256',$disk->path($file->storage_key)))) {
            throw new BusinessRuleException('integritas_berkas','Checksum berkas tidak sesuai.');
        }

        return $disk->download($file->storage_key,$file->nama_asli,['Content-Type' => $file->mime_type_terdeteksi, 'X-Content-Type-Options' => 'nosniff']);
    }
}
