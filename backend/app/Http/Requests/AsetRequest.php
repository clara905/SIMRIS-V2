<?php

namespace App\Http\Requests;

use App\Models\Aset;
use App\Models\KategoriAset;
use App\Services\AsetService;
use App\Services\ScopeUnit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AsetRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $resource = $this->route('resource');
        if ($resource === 'aset-sinkron') {
            Gate::authorize('aset.sync');

            return true;
        }
        Gate::authorize('aset.view');
        $service = app(AsetService::class);
        $row = $this->route('id') ? ($resource === 'penugasan-aset' ? $service->assignments($this->user()) : $service->query($this->user()))->findOrFail($this->route('id')) : null;
        if (! $this->isMethod('GET')) {
            if ($row) {
                $asset = $row instanceof Aset ? $row : $service->query($this->user())->findOrFail($row->aset_id);
                Gate::authorize('aset.write', [(int) $asset->unit_pemilik_id]);
            } else {
                abort_unless(app(ScopeUnit::class)->hasRole($this->user(), ['ADMIN_SATKER']), 403, 'Hanya ADMIN_SATKER dapat mengubah data aset lokal.');
            }
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array'], 'q' => ['sometimes', 'string', 'max:255']];
        }
        if ($this->isMethod('DELETE') || $this->route('resource') === 'aset-sinkron') {
            return [];
        }
        if ($this->route('resource') === 'penugasan-aset') {
            $required = $this->route('id') ? 'sometimes' : 'required';

            return ['aset_id' => [$required, 'integer'], 'pegawai_id' => [$required, 'integer'], 'jenis_tanggung_jawab' => [$required, 'string', 'max:255'], 'mulai' => [$required, 'date_format:Y-m-d'], 'selesai' => ['sometimes', 'nullable', 'date_format:Y-m-d']];
        }
        $rules = ['unit_pemilik_id' => ['sometimes', 'integer'], 'kategori_id' => ['sometimes', 'integer', Rule::exists('KATEGORI_ASET', 'id')], 'klasifikasi_informasi_id' => ['sometimes', 'nullable', 'integer', Rule::exists('KLASIFIKASI_INFORMASI', 'id')], 'lokasi' => ['sometimes', 'string', 'max:255'], 'deskripsi' => ['sometimes', 'string', 'max:10000']];
        foreach (array_diff((new Aset)->getFillable(), AsetService::LOCAL) as $field) {
            $rules[$field] = ['prohibited'];
        }
        if ($this->route('action') === 'tetapkan') {
            $rules['ids'] = ['required', 'array', 'min:1', 'max:100'];
            $rules['ids.*'] = ['integer', 'distinct'];
            $rules['unit_pemilik_id'] = ['required', 'integer'];
            $rules['kategori_id'] = ['required', 'integer', Rule::exists('KATEGORI_ASET', 'id'), Rule::notIn(KategoriAset::where('kode', 'BELUM_DIKATEGORIKAN')->pluck('id')->all())];
        }

        return $rules;
    }
}
