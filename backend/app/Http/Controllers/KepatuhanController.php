<?php

namespace App\Http\Controllers;

use App\Http\Requests\KepatuhanRequest;
use App\Http\Resources\KepatuhanResource;
use App\Services\KatalogService;
use App\Services\KepatuhanService;
use App\Services\TautanKepatuhanService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KepatuhanController extends Controller
{
    public function __construct(private KepatuhanService $service) {}

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(KepatuhanRequest $request): AnonymousResourceCollection
    {
        $resource = $request->route('resource');
        $query = $this->service->query($resource, $request->user());
        if ($request->route('parent')) {
            $query->where(KepatuhanService::PARENTS[$resource][1], $request->route('parent'));
        }
        $class = KepatuhanService::MODELS[$resource];
        app(KatalogService::class)->filtered($query, $request->validated(), ['id', ...(new $class)->getFillable()]);

        return KepatuhanResource::collection($query->paginate((int) $request->input('per_page', 20))->withQueryString());
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(KepatuhanRequest $request): KepatuhanResource
    {
        return new KepatuhanResource($this->service->query($request->route('resource'), $request->user())->findOrFail($request->route('id')));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(KepatuhanRequest $request): JsonResponse
    {
        return (new KepatuhanResource($this->service->mutate($request->route('resource'), null, $request->route('parent') ? (int) $request->route('parent') : null, $request->validated(), $request->user())))->response()->setStatusCode(201);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function update(KepatuhanRequest $request): KepatuhanResource
    {
        return new KepatuhanResource($this->service->mutate($request->route('resource'), (int) $request->route('id'), null, $request->validated(), $request->user()));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function destroy(KepatuhanRequest $request): JsonResponse
    {
        $this->service->mutate($request->route('resource'), (int) $request->route('id'), null, [], $request->user(), true);

        return response()->json(['message' => 'Draft dihapus.']);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function review(KepatuhanRequest $request): KepatuhanResource
    {
        return new KepatuhanResource($this->service->review($request->route('resource'), (int) $request->route('id'), $request->route('action') === 'ajukan' ? 'AJUKAN' : $request->validated('keputusan'), $request->validated(), $request->user()));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function links(KepatuhanRequest $request): AnonymousResourceCollection
    {
        [$table,$source,$target,$column,$fields] = TautanKepatuhanService::LINKS[$request->route('resource')][$request->route('relation')];

        $query = DB::table($table)->where($source, $request->route('id'));
        $allowed = [$source, $column, ...$fields, 'created_at', 'updated_at'];
        foreach ($request->input('filter', []) as $field => $value) {
            if (! in_array($field, $allowed, true) || ! is_scalar($value)) {
                throw ValidationException::withMessages(['filter' => ['Kolom atau nilai filter tidak diperbolehkan.']]);
            }
            $query->where($field, $value);
        }
        $sort = $request->input('sort', $column);
        $sortColumn = ltrim($sort, '-');
        if (! in_array($sortColumn, $allowed, true)) {
            throw ValidationException::withMessages(['sort' => ['Kolom urutan tidak diperbolehkan.']]);
        }

        return KepatuhanResource::collection($query->orderBy($sortColumn, str_starts_with($sort, '-') ? 'desc' : 'asc')->paginate((int) $request->input('per_page', 20))->withQueryString());
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function link(KepatuhanRequest $request, TautanKepatuhanService $links): JsonResponse
    {
        $result = $links->mutate($request->route('resource'), (int) $request->route('id'), $request->route('relation'), $request->validated(), $request->user(), $request->isMethod('DELETE'));

        return (new KepatuhanResource($result))->response()->setStatusCode($request->isMethod('DELETE') ? 200 : 201);
    }
}
