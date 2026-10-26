<?php

namespace App\Http\Controllers;

use App\Http\Requests\DokumenRequest;
use App\Http\Resources\DokumenResource;
use App\Services\DokumenService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenController extends Controller
{
    public function __construct(private DokumenService $service) {}

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(DokumenRequest $request): AnonymousResourceCollection
    {
        return DokumenResource::collection($this->service->listing($request->route('resource'), $request->user(), $request->validated(), $request->route('document') ? (int) $request->route('document') : null));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(DokumenRequest $request): DokumenResource
    {
        return new DokumenResource($this->service->query($request->route('resource'), $request->user())->findOrFail($request->route('id')));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(DokumenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $model = match ($request->route('resource')) {
            'dokumen' => $this->service->create($data, $request->user()),'dokumen-versi' => $this->service->createVersion((int) $request->route('document'), $data, $request->user()),default => $this->service->upload((int) $data['dokumen_versi_id'], $request->file('file'), $data, $request->user())
        };

        return (new DokumenResource($model))->response()->setStatusCode(201);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function update(DokumenRequest $request): DokumenResource
    {
        return new DokumenResource($this->service->updateVersion((int) $request->route('id'), $request->validated(), $request->user(), $request->route('action') === 'finalkan'));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function detach(DokumenRequest $request): JsonResponse
    {
        $this->service->detach((int) $request->route('id'), (int) $request->route('file'), $request->user());

        return response()->json(['message' => 'Lampiran dilepas dari draft.']);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function download(DokumenRequest $request): StreamedResponse
    {
        return $this->service->download((int) $request->route('id'), $request->user());
    }
}
