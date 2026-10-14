<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsesmenWorkflowRequest;
use App\Http\Resources\AsesmenResource;
use App\Models\AsesmenRisiko;
use App\Models\ReviewAsesmen;
use App\Services\KatalogService;
use App\Workflow\AsesmenWorkflow;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AsesmenWorkflowController extends Controller
{
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function action(AsesmenWorkflowRequest $request, AsesmenWorkflow $workflow): JsonResponse
    {
        $id = (int) $request->route('id');
        $action = $request->route('action');
        $data = $request->validated();
        $model = $action === 'buat-revisi' ? $workflow->revise($id, $request->user()) : $workflow->transition($id, $action === 'review' ? $data['keputusan'] : strtoupper(str_replace('-', '_', $action)), $data, $request->user());

        return (new AsesmenResource($model))->response()->setStatusCode($action === 'buat-revisi' ? 201 : 200);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function history(AsesmenWorkflowRequest $request): AnonymousResourceCollection
    {
        $assessment = AsesmenRisiko::findOrFail($request->route('id'));

        return AsesmenResource::collection(app(KatalogService::class)->filtered(AsesmenRisiko::where('seri_asesmen_id', $assessment->seri_asesmen_id), $request->validated(), ['id', 'nomor_versi', 'status', 'dibuat_oleh'])->paginate($request->integer('per_page', 20))->withQueryString());
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function reviews(AsesmenWorkflowRequest $request): AnonymousResourceCollection
    {
        return AsesmenResource::collection(app(KatalogService::class)->filtered(ReviewAsesmen::where('asesmen_id', $request->route('id')), $request->validated(), ['id', 'reviewer_id', 'tahap', 'keputusan', 'waktu'])->paginate($request->integer('per_page', 20))->withQueryString());
    }
}
