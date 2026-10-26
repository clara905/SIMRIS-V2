<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class MonitoringRequest extends ApiRequest
{
    public function authorize(): bool
    {
        Gate::authorize($this->route('resource') === 'log-audit' ? 'audit.view' : 'asesmen.view');

        return true;
    }

    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array'], ...($this->route('resource') === 'lampiran-14' ? ['asesmen_id' => ['required', 'integer'], 'format' => ['prohibited']] : [])];
    }
}
