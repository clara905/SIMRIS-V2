<?php

namespace App\Http\Requests;

use App\Services\AsesmenService;
use Illuminate\Validation\Rule;

class AsesmenWorkflowRequest extends ApiRequest
{
    public function authorize(): bool
    {
        app(AsesmenService::class)->assessments($this->user())->findOrFail($this->route('id'));

        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array']];
        }

        return $this->route('action') === 'review' ? ['tahap' => ['required', Rule::in(['KASUB_AWAL', 'KABID_AWAL', 'KASUB_RESIDUAL', 'KABID_RESIDUAL', 'KASATKER_FINAL'])], 'keputusan' => ['required', Rule::in(['SETUJU', 'TOLAK'])], 'catatan' => ['sometimes', 'nullable', 'string', 'max:10000'], 'kembali_ke' => ['sometimes', 'nullable', Rule::in(['PENANGANAN', 'RESIDUAL_DRAFT'])]] : [];
    }
}
