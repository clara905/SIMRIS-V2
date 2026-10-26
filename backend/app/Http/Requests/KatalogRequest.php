<?php

namespace App\Http\Requests;

use App\Services\KatalogService;
use Illuminate\Support\Facades\Gate;

class KatalogRequest extends ApiRequest
{
    public function authorize(): bool
    {
        Gate::authorize($this->isMethod('GET') ? 'katalog.view' : 'katalog.write');

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
        if ($this->route('action') === 'impor') {
            return ['file' => ['required', 'file', 'max:2048']];
        }

        return app(KatalogService::class)->rules($this->route('resource'), $this->route('id') ? (int) $this->route('id') : null);
    }
}
