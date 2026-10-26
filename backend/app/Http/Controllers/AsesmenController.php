<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsesmenRequest;
use App\Http\Resources\AsesmenResource;
use App\Services\AsesmenService;
use App\Services\PenilaianService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AsesmenController extends Controller
{
    public function __construct(private AsesmenService $service) {}

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(AsesmenRequest $request): AnonymousResourceCollection
    {
        return AsesmenResource::collection($this->service->listing($request->route('resource'), $request->user(), $request->validated(), $request->route('assessment') ? (int) $request->route('assessment') : null, $request->route('risk') ? (int) $request->route('risk') : null));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(AsesmenRequest $request): AsesmenResource
    {
        return new AsesmenResource($this->service->query($request->route('resource'), $request->user())->findOrFail($request->route('id')));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(AsesmenRequest $request, PenilaianService $ratings): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $model = match ($request->route('resource')) {
            'asesmen' => $this->service->create($data, $actor),'risiko' => $this->service->risk(null, (int) $request->route('assessment'), $data, $actor),'asesmen-aset' => $this->service->snapshot((int) $request->route('assessment'), $data, $actor),'penilaian' => $ratings->assess((int) $request->route('risk'), $data, $actor),'usulan-keputusan' => $ratings->propose((int) $request->route('risk'), $data, $actor)
        };

        return (new AsesmenResource($model))->response()->setStatusCode($model->wasRecentlyCreated ? 201 : 200);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function update(AsesmenRequest $request): AsesmenResource
    {
        $id = (int) $request->route('id');
        $actor = $request->user();
        $data = $request->validated();
        $model = match ($request->route('resource')) {
            'asesmen' => $this->service->update($id, $data, $actor),'risiko' => $this->service->risk($id, $this->service->assessmentFor('risiko', $id, $actor)->id, $data, $actor),'asesmen-aset' => $this->service->mutateSnapshot($id, $data, $actor)
        };

        return new AsesmenResource($model);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function destroy(AsesmenRequest $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $actor = $request->user();
        if ($request->route('resource') === 'risiko') {
            $this->service->risk($id, $this->service->assessmentFor('risiko', $id, $actor)->id, [], $actor, true);
        } else {
            $this->service->mutateSnapshot($id, [], $actor, true);
        }

        return response()->json(['message' => 'Data draft dihapus.']);
    }
}
