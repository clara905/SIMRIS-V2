<?php

namespace App\Http\Requests;

use App\Services\MetodeRisikoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MetodeRisikoRequest extends ApiRequest
{
    public function authorize(): bool
    {
        Gate::authorize($this->isMethod('GET') || $this->route('action') === 'validasi' ? 'metode.view' : 'metode.write');
        if ($this->route('id')) {
            $class = MetodeRisikoService::MODELS[$this->route('resource')];
            $class::findOrFail($this->route('id'));
        }

        return true;
    }

    public function messages(): array
    {
        return [...parent::messages(), 'nilai.min' => 'Nilai skala minimal 1.', 'page.min' => 'Halaman minimal 1.', 'per_page.min' => 'Jumlah per halaman minimal 1.'];
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array'], 'q' => ['sometimes', 'string', 'max:255']];
        }
        if ($this->isMethod('DELETE') || in_array($this->route('action'), ['bekukan', 'validasi'], true)) {
            return [];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $text = [$required, 'string', 'max:255'];
        $long = [$required, 'string', 'max:10000'];
        $version = [$required, 'integer', Rule::exists('VERSI_METODE_RISIKO', 'id')];
        $level = [$required, 'integer', Rule::exists('TINGKAT_RISIKO', 'id')];
        if ($this->route('action') === 'duplikasi') {
            return ['versi' => ['required', 'string', 'max:255'], 'nama' => ['sometimes', 'string', 'max:255'], 'referensi' => ['sometimes', 'string', 'max:10000'], 'profil_penilaian' => ['sometimes', 'boolean'], 'dibekukan' => ['prohibited']];
        }
        if ($this->route('action') === 'generate') {
            return ['versi_metode_id' => $version, 'tingkat_risiko_id' => $level, 'rekomendasi_keputusan' => $text];
        }
        if ($this->route('resource') === 'tingkat-risiko') {
            return ['versi_metode_id' => $version, 'kode' => $text, 'nama' => $text, 'warna' => [$required, 'regex:/^#[0-9a-fA-F]{6}$/'], 'batas_min_skor' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:25'], 'batas_max_skor' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:25'], 'perlu_rtp' => ['sometimes', 'nullable', 'boolean'], 'perlu_persetujuan_pemilik' => ['sometimes', 'nullable', 'boolean'], 'kriteria_penerimaan' => ['sometimes', 'nullable', 'string', 'max:10000']];
        }

        return match ($this->route('resource')) {
            'versi-metode-risiko' => ['nama' => $text, 'versi' => $text, 'referensi' => $long, 'profil_penilaian' => ['sometimes', 'boolean'], 'dibekukan' => ['prohibited']],
            'skala-kemungkinan' => ['versi_metode_id' => $version, 'nilai' => [$required, 'integer', 'min:1', 'max:46340'], 'label' => $text, 'label_persentase' => $text, 'label_frekuensi' => $text, 'kriteria' => $long],
            'skala-dampak' => ['versi_metode_id' => $version, 'nilai' => [$required, 'integer', 'min:1', 'max:46340'], 'label' => $text, 'kriteria' => $long, 'kriteria_kerahasiaan' => ['sometimes', 'nullable', 'string', 'max:10000'], 'kriteria_integritas' => ['sometimes', 'nullable', 'string', 'max:10000'], 'kriteria_ketersediaan' => ['sometimes', 'nullable', 'string', 'max:10000'], 'kriteria_kepatuhan' => ['sometimes', 'nullable', 'string', 'max:10000'], 'kriteria_operasional' => ['sometimes', 'nullable', 'string', 'max:10000']],
            'area-dampak' => ['kode' => [...$text, Rule::unique('AREA_DAMPAK', 'kode')->ignore($this->route('id'))], 'nama' => $text],
            'kriteria-dampak' => ['skala_dampak_id' => [$required, 'integer', Rule::exists('SKALA_DAMPAK', 'id')], 'area_dampak_id' => [$required, 'integer', Rule::exists('AREA_DAMPAK', 'id')], 'arah_dampak' => [$required, Rule::in(['P', 'N'])], 'deskripsi' => $long],
            'tingkat-risiko' => ['versi_metode_id' => $version, 'kode' => $text, 'nama' => $text, 'warna' => [$required, 'regex:/^#[0-9a-fA-F]{6}$/']],
            'matriks-risiko' => ['versi_metode_id' => $version, 'kemungkinan_id' => [$required, 'integer', Rule::exists('SKALA_KEMUNGKINAN', 'id')], 'dampak_id' => [$required, 'integer', Rule::exists('SKALA_DAMPAK', 'id')], 'tingkat_risiko_id' => $level, 'skor' => ['prohibited'], 'rekomendasi_keputusan' => $text],
            default => [],
        };
    }
}
