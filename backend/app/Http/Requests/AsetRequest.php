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
    private const CONDITIONS = ['BAIK', 'RUSAK_BIASA', 'RUSAK_BERAT', 'TIDAK_DIKETAHUI'];

    private const STATUSES = ['AKTIF', 'NONAKTIF'];

    public function authorize(): bool
    {
        $resource = $this->route('resource');
        if ($resource === 'aset-pilihan') {
            Gate::authorize('aset.view');

            return true;
        }
        if ($resource === 'aset-sinkron') {
            Gate::authorize('aset.sync');

            return true;
        }
        Gate::authorize('aset.view');
        if ($resource === 'penugasan-aset' && ! $this->isMethod('GET')) {
            abort_unless(app(ScopeUnit::class)->hasRole($this->user(), ['ADMIN_SATKER']), 403, 'Hanya ADMIN_SATKER dapat mengelola penugasan aset.');
        }
        $service = app(AsetService::class);
        $row = $this->route('id') ? ($resource === 'penugasan-aset' ? $service->assignments($this->user()) : $service->query($this->user()))->findOrFail($this->route('id')) : null;
        if (! $this->isMethod('GET')) {
            if ($row) {
                $asset = $row instanceof Aset ? $row : $service->query($this->user())->findOrFail($row->aset_id);
                Gate::authorize('aset.write', [(int) $asset->unit_pemilik_id]);
                if ($resource === 'aset' && app(ScopeUnit::class)->hasRole($this->user(), ['KASUB']) && ! app(ScopeUnit::class)->hasRole($this->user(), ['ADMIN_SATKER']) && ! app(ScopeUnit::class)->isSuper($this->user()) && ! $service->isManual($asset)) {
                    abort(403, 'Kasub hanya dapat mengubah inventaris lokal.');
                }
            } else {
                if ($resource === 'aset' && $this->isMethod('POST') && $this->route('action') !== 'tetapkan') {
                    Gate::authorize('aset.create');
                } else {
                    abort_unless(app(ScopeUnit::class)->hasRole($this->user(), ['ADMIN_SATKER']), 403, 'Hanya ADMIN_SATKER dapat mengelola penugasan aset.');
                }
            }
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array'], 'q' => ['sometimes', 'string', 'max:255']];
        }
        if ($this->isMethod('DELETE') || $this->route('resource') === 'aset-sinkron' || $this->route('resource') === 'aset-pilihan') {
            return [];
        }
        if ($this->route('resource') === 'penugasan-aset') {
            $required = $this->route('id') ? 'sometimes' : 'required';

            return ['aset_id' => [$required, 'integer'], 'pegawai_id' => [$required, 'integer'], 'jenis_tanggung_jawab' => [$required, 'string', 'max:255'], 'mulai' => [$required, 'date_format:Y-m-d'], 'selesai' => ['sometimes', 'nullable', 'date_format:Y-m-d']];
        }
        if ($this->route('action') === 'tetapkan') {
            return [
                'ids' => ['required', 'array', 'min:1', 'max:100'],
                'ids.*' => ['integer', 'distinct'],
                'unit_pemilik_id' => ['required', 'integer'],
                'kategori_id' => ['required', 'integer', Rule::exists('KATEGORI_ASET', 'id'), Rule::notIn(KategoriAset::where('kode', 'BELUM_DIKATEGORIKAN')->pluck('id')->all())],
            ];
        }
        $required = $this->route('id') ? 'sometimes' : 'required';
        $service = app(AsetService::class);
        $manual = $this->isMethod('POST');
        if ($this->route('id')) {
            $asset = $service->query($this->user())->findOrFail($this->route('id'));
            $manual = $service->isManual($asset);
        }
        $rules = [
            'unit_pemilik_id' => ['sometimes', 'integer', Rule::exists('UNIT_ORGANISASI', 'id')],
            'kategori_id' => [$required, 'integer', Rule::exists('KATEGORI_ASET', 'id'), Rule::notIn(KategoriAset::where('kode', 'BELUM_DIKATEGORIKAN')->pluck('id')->all())],
            'klasifikasi_informasi_id' => ['sometimes', 'nullable', 'integer', Rule::exists('KLASIFIKASI_INFORMASI', 'id')],
            'lokasi' => [$this->route('id') ? 'sometimes' : 'required', 'string', 'max:255'],
            'deskripsi' => [$this->route('id') ? 'sometimes' : 'present', 'nullable', 'string', 'max:10000'],
        ];
        if ($manual) {
            $rules += [
                'kode_aset' => ['sometimes', $this->route('id') ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('ASET', 'kode_aset')->ignore($this->route('id'))],
                'nama' => [$required, 'string', 'max:255'],
                'merk' => ['sometimes', 'nullable', 'string', 'max:255'],
                'nomor_seri' => ['sometimes', 'nullable', 'string', 'max:255'],
                'jumlah' => ['sometimes', 'integer', 'min:0'],
                'kondisi' => [$required, Rule::in(self::CONDITIONS)],
                'lokasi' => [$required, 'string', 'max:255'],
                'tanggal_pengadaan' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
                'status' => [$required, Rule::in(self::STATUSES)],
            ];
        } else {
            foreach (['kode_aset', 'nama', 'merk', 'nomor_seri', 'jumlah', 'kondisi', 'tanggal_pengadaan', 'status'] as $field) {
                $rules[$field] = ['prohibited'];
            }
        }
        foreach (array_diff((new Aset)->getFillable(), [...AsetService::LOCAL, ...($manual ? ['kode_aset', 'nama', 'merk', 'nomor_seri', 'jumlah', 'kondisi', 'tanggal_pengadaan', 'status'] : [])]) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
