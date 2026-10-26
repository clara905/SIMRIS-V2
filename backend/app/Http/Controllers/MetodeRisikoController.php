<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\MetodeRisikoRequest;
use App\Http\Resources\MetodeRisikoResource;
use App\Models\VersiMetodeRisiko;
use App\Services\MetodeRisikoService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @throws AuthenticationException
 * @throws AuthorizationException
 * @throws ValidationException
 * @throws ModelNotFoundException
 * @throws BusinessRuleException
 */
class MetodeRisikoController extends Controller
{
    public function __construct(private MetodeRisikoService $service) {}

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ModelNotFoundException
     * @throws BusinessRuleException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(MetodeRisikoRequest $request): AnonymousResourceCollection
    {
        return MetodeRisikoResource::collection($this->service->listing($request->route('resource'), $request->validated()));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ModelNotFoundException
     * @throws BusinessRuleException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(MetodeRisikoRequest $request): MetodeRisikoResource
    {
        $class = MetodeRisikoService::MODELS[$request->route('resource')];

        return new MetodeRisikoResource($class::findOrFail($request->route('id')));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ModelNotFoundException
     * @throws BusinessRuleException
     */
    #[Response(status: 409, description: 'Aturan bisnis metode risiko tidak terpenuhi.', type: 'array{message: string, code: string, masalah: list<string>}')]
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(MetodeRisikoRequest $request): JsonResponse
    {
        return (new MetodeRisikoResource($this->service->mutate($request->route('resource'), null, $request->validated(), 'create', $request->user())))->response()->setStatusCode(201);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ModelNotFoundException
     * @throws BusinessRuleException
     */
    #[Response(status: 409, description: 'Aturan bisnis metode risiko tidak terpenuhi.', type: 'array{message: string, code: string, masalah: list<string>}')]
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function update(MetodeRisikoRequest $request): MetodeRisikoResource
    {
        return new MetodeRisikoResource($this->service->mutate($request->route('resource'), (int) $request->route('id'), $request->validated(), 'update', $request->user()));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ModelNotFoundException
     * @throws BusinessRuleException
     */
    #[Response(status: 409, description: 'Aturan bisnis metode risiko tidak terpenuhi.', type: 'array{message: string, code: string, masalah: list<string>}')]
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function destroy(MetodeRisikoRequest $request): JsonResponse
    {
        $this->service->mutate($request->route('resource'), (int) $request->route('id'), [], 'delete', $request->user());

        return response()->json(['message' => 'Data metode risiko berhasil dihapus.']);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws ModelNotFoundException
     * @throws BusinessRuleException
     */
    #[Response(status: 409, description: 'Aturan bisnis metode risiko tidak terpenuhi.', type: 'array{message: string, code: string, masalah: list<string>}')]
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function action(MetodeRisikoRequest $request): JsonResponse
    {
        $action = $request->route('action');
        if ($action === 'validasi') {
            $report = DB::transaction(function () use ($request): array {
                $this->service->lock();

                return $this->service->validation(VersiMetodeRisiko::findOrFail($request->route('id')));
            });

            return response()->json(['data' => $report]);
        }
        $model = $this->service->mutate($request->route('resource'), $request->route('id') ? (int) $request->route('id') : null, $request->validated(), $action, $request->user());

        return (new MetodeRisikoResource($model))->response()->setStatusCode($action === 'duplikasi' ? 201 : 200);
    }
}
