<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\AsetRequest;
use App\Http\Resources\AsetResource;
use App\Models\SinkronAset;
use App\Services\AsetService;
use App\Services\KatalogService;
use App\Services\SinkronAsetService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AsetController extends Controller
{
    public function __construct(private AsetService $service) {}

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(AsetRequest $request, KatalogService $catalog): AnonymousResourceCollection
    {
        if ($request->route('resource') === 'aset-sinkron') {
            return AsetResource::collection($catalog->filtered(SinkronAset::query(), $request->validated(), ['id', 'sumber_id', 'status', 'dipicu_oleh', 'dimulai_pada', 'selesai_pada'], false)->paginate($request->integer('per_page', 20))->withQueryString());
        }

        return AsetResource::collection($this->service->listing($request->user(), $request->validated(), $request->route('resource') === 'penugasan-aset'));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(AsetRequest $request): AsetResource
    {
        $query = match ($request->route('resource')) {
            'aset-sinkron' => SinkronAset::query(),'penugasan-aset' => $this->service->assignments($request->user()),default => $this->service->query($request->user())
        };

        return new AsetResource($query->findOrFail($request->route('id')));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function update(AsetRequest $request): AsetResource
    {
        $model = $request->route('resource') === 'penugasan-aset' ? $this->service->assignment((int) $request->route('id'), $request->validated(), $request->user()) : $this->service->update((int) $request->route('id'), $request->validated(), $request->user());

        return new AsetResource($model);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(AsetRequest $request): JsonResponse
    {
        return (new AsetResource($this->service->assignment(null, $request->validated(), $request->user())))->response()->setStatusCode(201);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function destroy(AsetRequest $request): JsonResponse
    {
        $this->service->assignment((int) $request->route('id'), [], $request->user(), true);

        return response()->json(['message' => 'Penugasan aset dihapus.']);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function assign(AsetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = ['berhasil' => 0, 'gagal' => 0, 'masalah' => []];
        foreach ($data['ids'] as $id) {
            try {
                $this->service->update($id, ['unit_pemilik_id' => $data['unit_pemilik_id'], 'kategori_id' => $data['kategori_id']], $request->user(), true);
                $result['berhasil']++;
            } catch (ModelNotFoundException|AuthorizationException|BusinessRuleException $e) {
                $result['gagal']++;
                $result['masalah'][] = ['id' => $id, 'pesan' => 'Aset atau tujuan tidak tersedia dalam scope atau tidak dapat ditetapkan.'];
            }
        }

        return response()->json(['data' => $result]);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function sync(AsetRequest $request, SinkronAsetService $sync): JsonResponse
    {
        return (new AsetResource($sync->enqueue($request->user()->id)))->response()->setStatusCode(202);
    }
}
