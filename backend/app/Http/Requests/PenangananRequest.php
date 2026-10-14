<?php

namespace App\Http\Requests;

use App\Services\AsesmenService;
use App\Services\PenangananService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PenangananRequest extends ApiRequest
{
    public function authorize(): bool
    {
        Gate::authorize('asesmen.view');
        $service = app(PenangananService::class);
        $assessments = app(AsesmenService::class);
        $actor = $this->user();
        $kind = $this->route('resource');
        $plan = null;
        if ($this->route('assessment')) {
            $assessment = $assessments->assessments($actor)->findOrFail($this->route('assessment'));
        } elseif ($kind === 'penilaian') {
            $assessment = $assessments->assessmentFor('penilaian', (int) $this->route('id'), $actor);
        } else {
            $plan = $kind === 'progres' && ! $this->route('plan') ? $service->plans($actor)->findOrFail($service->progress($actor)->findOrFail($this->route('id'))->rencana_penanganan_id) : $service->plans($actor)->findOrFail($this->route('plan') ?? $this->route('id'));
            $assessment = $assessments->assessments($actor)->findOrFail($plan->asesmen_id);
        }
        if (! $this->isMethod('GET')) {
            if ($kind === 'progres') {
                $service->authorizeProgress($actor, $plan);
            } else {
                Gate::authorize('asesmen.write', [$assessment]);
            }
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array']];
        }
        if ($this->route('action') === 'bukti') {
            return ['dokumen_versi_id' => ['required', 'integer'], 'keterangan' => ['required', 'string', 'max:10000']];
        }
        if ($this->route('resource') === 'progres') {
            return ['uraian' => ['required', 'string', 'max:10000'], 'status' => ['required', Rule::in(['DIRENCANAKAN', 'BERJALAN', 'SELESAI'])], 'dilaporkan_oleh' => ['prohibited'], 'dilaporkan_pada' => ['prohibited']];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return ['rencana_kerja_id' => ['sometimes', 'nullable', 'integer', Rule::exists('RENCANA_KERJA', 'id')], 'pic_id' => [$required, 'integer'], 'kode' => [$required, 'string', 'max:255'], 'uraian' => [$required, 'string', 'max:10000'], 'target_selesai' => [$required, 'date_format:Y-m-d'], 'risiko_ids' => [$required, 'array', 'min:1', 'max:100'], 'risiko_ids.*' => ['integer', 'distinct'], 'status' => ['prohibited'], 'asesmen_id' => ['prohibited']];
    }
}
