<?php

namespace App\Workflow;

use App\Exceptions\BusinessRuleException;
use App\Models\AsesmenAset;
use App\Models\AsesmenRisiko;
use App\Models\PenilaianRisiko;
use App\Models\PeriodePenilaian;
use App\Models\ReviewAsesmen;
use App\Models\Risiko;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UsulanKeputusan;
use App\Services\AsesmenService;
use App\Services\AuditService;
use App\Services\KeputusanService;
use App\Services\PenangananService;
use App\Services\ReviewScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AsesmenWorkflow
{
    public function __construct(private AsesmenService $assessments, private ReviewScope $reviewers, private AuditService $audit) {}

    public function transition(int $id, string $action, array $data, User $actor): AsesmenRisiko
    {
        return DB::transaction(function () use ($id, $action, $data, $actor): AsesmenRisiko {
            DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
            $assessment = $this->assessments->assessments($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
            $transition = config('alur_asesmen.'.$assessment->status.'.'.$action);
            if (! $transition || $assessment->dibekukan_pada) {
                throw new BusinessRuleException('transisi_tidak_valid', 'Aksi tidak tersedia pada status asesmen sekarang.');
            }
            if ($transition['pelaku'] === 'PENYUSUN') {
                Gate::forUser($actor)->authorize('asesmen.write', [$assessment]);
                if (($transition['syarat'] ?? null) === 'residual') {
                    app(PenangananService::class)->assertResidualReady($assessment);
                } else {
                    $this->assertInitialComplete($assessment);
                }
            } elseif ($transition['pelaku'] === 'PIC_PENYUSUN') {
                app(PenangananService::class)->authorizeFinish($actor, $assessment);
                app(PenangananService::class)->assertFinished($assessment);
            } else {
                $this->reviewers->authorize($actor, $assessment->unit_id, $transition['pelaku']);
                if (($data['tahap'] ?? null) !== $transition['tahap']) {
                    throw new BusinessRuleException('transisi_tidak_valid', 'Tahap review tidak sesuai status asesmen.');
                }
                $this->separation($assessment, $actor, $transition['pelaku']);
                if ($action === 'TOLAK' && trim($data['catatan'] ?? '') === '') {
                    throw new BusinessRuleException('catatan_wajib', 'Catatan penolakan wajib diisi.');
                }
            }
            $before = $assessment->getAttributes();
            $next = $transition['ke'];
            $kind = UnitOrganisasi::findOrFail($assessment->unit_id)->jenis;
            $skip = config('alur_review.lewati.'.$kind, []);
            if ($next === 'MENUNGGU_KASUB' && in_array('KASUB', $skip, true)) {
                $next = 'MENUNGGU_KABID';
            }
            if ($next === 'MENUNGGU_KABID' && in_array('KABID', $skip, true)) {
                $next = 'AWAL_SELESAI';
            }
            if ($next === 'VERIFIKASI_KASUB' && in_array('KASUB', $skip, true)) {
                $next = 'VERIFIKASI_KABID';
            }
            if ($next === 'VERIFIKASI_KABID' && in_array('KABID', $skip, true)) {
                $next = 'MENUNGGU_KASATKER';
            }
            if ($next === 'KEMBALI') {
                if ($transition['pelaku'] === 'KASATKER' && ! $this->hasMitigation($assessment)) {
                    $next = 'DITOLAK';
                } else {
                    $next = $data['kembali_ke'] ?? null;
                    if (! in_array($next, ['PENANGANAN', 'RESIDUAL_DRAFT'], true)) {
                        throw new BusinessRuleException('kembali_ke_wajib', 'Tujuan penolakan harus PENANGANAN atau RESIDUAL_DRAFT.');
                    }
                }
            }
            if ($next === 'DISAHKAN') {
                $confirmed = request()->hasSession() ? request()->session()->get('auth.password_confirmed_at') : null;
                abort_unless(is_numeric($confirmed) && (int) $confirmed >= now()->timestamp - (int) config('security.password_confirmation_seconds'), 403, 'Konfirmasi ulang password diperlukan untuk pengesahan akhir.');
                $this->assertInitialComplete($assessment);
                if ($this->hasMitigation($assessment)) {
                    app(PenangananService::class)->assertFinished($assessment);
                    app(PenangananService::class)->assertResidualReady($assessment);
                }
                app(KeputusanService::class)->decide($assessment, $actor, $data['catatan'] ?? '');
                $assessment->dibekukan_pada = now();
            }
            if ($next === 'AWAL_SELESAI') {
                $next = $this->hasMitigation($assessment) ? 'PENANGANAN' : 'MENUNGGU_KASATKER';
                $assessment->dibekukan_awal_pada = now();
            }
            if ($next === 'DITOLAK') {
                $assessment->dibekukan_pada = now();
            }
            if (isset($transition['tahap'])) {
                ReviewAsesmen::create(['asesmen_id' => $id, 'reviewer_id' => $actor->id, 'tahap' => $transition['tahap'], 'keputusan' => $action, 'catatan' => $data['catatan'] ?? '', 'kembali_ke' => $data['kembali_ke'] ?? null, 'waktu' => now()]);
            }
            $assessment->workflowMutation = true;
            try {
                $assessment->status = $next;
                $assessment->save();
            } finally {
                $assessment->workflowMutation = false;
            }
            $after = $assessment->getAttributes();
            if ($action === 'TOLAK' && in_array($transition['tahap'] ?? '', ['KASUB_RESIDUAL', 'KABID_RESIDUAL', 'KASATKER_FINAL'], true)) {
                $after['residual_ditolak_ids'] = PenilaianRisiko::where('tahap', 'AKTUAL_SETELAH')->whereIn('risiko_id', Risiko::where('asesmen_id', $id)->select('id'))->pluck('id')->all();
            }
            $this->audit->record('ASESMEN_RISIKO', $id, $action === 'SETUJU' ? 'APPROVE' : ($action === 'TOLAK' ? 'REJECT' : 'UPDATE'), $before, $after, $actor->id);

            return $assessment;
        });
    }

    public function hasMitigation(AsesmenRisiko $assessment): bool
    {
        return UsulanKeputusan::where('keputusan', 'MITIGASI')->whereIn('penilaian_id', PenilaianRisiko::where('tahap', 'SAAT_INI')->whereIn('risiko_id', Risiko::where('asesmen_id', $assessment->id)->select('id'))->select('id'))->exists();
    }

    public function assertInitialComplete(AsesmenRisiko $assessment): void
    {
        $risks = Risiko::where('asesmen_id', $assessment->id)->get();
        $problems = [];
        if ($risks->isEmpty()) {
            $problems[] = 'Minimal satu risiko diperlukan.';
        }
        foreach ($risks as $risk) {
            foreach (['SAAT_INI', 'TARGET_SETELAH'] as $stage) {
                if (! PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', $stage)->exists()) {
                    $problems[] = 'Risiko '.$risk->kode.' belum memiliki penilaian '.$stage.'.';
                }
            }
            if (! UsulanKeputusan::whereIn('penilaian_id', PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', 'SAAT_INI')->select('id'))->exists()) {
                $problems[] = 'Risiko '.$risk->kode.' belum memiliki usulan keputusan.';
            }
        }
        if ($problems) {
            throw new BusinessRuleException('asesmen_belum_lengkap', 'Asesmen belum memenuhi syarat pengajuan.', $problems);
        }
    }

    public function separation(AsesmenRisiko $assessment, User $actor, string $role): void
    {
        if (PenilaianRisiko::where('dinilai_oleh', $actor->id)->whereIn('risiko_id', Risiko::where('asesmen_id', $assessment->id)->select('id'))->exists()) {
            throw new BusinessRuleException('pemisahan_tugas', 'Pembuat penilaian tidak dapat menjadi reviewer atau pemutus asesmen yang sama.');
        }
        if (in_array($role, ['KABID', 'KASATKER'], true) && ReviewAsesmen::where('asesmen_id', $assessment->id)->where('reviewer_id', $actor->id)->whereIn('tahap', ['KASUB_AWAL', 'KASUB_RESIDUAL'])->exists()) {
            throw new BusinessRuleException('pemisahan_tugas', 'Reviewer Kasub tidak dapat menjadi Kabid atau Kasatker pada asesmen yang sama.');
        }
    }

    public function revise(int $id, User $actor): AsesmenRisiko
    {
        return DB::transaction(function () use ($id, $actor): AsesmenRisiko {
            DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
            $source = $this->assessments->assessments($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('asesmen.write', [$source]);
            if ($source->status !== 'DITOLAK') {
                throw new BusinessRuleException('transisi_tidak_valid', 'Revisi hanya dapat dibuat dari asesmen DITOLAK.');
            }
            if (AsesmenRisiko::where('unit_id', $source->unit_id)->where('periode_id', $source->periode_id)->whereNotIn('status', ['DISAHKAN', 'DITOLAK'])->exists()) {
                throw new BusinessRuleException('seri_aktif_sudah_ada', 'Unit dan periode sudah memiliki asesmen aktif.');
            }
            $period = PeriodePenilaian::findOrFail($source->periode_id);
            if (today()->lt($period->tanggal_mulai) || today()->gt($period->tanggal_selesai)) {
                throw new BusinessRuleException('periode_tidak_berjalan', 'Periode revisi tidak sedang berjalan.');
            }
            $target = $source->replicate();
            $target->fill(['versi_sebelumnya_id' => $source->id, 'nomor_versi' => (int) AsesmenRisiko::where('seri_asesmen_id', $source->seri_asesmen_id)->max('nomor_versi') + 1, 'status' => 'DRAFT', 'dibuat_oleh' => $actor->id, 'dibuat_pada' => now(), 'dibekukan_pada' => null, 'dibekukan_awal_pada' => null]);
            $target->save();
            $snapshotMap = [];
            foreach (AsesmenAset::where('asesmen_id', $source->id)->get() as $snapshot) {
                $copy = $snapshot->replicate();
                $copy->asesmen_id = $target->id;
                $copy->save();
                $snapshotMap[$snapshot->id] = $copy->id;
            }
            foreach (Risiko::where('asesmen_id', $source->id)->get() as $risk) {
                $copy = $risk->replicate();
                $copy->asesmen_id = $target->id;
                $copy->save();
                foreach (DB::table('RISIKO_ASET')->where('risiko_id', $risk->id)->get() as $link) {
                    $new = ['risiko_id' => $copy->id, 'asesmen_aset_id' => $snapshotMap[$link->asesmen_aset_id]];
                    DB::table('RISIKO_ASET')->insert([...$new, 'created_at' => now(), 'updated_at' => now()]);
                    $this->audit->record('RISIKO_ASET', $copy->id, 'CREATE', null, $new, $actor->id);
                }
                foreach (PenilaianRisiko::where('risiko_id', $risk->id)->whereIn('tahap', ['SAAT_INI', 'TARGET_SETELAH'])->get() as $rating) {
                    $ratingCopy = $rating->replicate();
                    $ratingCopy->risiko_id = $copy->id;
                    $ratingCopy->save();
                    foreach (UsulanKeputusan::where('penilaian_id', $rating->id)->get() as $proposal) {
                        $proposalCopy = $proposal->replicate();
                        $proposalCopy->penilaian_id = $ratingCopy->id;
                        $proposalCopy->save();
                    }
                }
            }

            return $target;
        });
    }
}
