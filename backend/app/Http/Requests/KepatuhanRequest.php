<?php

namespace App\Http\Requests;

use App\Services\KepatuhanService;
use App\Services\TautanKepatuhanService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KepatuhanRequest extends ApiRequest
{
    public function authorize(): bool
    {
        Gate::authorize('asesmen.view');
        $service = app(KepatuhanService::class);
        $resource = $this->route('resource');
        $actor = $this->user();
        $model = null;
        if ($this->route('relation')) {
            app(TautanKepatuhanService::class)->source($resource, (int) $this->route('id'), $actor);

            return true;
        }
        if ($this->route('id')) {
            $model = $service->query($resource, $actor)->findOrFail($this->route('id'));
        } elseif ($this->route('parent')) {
            $model = $service->query(KepatuhanService::PARENTS[$resource][0], $actor)->findOrFail($this->route('parent'));
        }
        if ($resource === 'sop-versi' && $this->route('action') === 'review') {
            Gate::forUser($actor)->authorize('kepatuhan.review-sop');
        }
        if (! $this->isMethod('GET') && $this->route('action') !== 'review') {
            $admin = $this->isMethod('DELETE') || $this->route('action') === 'ajukan';
            if ($model) {
                Gate::authorize('kepatuhan.write', [$service->unit($model), $admin, $resource]);
            } else {
                $unit = (int) ($this->input('unit_id') ?? $this->input('unit_pemilik_id') ?? 0);
                Gate::authorize('kepatuhan.write', [$unit, $admin, $resource]);
            }
        }

return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array']];
        }
        if ($this->route('relation')) {
            [$table,$source,$target,$column,$fields] = TautanKepatuhanService::LINKS[$this->route('resource')][$this->route('relation')];
            $rules = [$column => ['required', 'integer']];
            foreach ($fields as $field) {
                $rules[$field] = [$this->isMethod('DELETE') && $field !== 'kontrol_internal_id' ? 'sometimes' : 'required', str_ends_with($field, '_id') ? 'integer' : ($field === 'diperiksa_pada' ? 'date' : 'string')];
            }

return $rules;
        }
        if ($this->route('action') === 'ajukan' || $this->isMethod('DELETE')) {
            return [];
        }
        if ($this->route('action') === 'review') {
            $tahapan = $this->route('resource') === 'sop-versi' ? ['SUPER_ADMIN'] : ['KASUB', 'KABID', 'KASATKER'];

            return ['tahap' => ['required', Rule::in($tahapan)], 'keputusan' => ['required', Rule::in(['SETUJU', 'TOLAK'])], 'catatan' => ['required_if:keputusan,TOLAK', 'nullable', 'string', 'max:10000']];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $text = [$required, 'string', 'max:10000'];
        $short = [$required, 'string', 'max:255'];
        $protected = ['status' => ['prohibited'], 'dibuat_oleh' => ['prohibited'], 'dibuat_pada' => ['prohibited'], 'dibekukan_pada' => ['prohibited'], 'nomor_versi' => ['prohibited']];
        $rules = match ($this->route('resource')) {
            'kontrol-internal' => ['unit_pemilik_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'], 'kode' => $short, 'nama' => $short, 'jenis' => [$required, Rule::in(['SOP', 'KEBIJAKAN', 'TEKNIS', 'FISIK', 'LAINNYA'])], 'deskripsi' => $text, 'aktif' => ['sometimes', 'boolean']],
            'sop' => ['unit_pemilik_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'], 'kode' => $short, 'judul' => $short],
            'soa' => ['unit_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'], 'periode_id' => [$required, 'integer', Rule::exists('PERIODE_PENILAIAN', 'id')], 'standar_id' => [$required, 'integer', Rule::exists('STANDAR_KONTROL', 'id')], 'ruang_lingkup' => $text],
            'sop-versi' => ['dokumen_versi_id' => [$required, 'integer'], 'tanggal_berlaku' => [$required, 'date_format:Y-m-d']],
            'soa-versi' => [],
            'soa-detail' => ['kontrol_id' => [$required, 'integer'], 'penanggung_jawab_id' => ['sometimes', 'nullable', 'integer'], 'berlaku' => [$required, 'boolean'], 'alasan' => $text, 'status_penerapan' => $short],
            'soa-benchmark' => ['regulasi_id' => [$required, 'integer', Rule::exists('REGULASI', 'id')], 'rujukan' => $short],
            default => []
        };

        return [...$protected, ...$rules];
    }
}
