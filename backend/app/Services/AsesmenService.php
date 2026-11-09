<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\AsesmenAset;
use App\Models\AsesmenRisiko;
use App\Models\KategoriAset;
use App\Models\KlasifikasiInformasi;
use App\Models\MatriksRisiko;
use App\Models\Pegawai;
use App\Models\PenilaianRisiko;
use App\Models\PeriodePenilaian;
use App\Models\Risiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\SeriAsesmen;
use App\Models\TingkatRisiko;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UsulanKeputusan;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AsesmenService
{
    public function __construct(private ScopeUnit $scope, private KatalogService $catalog) {}

    public function assessments(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('asesmen.view');

        return AsesmenRisiko::whereIn('unit_id', $this->scope->ids($actor));
    }

    public function risks(User $actor): Builder
    {
        return Risiko::whereIn('asesmen_id', $this->assessments($actor)->select('id'));
    }

    public function query(string $resource, User $actor): Builder
    {
        return match ($resource) {
            'risiko' => $this->risks($actor),'asesmen-aset' => AsesmenAset::whereIn('asesmen_id', $this->assessments($actor)->select('id')),'penilaian' => PenilaianRisiko::whereIn('risiko_id', $this->risks($actor)->select('id')),'usulan-keputusan' => UsulanKeputusan::whereIn('penilaian_id', PenilaianRisiko::whereIn('risiko_id', $this->risks($actor)->select('id'))->select('id')),default => $this->assessments($actor)
        };
    }

    public function assessmentFor(string $resource, int $id, User $actor): AsesmenRisiko
    {
        $model = $this->query($resource, $actor)->findOrFail($id);
        if ($model instanceof AsesmenRisiko) {
            return $model;
        }
        if ($model instanceof UsulanKeputusan) {
            $model = PenilaianRisiko::findOrFail($model->penilaian_id);
        }
        if ($model instanceof PenilaianRisiko) {
            $model = Risiko::findOrFail($model->risiko_id);
        }

        return $this->assessments($actor)->findOrFail($model->asesmen_id);
    }

    public function listing(string $resource, User $actor, array $data, ?int $assessment = null, ?int $risk = null): mixed
    {
        $query = $this->query($resource, $actor);
        if ($resource === 'asesmen' && $this->scope->hasRole($actor, ['STAFF'])) {
            $query->where('dibuat_oleh', $actor->id);
        }
        if ($assessment) {
            $this->assessments($actor)->findOrFail($assessment);
            $query->where('asesmen_id', $assessment);
        }
        if ($risk) {
            $this->risks($actor)->findOrFail($risk);
            if ($resource === 'usulan-keputusan') {
                $query->whereIn('penilaian_id', PenilaianRisiko::where('risiko_id', $risk)->select('id'));
            } else {
                $query->where('risiko_id', $risk);
            }
        }
        $allowed = match ($resource) {
            'risiko' => ['id', 'asesmen_id', 'kode', 'ancaman_id', 'kerentanan_id', 'pemilik_risiko_id', 'area_dampak_id'],'asesmen-aset' => ['id', 'asesmen_id', 'aset_id', 'nama_snapshot'],'penilaian' => ['id', 'risiko_id', 'matriks_id', 'tahap', 'dinilai_oleh'],'usulan-keputusan' => ['id', 'penilaian_id', 'keputusan'],default => ['id', 'unit_id', 'periode_id', 'seri_asesmen_id', 'status', 'nomor_versi', 'dibuat_oleh', 'versi_metode_id']
        };

        return $this->catalog->filtered($query, $data, $allowed)->paginate((int) ($data['per_page'] ?? 20))->withQueryString();
    }

    public function riskMap(int $assessmentId, User $actor): array
    {
        $assessment = $this->assessments($actor)->findOrFail($assessmentId);
        $method = VersiMetodeRisiko::findOrFail($assessment->versi_metode_id);
        $risks = $this->risks($actor)->where('asesmen_id', $assessment->id)->orderBy('id')->get();
        $riskIds = $risks->pluck('id');
        $ratings = PenilaianRisiko::whereIn('risiko_id', $riskIds)->orderBy('id')->get()->groupBy('risiko_id');
        $cells = MatriksRisiko::where('versi_metode_id', $method->id)->get()->keyBy('id');
        $levels = TingkatRisiko::where('versi_metode_id', $method->id)->get()->keyBy('id');
        $possibilities = SkalaKemungkinan::where('versi_metode_id', $method->id)->get()->keyBy('id');
        $impacts = SkalaDampak::where('versi_metode_id', $method->id)->get()->keyBy('id');
        $methodCells = $cells->values();
        $ratingIds = $ratings->flatten()->pluck('id');
        $proposals = UsulanKeputusan::whereIn('penilaian_id', $ratingIds)->get()->groupBy('penilaian_id');
        $riskAssets = DB::table('RISIKO_ASET')->whereIn('risiko_id', $riskIds)->get()->groupBy('risiko_id');
        $snapshots = AsesmenAset::where('asesmen_id', $assessment->id)->orderBy('id')->get();
        $calculator = app(HitungRisiko::class);
        $stages = ['SAAT_INI', 'TARGET_SETELAH', 'AKTUAL_SETELAH'];
        $emptyCells = $methodCells->mapWithKeys(fn (MatriksRisiko $cell): array => [$cell->id => [
            'matriks_id' => $cell->id,
            'kemungkinan_id' => $cell->kemungkinan_id,
            'dampak_id' => $cell->dampak_id,
            'tingkat_risiko_id' => $cell->tingkat_risiko_id,
            'skor' => $cell->skor,
            'jumlah' => 0,
            'risiko_ids' => [],
        ]])->all();
        $map = collect($stages)->mapWithKeys(fn (string $stage): array => [$stage => $emptyCells])->all();
        $riskData = $risks->map(function (Risiko $risk) use ($ratings, $cells, $levels, $possibilities, $impacts, $proposals, $riskAssets, $method, $calculator, &$map): array {
            $riskData = $risk->toArray();
            $riskData['asesmen_aset_ids'] = ($riskAssets->get($risk->id) ?? collect())->pluck('asesmen_aset_id')->map(fn ($id): int => (int) $id)->values();
            $riskData['penilaian'] = ($ratings->get($risk->id) ?? collect())->map(function (PenilaianRisiko $rating) use ($cells, $levels, $possibilities, $impacts, $proposals, $method, $calculator, &$map, $risk): array {
                $ratingData = $rating->toArray();
                $cell = $cells->get($rating->matriks_id);
                $ratingData['matriks'] = $cell;
                $ratingData['tingkat'] = $cell ? $levels->get($cell->tingkat_risiko_id) : null;
                $ratingData['kemungkinan'] = $cell ? $possibilities->get($cell->kemungkinan_id) : null;
                $ratingData['dampak'] = $cell ? $impacts->get($cell->dampak_id) : null;
                if ($cell && $ratingData['tingkat'] && $ratingData['kemungkinan'] && $ratingData['dampak']) {
                    if ($method->profil_penilaian) {
                        $calculated = $calculator->fromCell($method, $cell, $ratingData['kemungkinan'], $ratingData['dampak'], $ratingData['tingkat']);
                        $ratingData['skor'] = $calculated['skor'];
                        $ratingData['perlu_rtp'] = $calculated['perlu_rtp'];
                        $ratingData['perlu_persetujuan_pemilik'] = $calculated['perlu_persetujuan_pemilik'];
                        $ratingData['kriteria_penerimaan'] = $calculated['kriteria_penerimaan'];
                    } else {
                        $ratingData['skor'] = $cell->skor;
                        $ratingData['perlu_rtp'] = null;
                        $ratingData['perlu_persetujuan_pemilik'] = null;
                        $ratingData['kriteria_penerimaan'] = null;
                    }
                    if (isset($map[$rating->tahap][$cell->id])) {
                        $map[$rating->tahap][$cell->id]['jumlah']++;
                        $map[$rating->tahap][$cell->id]['risiko_ids'][] = (int) $risk->id;
                    }
                }
                $ratingData['usulan_keputusan'] = $proposals->get($rating->id, collect())->values();

                return $ratingData;
            })->values();
            $riskData['usulan_keputusan'] = ($ratings->get($risk->id) ?? collect())->flatMap(fn (PenilaianRisiko $rating) => $proposals->get($rating->id, collect()))->values();

            return $riskData;
        })->values()->all();

        return [
            'asesmen' => $assessment,
            'metode' => [
                'id' => $method->id,
                'nama' => $method->nama,
                'versi' => $method->versi,
                'referensi' => $method->referensi,
                'profil_penilaian' => $method->profil_penilaian,
                'kemungkinan' => $possibilities->values(),
                'dampak' => $impacts->values(),
                'tingkat' => $levels->values(),
                'matriks' => $methodCells->sortBy(fn (MatriksRisiko $cell): string => sprintf('%05d:%05d', $cell->kemungkinan_id, $cell->dampak_id))->values(),
            ],
            'peta' => $map,
            'aset' => $snapshots,
            'risiko' => $riskData,
        ];
    }

    public function editable(int $id, User $actor): AsesmenRisiko
    {
        $assessment = $this->assessments($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('asesmen.write', [$assessment]);
        if ($assessment->status !== 'DRAFT' || $assessment->dibekukan_awal_pada || $assessment->dibekukan_pada) {
            throw new BusinessRuleException('asesmen_bukan_draft', 'Bagian awal asesmen hanya dapat diubah saat DRAFT.');
        }
        $this->assertDraftPeriodOpen($assessment);

return $assessment;
    }

    public function assertDraftPeriodOpen(AsesmenRisiko $assessment): void
    {
        $period = PeriodePenilaian::findOrFail($assessment->periode_id);
        if (today()->lt($period->tanggal_mulai) || today()->gt($period->tanggal_selesai)) {
            throw new BusinessRuleException('periode_tidak_berjalan', 'Periode penilaian belum dibuka / sudah ditutup');
        }
    }

    public function create(array $data, User $actor): AsesmenRisiko
    {
        return DB::transaction(function () use ($data, $actor): AsesmenRisiko {
            $unitId = $actor->pegawai_id
                ? DB::table('PENEMPATAN_PEGAWAI')
                    ->where('pegawai_id', $actor->pegawai_id)
                    ->where('utama', true)
                    ->where('mulai', '<=', today())
                    ->where(fn ($query) => $query->whereNull('selesai')->orWhere('selesai', '>=', today()))
                    ->value('unit_id')
                : null;
            if ($unitId === null || ! in_array((int) $unitId, $this->scope->idsForRoles($actor, ['STAFF']), true)) {
                abort(403, 'STAFF harus memiliki penempatan aktif dalam scope untuk membuat asesmen.');
            }
            $unit = UnitOrganisasi::whereKey($unitId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('asesmen.create', [(int) $unit->id]);
            if (! $unit->aktif) {
                throw new BusinessRuleException('unit_tidak_aktif', 'Unit asesmen harus aktif.');
            }
            $period = PeriodePenilaian::findOrFail($data['periode_id']);
            if (today()->lt($period->tanggal_mulai) || today()->gt($period->tanggal_selesai)) {
                throw new BusinessRuleException('periode_tidak_berjalan', 'Periode penilaian belum dibuka / sudah ditutup');
            }
            $method = VersiMetodeRisiko::where('dibekukan', true)->orderByDesc('id')->lockForUpdate()->first();
            if (! $method) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'versi_metode_id' => ['Metode risiko belum disiapkan Super Admin.'],
                ]);
            }
            app(MetodeRisikoService::class)->forAssessment((int) $method->id);
            if (AsesmenRisiko::where('unit_id', $unit->id)->where('periode_id', $period->id)->exists()) {
                throw new BusinessRuleException('seri_aktif_sudah_ada', 'Unit ini sudah memiliki seri asesmen untuk periode tersebut. Gunakan Buat versi baru pada seri yang sama.');
            }
            $series = SeriAsesmen::create(['kode' => 'ASM-'.Str::uuid(), 'nama' => 'Asesmen '.$unit->nama.' '.$period->nama]);

            return AsesmenRisiko::create([...$data, 'unit_id' => $unit->id, 'versi_metode_id' => $method->id, 'seri_asesmen_id' => $series->id, 'nomor_versi' => 1, 'dibuat_oleh' => $actor->id, 'dibuat_pada' => now(), 'status' => 'DRAFT']);
        });
    }

    public function update(int $id, array $data, User $actor): AsesmenRisiko
    {
        return DB::transaction(function () use ($id, $data, $actor): AsesmenRisiko {
            $model = $this->editable($id, $actor);
            $model->fill($data)->save();

            return $model;
        });
    }

    public function risk(?int $id, int $assessmentId, array $data, User $actor, bool $delete = false): Risiko
    {
        try {
            return DB::transaction(function () use ($id, $assessmentId, $data, $actor, $delete): Risiko {
                $assessment = $this->editable($assessmentId, $actor);
                $risk = $id ? Risiko::where('asesmen_id', $assessment->id)->findOrFail($id) : new Risiko(['asesmen_id' => $assessment->id, 'dibuat_oleh' => $actor->id, 'dibuat_pada' => now()]);
                if ($delete) {
                    $risk->delete();

                    return $risk;
                }
                foreach (['ancaman_id' => 'ANCAMAN', 'kerentanan_id' => 'KERENTANAN'] as $field => $table) {
                    if (array_key_exists($field, $data)) {
                        $this->assertActiveMaster($table, (int) $data[$field], $field);
                    }
                }
                if (isset($data['pemilik_risiko_id'])) {
                    Pegawai::where('aktif', true)
                        ->whereIn('id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')
                            ->where('unit_id', $assessment->unit_id)
                            ->where('mulai', '<=', today())
                            ->where(fn ($query) => $query->whereNull('selesai')->orWhere('selesai', '>=', today())))
                        ->findOrFail($data['pemilik_risiko_id']);
                }
                $assets = $data['asesmen_aset_ids'] ?? null;
                unset($data['asesmen_aset_ids']);
                unset($data['kode']);
                if (! $id) {
                    $data['kode'] = $this->nextRiskCode($assessment->id);
                }
                $risk->fill($data)->save();
                if ($assets !== null) {
                    if (AsesmenAset::where('asesmen_id', $assessment->id)->whereIn('id', $assets)->count() !== count($assets)) {
                        throw new BusinessRuleException('aset_beda_asesmen', 'Semua snapshot aset harus berasal dari asesmen risiko ini.');
                    }
                    $before = DB::table('RISIKO_ASET')->where('risiko_id', $risk->id)->pluck('asesmen_aset_id')->all();
                    DB::table('RISIKO_ASET')->where('risiko_id', $risk->id)->delete();
                    foreach ($assets as $asset) {
                        DB::table('RISIKO_ASET')->insert(['risiko_id' => $risk->id, 'asesmen_aset_id' => $asset, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    app(AuditService::class)->record('RISIKO_ASET', $risk->id, 'UPDATE', ['asesmen_aset_ids' => $before], ['asesmen_aset_ids' => $assets]);
                }

                return $risk;
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505', '23503'], true)) {
                throw new BusinessRuleException('integritas_data', 'Kode risiko duplikat atau data masih dirujuk.');
            } throw $e;
        }
    }

    public function snapshot(int $assessmentId, array $data, User $actor): AsesmenAset
    {
        return DB::transaction(function () use ($assessmentId, $data, $actor): AsesmenAset {
            $assessment = $this->editable($assessmentId, $actor);
            $asset = app(AsetService::class)->query($actor)
                ->where('unit_pemilik_id', $assessment->unit_id)
                ->whereKey($data['aset_id'])
                ->lockForUpdate()
                ->firstOrFail();
            if (AsesmenAset::where('asesmen_id', $assessmentId)->where('aset_id', $asset->id)->exists()) {
                throw new BusinessRuleException('aset_sudah_direkam', 'Aset sudah memiliki snapshot pada asesmen ini.');
            }
            $path = [];
            $seen = [];
            $unit = UnitOrganisasi::findOrFail($asset->unit_pemilik_id);
            do {
                if (isset($seen[$unit->id])) {
                    throw new BusinessRuleException('siklus_hierarki', 'Jalur unit aset tidak valid.');
                } $seen[$unit->id] = true;
                array_unshift($path, ['id' => $unit->id, 'kode' => $unit->kode, 'nama' => $unit->nama, 'jenis' => $unit->jenis]);
                $unit = $unit->parent_id ? UnitOrganisasi::findOrFail($unit->parent_id) : null;
            } while ($unit);

            return AsesmenAset::create([...$data, 'asesmen_id' => $assessment->id, 'nama_snapshot' => $asset->nama, 'kategori_snapshot' => KategoriAset::findOrFail($asset->kategori_id)->nama, 'klasifikasi_informasi_snapshot' => $asset->klasifikasi_informasi_id ? KlasifikasiInformasi::findOrFail($asset->klasifikasi_informasi_id)->nama : '', 'jalur_unit_snapshot' => json_encode($path, JSON_THROW_ON_ERROR), 'lokasi_snapshot' => $asset->lokasi, 'direkam_pada' => now()]);
        });
    }

    public function mutateSnapshot(int $id, array $data, User $actor, bool $delete = false): AsesmenAset
    {
        return DB::transaction(function () use ($id, $data, $actor, $delete): AsesmenAset {
            $snapshot = $this->query('asesmen-aset', $actor)->findOrFail($id);
            $this->editable($snapshot->asesmen_id, $actor);
            if ($delete) {
                if (DB::table('RISIKO_ASET')->where('asesmen_aset_id',$id)->exists()) {
                    throw new BusinessRuleException('snapshot_dipakai','Snapshot masih digunakan risiko.');
                } $snapshot->delete();
            } else {
                $snapshot->fill($data)->save();
            }

return $snapshot;
        });
    }

    private function nextRiskCode(int $assessmentId): string
    {
        $number = 1;
        do {
            $code = 'R-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (Risiko::where('asesmen_id', $assessmentId)->where('kode', $code)->exists());

        return $code;
    }

    private function assertActiveMaster(string $table, int $id, string $field): void
    {
        $query = DB::table($table)->where('id', $id);
        if (Schema::hasColumn($table, 'aktif')) {
            $query->where('aktif', true);
        }
        if (! $query->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => ['Master tidak ditemukan atau sudah nonaktif.'],
            ]);
        }
    }
}
