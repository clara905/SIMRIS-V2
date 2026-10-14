<?php

namespace App\Http\Requests;

use App\Models\Dokumen;
use App\Services\DokumenService;
use App\Services\ScopeUnit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DokumenRequest extends ApiRequest
{
    public function authorize(): bool
    {
        Gate::authorize('dokumen.view');
        $service = app(DokumenService::class);
        $row = null;
        if ($this->route('document')) {
            $row = $service->documents($this->user())->findOrFail($this->route('document'));
        } elseif ($this->route('id')) {
            $row = $service->query($this->route('resource'), $this->user())->findOrFail($this->route('id'));
        }
        if ($this->route('resource') === 'file-lampiran' && $this->isMethod('POST') && filter_var($this->input('dokumen_versi_id'), FILTER_VALIDATE_INT) !== false) {
            $row = $service->versions($this->user())->findOrFail($this->integer('dokumen_versi_id'));
        }
        if (! $this->isMethod('GET')) {
            abort_unless(app(ScopeUnit::class)->hasRole($this->user(), ['ADMIN_SATKER', 'STAFF']), 403, 'Hanya penyusun dapat mengubah dokumen.');
            if ($row) {
                $doc = $row instanceof Dokumen ? $row : $service->documents($this->user())->findOrFail($row->dokumen_id);
                Gate::authorize('dokumen.write', [(int) $doc->unit_pemilik_id]);
            }
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array']];
        }
        if ($this->isMethod('DELETE') || $this->route('action') === 'finalkan') {
            return [];
        }
        if ($this->route('resource') === 'file-lampiran') {
            return ['dokumen_versi_id' => ['required', 'integer'], 'file' => ['required', 'file', 'max:'.config('lampiran.max_kb')], 'keterangan' => ['sometimes', 'string', 'max:255'], 'urutan' => ['sometimes', 'integer', 'min:1']];
        }
        if ($this->route('resource') === 'dokumen') {
            return ['unit_pemilik_id' => ['required', 'integer'], 'kode' => ['required', 'string', 'max:255', Rule::unique('DOKUMEN', 'kode')], 'judul' => ['required', 'string', 'max:255'], 'jenis' => ['required', Rule::in(['SOP', 'BUKTI', 'LAPORAN', 'PENDUKUNG'])]];
        }

        return ['isi_teks' => ['sometimes', 'nullable', 'string', 'max:1000000'], 'format_teks' => [$this->isMethod('POST') ? 'required' : 'sometimes', Rule::in(['PLAIN', 'MARKDOWN', 'RICH_TEXT_JSON'])], 'status' => ['prohibited'], 'dokumen_id' => ['prohibited'], 'nomor_versi' => ['prohibited'], 'dibekukan_pada' => ['prohibited'], 'dibuat_oleh' => ['prohibited']];
    }
}
