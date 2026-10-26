<?php

namespace App\Http\Requests;

use App\Services\AsesmenService;
use App\Services\ScopeUnit;
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
                abort_unless(app(ScopeUnit::class)->hasRole($this->user(), ['STAFF', 'ADMIN_SATKER']), 403, 'Hanya penyusun dapat membuat asesmen.');
            }
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array']];
        }
        if ($this->isMethod('DELETE')) {
            return [];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $text = [$required, 'string', 'max:10000'];
        $protected = ['status' => ['prohibited'], 'dibuat_oleh' => ['prohibited'], 'dibekukan_awal_pada' => ['prohibited'], 'dibekukan_pada' => ['prohibited']];

        return [...$protected, ...match ($this->route('resource')) {
            'asesmen' => ['unit_id' => $this->isMethod('POST') ? ['required', 'integer'] : ['prohibited'], 'periode_id' => $this->isMethod('POST') ? ['required', 'integer', Rule::exists('PERIODE_PENILAIAN', 'id')] : ['prohibited'], 'versi_metode_id' => $this->isMethod('POST') ? ['required', 'integer', Rule::exists('VERSI_METODE_RISIKO', 'id')] : ['prohibited'], 'tujuan' => $text, 'ruang_lingkup' => $text, 'seri_asesmen_id' => ['prohibited'], 'nomor_versi' => ['prohibited']],
            'risiko' => ['ancaman_id' => [$required, 'integer', Rule::exists('ANCAMAN', 'id')], 'kerentanan_id' => [$required, 'integer', Rule::exists('KERENTANAN', 'id')], 'area_dampak_id' => [$required, 'integer', Rule::exists('AREA_DAMPAK', 'id')], 'pemilik_risiko_id' => [$required, 'integer'], 'kode' => [$required, 'string', 'max:255'], 'skenario' => $text, 'uraian_kerentanan' => $text, 'uraian_dampak' => $text, 'arah_dampak' => [$required, Rule::in(['P', 'N'])], 'asesmen_aset_ids' => ['sometimes', 'array', 'max:100'], 'asesmen_aset_ids.*' => ['integer', 'distinct'], 'asesmen_id' => ['prohibited']],
            'asesmen-aset' => ['aset_id' => $this->isMethod('POST') ? ['required', 'integer'] : ['prohibited'], 'nilai_kekritisan' => [$required, 'numeric', 'min:0', 'max:99999999999.9999'], 'dasar_kekritisan' => $text, 'nama_snapshot' => ['prohibited'], 'kategori_snapshot' => ['prohibited'], 'klasifikasi_informasi_snapshot' => ['prohibited'], 'jalur_unit_snapshot' => ['prohibited'], 'lokasi_snapshot' => ['prohibited']],
            'penilaian' => ['matriks_id' => ['required', 'integer', Rule::exists('MATRIKS_RISIKO', 'id')], 'tahap' => ['required', Rule::in(['SAAT_INI', 'TARGET_SETELAH', 'AKTUAL_SETELAH'])], 'alasan_kemungkinan' => $text, 'alasan_dampak' => $text, 'kesimpulan' => $text, 'dinilai_oleh' => ['prohibited']],
            'usulan-keputusan' => ['keputusan' => ['required', Rule::in(['MITIGASI', 'TERIMA', 'HINDARI', 'ALIHKAN'])], 'alasan' => $text, 'penilaian_id' => ['prohibited'], 'diusulkan_oleh' => ['prohibited']],
            default => []
        }];
    }
}
