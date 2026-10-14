<?php

namespace App\Http\Controllers;

use App\Http\Requests\PenangananRequest;
use App\Http\Resources\AsesmenResource;
use App\Services\KatalogService;
use App\Services\PenangananService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PenangananController extends Controller
{
    public function __construct(private PenangananService $service) {}

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(PenangananRequest $request): AnonymousResourceCollection
    {
        $query = $request->route('resource') === 'progres' ? $this->service->progress($request->user())->where('rencana_penanganan_id', $request->route('plan')) : $this->service->plans($request->user())->where('asesmen_id', $request->route('assessment'));
        app(KatalogService::class)->filtered($query, $request->validated(), $request->route('resource') === 'progres' ? ['id', 'status', 'dilaporkan_oleh', 'dilaporkan_pada'] : ['id', 'kode', 'status', 'pic_id', 'target_selesai']);

        return AsesmenResource::collection($query->paginate((int) $request->input('per_page', 20))->withQueryString());
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(PenangananRequest $request): AsesmenResource
    {
        $query = $request->route('resource') === 'progres' ? $this->service->progress($request->user()) : $this->service->plans($request->user());

        return new AsesmenResource($query->findOrFail($request->route('id')));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(PenangananRequest $request): JsonResponse
    {
        if ($request->route('action') === 'bukti') {
            $this->service->evidence($request->route('resource'), (int) $request->route('id'), $request->validated(), $request->user());

            return response()->json(['data' => $request->validated()], 201);
        }
        $model = $request->route('resource') === 'progres' ? $this->service->report((int) $request->route('plan'), $request->validated(), $request->user()) : $this->service->plan((int) $request->route('assessment'), null, $request->validated(), $request->user());

        return (new AsesmenResource($model))->response()->setStatusCode(201);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function update(PenangananRequest $request): AsesmenResource
    {
        $plan = $this->service->plans($request->user())->findOrFail($request->route('id'));

        return new AsesmenResource($this->service->plan($plan->asesmen_id, $plan->id, $request->validated(), $request->user()));
    }
}
