<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\AreaDampak;
use App\Models\KriteriaDampak;
use App\Models\MatriksRisiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\TingkatRisiko;
use App\Models\User;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MetodeRisikoService
{
    public const MODELS = ['versi-metode-risiko' => VersiMetodeRisiko::class, 'skala-kemungkinan' => SkalaKemungkinan::class, 'skala-dampak' => SkalaDampak::class, 'area-dampak' => AreaDampak::class, 'kriteria-dampak' => KriteriaDampak::class, 'tingkat-risiko' => TingkatRisiko::class, 'matriks-risiko' => MatriksRisiko::class];

    /** All method and global-area writers use the same pre-existing lock, including model observers. */
    public function lock(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Mutasi metode harus berada dalam transaksi.');
        }
        DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
    }

    public function assertDraft(int $id): VersiMetodeRisiko
    {
        $version = VersiMetodeRisiko::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($version->dibekukan) {
            throw new BusinessRuleException('versi_sudah_beku', 'Versi metode sudah beku dan tidak dapat diubah.');
        }

        return $version;
    }

    /** R12 guard for assessment creation in Tahap 7; caller must hold its assessment transaction. */
    public function forAssessment(int $id): VersiMetodeRisiko
    {
        $this->lock();
        $version = VersiMetodeRisiko::whereKey($id)->lockForUpdate()->firstOrFail();
        if (! $version->dibekukan) {
            throw new BusinessRuleException('versi_belum_beku', 'Asesmen hanya dapat memakai versi metode yang sudah dibekukan.');
        }

        return $version;
    }

    public function prepareCell(MatriksRisiko $cell): void
    {
        $possibility = SkalaKemungkinan::findOrFail($cell->kemungkinan_id);
        $impact = SkalaDampak::findOrFail($cell->dampak_id);
        $level = TingkatRisiko::findOrFail($cell->tingkat_risiko_id);
        foreach ([$possibility, $impact, $level] as $child) {
            if ($child->versi_metode_id !== $cell->versi_metode_id) {
                throw new BusinessRuleException('matriks_beda_versi', 'Skala dan tingkat risiko harus berasal dari versi matriks yang sama.');
            }
        }
        $cell->skor = $possibility->nilai * $impact->nilai;
    }

    public function listing(string $resource, array $input): mixed
    {
        Gate::authorize('metode.view');
        $class = self::MODELS[$resource];
        $allowed = ['id', ...(new $class)->getFillable(), 'created_at', 'updated_at'];
        $query = $class::query();
        foreach ($input['filter'] ?? [] as $field => $value) {
            if (! in_array($field, $allowed, true) || ! is_scalar($value)) {
                throw ValidationException::withMessages(['filter' => ['Kolom atau nilai filter tidak diperbolehkan.']]);
            }
            $query->where($field, $value);
        }
        $sort = $input['sort'] ?? 'id';
        if (! in_array(ltrim($sort, '-'), $allowed, true)) {
            throw ValidationException::withMessages(['sort' => ['Kolom urutan tidak diperbolehkan.']]);
        }

        return $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id')->paginate((int) ($input['per_page'] ?? 20))->withQueryString();
    }

    public function mutate(string $resource, ?int $id, array $data, string $action, User $actor): Model
    {
        try {
            return DB::transaction(function () use ($resource, $id, $data, $action, $actor): Model {
                $this->lock();
                Gate::forUser($actor->refresh())->authorize('metode.write');
                $class = self::MODELS[$resource];
                $model = $id ? $class::whereKey($id)->lockForUpdate()->firstOrFail() : new $class;
                if ($action === 'duplikasi') {
                    return $this->duplicate($model, $data);
                }
                if ($model instanceof VersiMetodeRisiko && $id) {
                    $this->assertDraft($id);
                }
                if ($action === 'bekukan') {
                    $this->assertValid($model);
                    $model->dibekukan = true;
                    $model->save();
                } elseif ($action === 'delete') {
                    if ($model instanceof VersiMetodeRisiko) {
                        $this->deleteChildren($model);
                    }
                    $model->delete();
                } elseif ($action === 'generate') {
                    return $this->generate($data);
                } else {
                    $model->fill($data)->save();
                }

                return $model;
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505', '23503'], true)) {
                throw new BusinessRuleException('integritas_data', 'Data duplikat atau masih digunakan oleh data lain.');
            }
            throw $exception;
        }
    }

    private function deleteChildren(VersiMetodeRisiko $version): void
    {
        if (DB::table('ASESMEN_RISIKO')->where('versi_metode_id', $version->id)->exists()) {
            throw new BusinessRuleException('versi_dipakai_asesmen', 'Versi yang dipakai asesmen tidak dapat dihapus.');
        }
        foreach (MatriksRisiko::where('versi_metode_id', $version->id)->get() as $cell) {
            $cell->delete();
        }
        foreach (KriteriaDampak::whereIn('skala_dampak_id', SkalaDampak::where('versi_metode_id', $version->id)->select('id'))->get() as $criterion) {
            $criterion->delete();
        }
        foreach ([SkalaKemungkinan::class, SkalaDampak::class, TingkatRisiko::class] as $class) {
            foreach ($class::where('versi_metode_id', $version->id)->orderByDesc($class === TingkatRisiko::class ? 'id' : 'nilai')->get() as $child) {
                $child->delete();
            }
        }
    }

    /** Generate only missing cells; the caller explicitly chooses their initial level and decision. */
    private function generate(array $data): VersiMetodeRisiko
    {
        $version = $this->assertDraft((int) $data['versi_metode_id']);
        $possibilities = SkalaKemungkinan::where('versi_metode_id', $version->id)->get();
        $impacts = SkalaDampak::where('versi_metode_id', $version->id)->get();
        $level = TingkatRisiko::findOrFail($data['tingkat_risiko_id']);
        if ($level->versi_metode_id !== $version->id) {
            throw new BusinessRuleException('matriks_beda_versi', 'Tingkat risiko harus berasal dari versi matriks yang sama.');
        }
        if ($possibilities->isEmpty() || $impacts->isEmpty()) {
            throw new BusinessRuleException('skala_belum_lengkap', 'Isi kedua skala sebelum generate matriks.');
        }
        foreach ($possibilities as $possibility) {
            foreach ($impacts as $impact) {
                if (! MatriksRisiko::where('versi_metode_id', $version->id)->where('kemungkinan_id', $possibility->id)->where('dampak_id', $impact->id)->exists()) {
                    MatriksRisiko::create(['versi_metode_id' => $version->id, 'kemungkinan_id' => $possibility->id, 'dampak_id' => $impact->id, 'tingkat_risiko_id' => $level->id, 'rekomendasi_keputusan' => $data['rekomendasi_keputusan']]);
                }
            }
        }

        return $version;
    }

    private function duplicate(VersiMetodeRisiko $source, array $data): VersiMetodeRisiko
    {
        $target = VersiMetodeRisiko::create(['nama' => $data['nama'] ?? $source->nama, 'versi' => $data['versi'], 'referensi' => $data['referensi'] ?? $source->referensi, 'dibekukan' => false]);
        $maps = [];
        foreach ([SkalaKemungkinan::class, SkalaDampak::class, TingkatRisiko::class] as $class) {
            foreach ($class::where('versi_metode_id', $source->id)->orderBy($class === TingkatRisiko::class ? 'id' : 'nilai')->get() as $child) {
                $copy = $child->replicate();
                $copy->versi_metode_id = $target->id;
                $copy->save();
                $maps[$class][$child->id] = $copy->id;
            }
        }
        foreach (KriteriaDampak::whereIn('skala_dampak_id', array_keys($maps[SkalaDampak::class] ?? []))->get() as $criterion) {
            $copy = $criterion->replicate();
            $copy->skala_dampak_id = $maps[SkalaDampak::class][$criterion->skala_dampak_id];
            $copy->save();
        }
        foreach (MatriksRisiko::where('versi_metode_id', $source->id)->get() as $cell) {
            $copy = $cell->replicate();
            $copy->versi_metode_id = $target->id;
            $copy->kemungkinan_id = $maps[SkalaKemungkinan::class][$cell->kemungkinan_id];
            $copy->dampak_id = $maps[SkalaDampak::class][$cell->dampak_id];
            $copy->tingkat_risiko_id = $maps[TingkatRisiko::class][$cell->tingkat_risiko_id];
            $copy->save();
        }

        return $target;
    }

    /** @return array{valid: bool, masalah: list<string>} */
    public function validation(VersiMetodeRisiko $version): array
    {
        $problems = [];
        $possibilities = SkalaKemungkinan::where('versi_metode_id', $version->id)->orderBy('nilai')->get();
        $impacts = SkalaDampak::where('versi_metode_id', $version->id)->orderBy('nilai')->get();
        foreach (['kemungkinan' => $possibilities, 'dampak' => $impacts] as $name => $scales) {
            if ($scales->isEmpty() || $scales->pluck('nilai')->all() !== range(1, $scales->count())) {
                $problems[] = 'Skala '.$name.' harus terisi dan berurutan mulai dari 1.';
            }
        }
        $levels = TingkatRisiko::where('versi_metode_id', $version->id)->pluck('id');
        if ($levels->isEmpty()) {
            $problems[] = 'Minimal satu tingkat risiko diperlukan.';
        }
        $cells = MatriksRisiko::where('versi_metode_id', $version->id)->get();
        foreach ($cells as $cell) {
            $possibility = $possibilities->firstWhere('id', $cell->kemungkinan_id);
            $impact = $impacts->firstWhere('id', $cell->dampak_id);
            if (! $possibility || ! $impact || ! $levels->contains($cell->tingkat_risiko_id) || $cell->skor !== $possibility->nilai * $impact->nilai) {
                $problems[] = 'Sel matriks '.$cell->id.' memiliki versi, tingkat, atau skor tidak valid.';
            }
        }
        $pairs = $cells->groupBy(fn (MatriksRisiko $cell): string => $cell->kemungkinan_id.':'.$cell->dampak_id);
        foreach ($possibilities as $possibility) {
            foreach ($impacts as $impact) {
                if (($pairs->get($possibility->id.':'.$impact->id)?->count() ?? 0) !== 1) {
                    $problems[] = 'Pasangan kemungkinan '.$possibility->nilai.' dan dampak '.$impact->nilai.' harus mempunyai tepat satu sel matriks.';
                }
            }
        }
        $criteria = KriteriaDampak::whereIn('skala_dampak_id', $impacts->pluck('id'))->get();
        // Frozen versions retain the global areas represented by their immutable criteria.
        $areas = $version->dibekukan ? AreaDampak::whereIn('id', $criteria->pluck('area_dampak_id'))->get() : AreaDampak::all();
        foreach ($impacts as $impact) {
            foreach ($areas as $area) {
                if (! $criteria->contains(fn (KriteriaDampak $criterion): bool => $criterion->skala_dampak_id === $impact->id && $criterion->area_dampak_id === $area->id && in_array($criterion->arah_dampak, ['P', 'N'], true) && trim($criterion->deskripsi) !== '')) {
                    $problems[] = 'Kriteria dampak '.$impact->nilai.' untuk area '.$area->kode.' belum terisi.';
                }
            }
        }

        return ['valid' => $problems === [], 'masalah' => $problems];
    }

    public function assertValid(VersiMetodeRisiko $version): void
    {
        $report = $this->validation($version);
        if (! $report['valid']) {
            throw new BusinessRuleException('metode_belum_valid','Versi metode belum memenuhi syarat pembekuan.',$report['masalah']);
        }
    }
}
