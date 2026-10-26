<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\AsesmenRisiko;
use App\Models\Pegawai;
use App\Models\PenilaianRisiko;
use App\Models\ProgresPenanganan;
use App\Models\RencanaPenanganan;
use App\Models\Risiko;
use App\Models\User;
use App\Models\UsulanKeputusan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PenangananService
{
    public function __construct(private AsesmenService $assessments, private ScopeUnit $scope, private DokumenService $documents) {}

    public function plans(User $actor): Builder
    {
        return RencanaPenanganan::whereIn('asesmen_id', $this->assessments->assessments($actor)->select('id'));
    }

    public function progress(User $actor): Builder
    {
        return ProgresPenanganan::whereIn('rencana_penanganan_id', $this->plans($actor)->select('id'));
    }

    public function mitigationIds(AsesmenRisiko $assessment): array
    {
        return PenilaianRisiko::whereIn('risiko_id', Risiko::where('asesmen_id', $assessment->id)->select('id'))->where('tahap', 'SAAT_INI')->whereIn('id', UsulanKeputusan::where('keputusan', 'MITIGASI')->select('penilaian_id'))->pluck('risiko_id')->map(fn ($id): int => (int) $id)->all();
    }

    public function authorizeProgress(User $actor, RencanaPenanganan $plan): void
    {
        $assessment = $this->assessments->assessments($actor)->findOrFail($plan->asesmen_id);
        $allowed = in_array($assessment->unit_id, $this->scope->idsForRoles($actor, ['ADMIN_SATKER']), true) || ($actor->pegawai_id === $plan->pic_id && in_array($assessment->unit_id, $this->scope->idsForRoles($actor, ['STAFF']), true));
        abort_unless($allowed, 403, 'Hanya ADMIN_SATKER atau STAFF PIC dalam scope dapat melaporkan progres.');
    }

    private function active(int $id, User $actor): AsesmenRisiko
    {
        $assessment = $this->assessments->assessments($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($assessment->status !== 'PENANGANAN' || $assessment->dibekukan_pada) {
            throw new BusinessRuleException('bukan_tahap_penanganan', 'Asesmen bukan dalam tahap PENANGANAN.');
        }

return $assessment;
    }

    public function plan(int $assessmentId, ?int $id, array $data, User $actor): RencanaPenanganan
    {
        return DB::transaction(function () use ($assessmentId, $id, $data, $actor): RencanaPenanganan {
            $assessment = $this->active($assessmentId, $actor);
            Gate::forUser($actor)->authorize('asesmen.write', [$assessment]);
            $plan = $id ? $this->plans($actor)->where('asesmen_id', $assessmentId)->findOrFail($id) : new RencanaPenanganan(['asesmen_id' => $assessmentId, 'status' => 'DIRENCANAKAN']);
            $risks = $data['risiko_ids'] ?? null;
            unset($data['risiko_ids']);
            if (isset($data['pic_id'])) {
                Pegawai::where('aktif', true)->whereIn('id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $this->scope->ids($actor))->where('mulai', '<=', today())->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today())))->findOrFail($data['pic_id']);
            }
            if ($risks !== null && array_diff($risks, $this->mitigationIds($assessment))) {
                throw new BusinessRuleException('risiko_penanganan_tidak_valid', 'Rencana hanya boleh menautkan risiko MITIGASI pada asesmen yang sama.');
            }
            $plan->fill($data)->save();
            if ($risks !== null) {
                $before = DB::table('RISIKO_PENANGANAN')->where('rencana_penanganan_id', $plan->id)->pluck('risiko_id')->all();
                DB::table('RISIKO_PENANGANAN')->where('rencana_penanganan_id', $plan->id)->delete();
                foreach ($risks as $risk) {
                    DB::table('RISIKO_PENANGANAN')->insert(['risiko_id' => $risk, 'rencana_penanganan_id' => $plan->id, 'created_at' => now(), 'updated_at' => now()]);
                } app(AuditService::class)->record('RISIKO_PENANGANAN', $plan->id, 'UPDATE', ['risiko_ids' => $before], ['risiko_ids' => $risks]);
            }

            return $plan;
        });
    }

    public function report(int $planId, array $data, User $actor): ProgresPenanganan
    {
        return DB::transaction(function () use ($planId, $data, $actor): ProgresPenanganan {
            $plan = $this->plans($actor)->findOrFail($planId);
            $this->active($plan->asesmen_id, $actor);
            $this->authorizeProgress($actor, $plan);
            $progress = ProgresPenanganan::create([...$data, 'rencana_penanganan_id' => $planId, 'dilaporkan_oleh' => $actor->id, 'dilaporkan_pada' => now()]);
            $plan->update(['status' => $data['status']]);

            return $progress;
        });
    }

    public function evidence(string $kind, int $id, array $data, User $actor): void
    {
        DB::transaction(function () use ($kind, $id, $data, $actor): void {
            if ($kind === 'progres') {
                $progress = $this->progress($actor)->findOrFail($id);
                $plan = $this->plans($actor)->findOrFail($progress->rencana_penanganan_id);
                $this->active($plan->asesmen_id, $actor);
                $this->authorizeProgress($actor, $plan);
                $table = 'BUKTI_PENANGANAN';
                $field = 'progres_penanganan_id';
            } else {
                $rating = $this->assessments->query('penilaian', $actor)->findOrFail($id);
                $assessment = $this->assessments->assessmentFor('penilaian', $id, $actor);
                Gate::forUser($actor)->authorize('asesmen.write', [$assessment]);
                $assessment = AsesmenRisiko::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
                $expected = $rating->tahap === 'AKTUAL_SETELAH' ? 'RESIDUAL_DRAFT' : 'DRAFT';
                if ($assessment->status !== $expected || $assessment->dibekukan_pada || ($expected === 'DRAFT' && $assessment->dibekukan_awal_pada)) {
                    throw new BusinessRuleException('bukti_dibekukan', 'Bukti tidak dapat diubah pada tahap ini.');
                }
                if ($rating->tahap === 'AKTUAL_SETELAH' && (int) PenilaianRisiko::where('risiko_id', $rating->risiko_id)->where('tahap', 'AKTUAL_SETELAH')->max('id') !== $rating->id) {
                    throw new BusinessRuleException('penilaian_bukan_terbaru', 'Bukti hanya ditambahkan ke penilaian aktual terbaru.');
                }
                $table = 'BUKTI_PENILAIAN';
                $field = 'penilaian_id';
            }
            $version = $this->documents->finalVersion((int) $data['dokumen_versi_id'], $actor);
            if (DB::table($table)->where($field, $id)->where('dokumen_versi_id', $version->id)->exists()) {
                throw new BusinessRuleException('bukti_sudah_ada', 'Bukti sudah ditautkan.');
            }
            DB::table($table)->insert([$field => $id, 'dokumen_versi_id' => $version->id, 'keterangan' => $data['keterangan'], 'created_at' => now(), 'updated_at' => now()]);
            app(AuditService::class)->record($table, $id, 'CREATE', null, ['dokumen_versi_id' => $version->id, 'keterangan' => $data['keterangan']]);
        });
    }

    public function assertFinished(AsesmenRisiko $assessment): void
    {
        $problems = [];
        foreach ($this->mitigationIds($assessment) as $riskId) {
            $plans = RencanaPenanganan::where('asesmen_id', $assessment->id)->whereIn('id', DB::table('RISIKO_PENANGANAN')->where('risiko_id', $riskId)->select('rencana_penanganan_id'))->get();
            if ($plans->isEmpty()) {
                $problems[] = 'Risiko '.$riskId.' belum mempunyai rencana penanganan.';
            }
            foreach ($plans as $plan) {
                if ($plan->status !== 'SELESAI' || ! DB::table('BUKTI_PENANGANAN')->whereIn('progres_penanganan_id', ProgresPenanganan::where('rencana_penanganan_id', $plan->id)->select('id'))->exists()) {
                    $problems[] = 'Rencana '.$plan->kode.' belum SELESAI dengan progres dan bukti.';
                }
            }
        }
        if ($problems) {
            throw new BusinessRuleException('penanganan_belum_selesai', 'Penanganan belum memenuhi syarat.', $problems);
        }
    }

    public function assertResidualReady(AsesmenRisiko $assessment): void
    {
        $problems = [];
        $rejection = DB::table('LOG_AUDIT')->where('entitas', 'ASESMEN_RISIKO')->where('entitas_id', $assessment->id)->where('aksi', 'REJECT')->orderByDesc('id')->first();
        $rejected = json_decode($rejection?->data_baru ?? '{}', true)['residual_ditolak_ids'] ?? [];
        foreach ($this->mitigationIds($assessment) as $riskId) {
            $rating = PenilaianRisiko::where('risiko_id', $riskId)->where('tahap', 'AKTUAL_SETELAH')->latest('id')->first();
            if (! $rating || in_array($rating->id, $rejected, true) || ! DB::table('BUKTI_PENILAIAN')->where('penilaian_id', $rating->id)->exists()) {
                $problems[] = 'Risiko '.$riskId.' memerlukan penilaian aktual terbaru dan bukti.';
            }
        }
        if ($problems) {
            throw new BusinessRuleException('residual_belum_lengkap', 'Residual belum memenuhi syarat.', $problems);
        }
    }

    public function authorizeFinish(User $actor, AsesmenRisiko $assessment): void
    {
        if (Gate::forUser($actor)->allows('asesmen.write',[$assessment])) {
            return;
        }
        $pic = $actor->pegawai_id && in_array($assessment->unit_id,$this->scope->idsForRoles($actor,['STAFF']),true) && RencanaPenanganan::where('asesmen_id',$assessment->id)->where('pic_id',$actor->pegawai_id)->exists();
        abort_unless($pic,403,'Hanya PIC atau penyusun dalam scope dapat menyelesaikan penanganan.');
    }
}
