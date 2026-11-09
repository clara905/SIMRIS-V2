<?php

namespace App\Http\Requests;

use App\Services\AsesmenService;
use App\Services\ScopeUnit;
use App\Models\VersiMetodeRisiko;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AsesmenRequest extends ApiRequest
{
    public function authorize(): bool
    {
        Gate::authorize('asesmen.view');
        $service = app(AsesmenService::class);
        $assessment = null;
        if ($this->route('assessment')) {
            $assessment = $service->assessments($this->user())->findOrFail($this->route('assessment'));
        } elseif ($this->route('risk')) {
            $assessment = $service->assessmentFor('risiko', (int) $this->route('risk'), $this->user());
        } elseif ($this->route('id')) {
            $assessment = $service->assessmentFor($this->route('resource'), (int) $this->route('id'), $this->user());
        }
        if (! $this->isMethod('GET')) {
            if ($assessment) {
                Gate::authorize('asesmen.write', [$assessment]);
            } else {
                abort_unless(app(ScopeUnit::class)->hasRole($this->user(), ['STAFF']), 403, 'Hanya STAFF dapat membuat asesmen.');
            }
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array'], 'q' => ['sometimes', 'string', 'max:255']];
        }
        if ($this->isMethod('DELETE')) {
            return [];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $text = [$required, 'string', 'max:10000'];
        $protected = ['status' => ['prohibited'], 'dibuat_oleh' => ['prohibited'], 'dibekukan_awal_pada' => ['prohibited'], 'dibekukan_pada' => ['prohibited']];
        $riskRules = ['ancaman_id' => [$required, 'integer', Rule::exists('ANCAMAN', 'id')], 'kerentanan_id' => [$required, 'integer', Rule::exists('KERENTANAN', 'id')], 'area_dampak_id' => [$required, 'integer', Rule::exists('AREA_DAMPAK', 'id')], 'pemilik_risiko_id' => [$required, 'integer'], 'kode' => ['prohibited'], 'skenario' => $text, 'uraian_kerentanan' => $text, 'uraian_dampak' => $text, 'arah_dampak' => [$required, Rule::in(['P', 'N'])], 'konteks' => ['sometimes', 'nullable', 'string', 'max:30'], 'konsekuensi_kerahasiaan' => ['sometimes', 'nullable', 'string', 'max:10000'], 'konsekuensi_integritas' => ['sometimes', 'nullable', 'string', 'max:10000'], 'konsekuensi_ketersediaan' => ['sometimes', 'nullable', 'string', 'max:10000'], 'konsekuensi_kepatuhan' => ['sometimes', 'nullable', 'string', 'max:10000'], 'konsekuensi_operasional' => ['sometimes', 'nullable', 'string', 'max:10000'], 'asesmen_aset_ids' => ['sometimes', 'array', 'max:100'], 'asesmen_aset_ids.*' => ['integer', 'distinct'], 'asesmen_id' => ['prohibited']];
        if ($this->riskUsesNewProfile()) {
            $riskRules['konteks'] = [$required, Rule::in(['STRATEGIS', 'OPERASIONAL', 'PELAPORAN', 'KEPATUHAN'])];
            foreach (['konsekuensi_kerahasiaan', 'konsekuensi_integritas', 'konsekuensi_ketersediaan'] as $field) {
                $riskRules[$field] = [$required, 'string', 'max:10000'];
            }
            $riskRules['asesmen_aset_ids'] = [$required, 'array', 'min:1', 'max:100'];
        }
        $ratingRules = [
            'matriks_id' => ['sometimes', 'integer', Rule::exists('MATRIKS_RISIKO', 'id')],
            'kemungkinan_nilai' => ['required_without:matriks_id', 'required_with:dampak_nilai', 'integer', 'min:1', 'max:5'],
            'dampak_nilai' => ['required_without:matriks_id', 'required_with:kemungkinan_nilai', 'integer', 'min:1', 'max:5'],
            'tahap' => ['required', Rule::in(['SAAT_INI', 'TARGET_SETELAH', 'AKTUAL_SETELAH'])],
            'alasan_kemungkinan' => $text,
            'alasan_dampak' => $text,
            'kesimpulan' => $text,
            'alasan_perubahan_level' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'dinilai_oleh' => ['prohibited'],
        ];

        return [...$protected, ...match ($this->route('resource')) {
            'asesmen' => ['unit_id' => ['prohibited'], 'periode_id' => $this->isMethod('POST') ? ['required', 'integer', Rule::exists('PERIODE_PENILAIAN', 'id')] : ['prohibited'], 'versi_metode_id' => ['prohibited'], 'tujuan' => $text, 'ruang_lingkup' => $text, 'seri_asesmen_id' => ['prohibited'], 'nomor_versi' => ['prohibited']],
            'risiko' => $riskRules,
            'asesmen-aset' => ['aset_id' => $this->isMethod('POST') ? ['required', 'integer'] : ['prohibited'], 'nilai_kekritisan' => [$required, 'numeric', 'min:0', 'max:99999999999.9999'], 'dasar_kekritisan' => $text, 'nama_snapshot' => ['prohibited'], 'kategori_snapshot' => ['prohibited'], 'klasifikasi_informasi_snapshot' => ['prohibited'], 'jalur_unit_snapshot' => ['prohibited'], 'lokasi_snapshot' => ['prohibited']],
            'penilaian' => $ratingRules,
            'usulan-keputusan' => ['keputusan' => ['required', Rule::in(['TERIMA', 'MODIFIKASI', 'MITIGASI'])], 'alasan' => $text, 'penilaian_id' => ['prohibited'], 'diusulkan_oleh' => ['prohibited']],
            default => []
        }];
    }

    private function riskUsesNewProfile(): bool
    {
        if ($this->route('resource') !== 'risiko') {
            return false;
        }
        $user = $this->user();
        if (! $user) {
            return false;
        }
        $service = app(AsesmenService::class);
        $assessment = $this->route('assessment')
            ? $service->assessments($user)->findOrFail($this->route('assessment'))
            : ($this->route('id') ? $service->assessmentFor('risiko', (int) $this->route('id'), $user) : null);

        return $assessment !== null && (bool) VersiMetodeRisiko::whereKey($assessment->versi_metode_id)->value('profil_penilaian');
    }
}
