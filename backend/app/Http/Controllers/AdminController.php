<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\AdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\UnitOrganisasi;
use App\Services\AdminService;
use App\Services\HierarkiService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(private AdminService $service) {}

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(AdminRequest $request): AnonymousResourceCollection
    {
        return AdminResource::collection($this->service->listing($request->route('resource'), $request->user(), $request->validated()));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(AdminRequest $request): AdminResource
    {
        return new AdminResource($this->service->query($request->route('resource'), $request->user())->findOrFail($request->route('id')));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws BusinessRuleException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(AdminRequest $request): AdminResource
    {
        return new AdminResource($this->service->mutate($request->route('resource'), null, $request->validated(), 'create', $request->user()));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws BusinessRuleException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function update(AdminRequest $request): AdminResource
    {
        return new AdminResource($this->service->mutate($request->route('resource'), (int) $request->route('id'), $request->validated(), $request->route('action') ?? 'update', $request->user()));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws BusinessRuleException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function destroy(AdminRequest $request): JsonResponse
    {
        $this->service->mutate($request->route('resource'), (int) $request->route('id'), [], 'delete', $request->user());

        return response()->json(['message' => 'Data berhasil dihapus atau dinonaktifkan.']);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function tree(AdminRequest $request, HierarkiService $hierarki): JsonResponse
    {
        return response()->json(['data' => $hierarki->tree($this->service->query('unit-organisasi', $request->user())->orderBy('kode')->get()->toArray())]);
    }

    /** Pilihan jenis unit dibaca langsung dari konfigurasi hierarki backend. */
    public function unitKinds(): JsonResponse
    {
        Gate::authorize('admin.view', [UnitOrganisasi::class]);

        return response()->json(['data' => config('aturan_unit')]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function path(AdminRequest $request): JsonResponse
    {
        $pegawai = $this->service->query('pegawai', $request->user())->findOrFail($request->route('id'));

        return response()->json(['data' => DB::table('v_pegawai_unit')->where('pegawai_id', $pegawai->id)->first()]);
    }

    #[Response(status: 200, description: 'NIP pegawai setelah konfirmasi password.', type: 'array{data: array{nip: string|null}}')]
    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Konfirmasi password diperlukan atau hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Pegawai tidak ditemukan atau berada di luar scope.', type: 'array{message: string}')]
    public function revealNip(AdminRequest $request): JsonResponse
    {
        if (! Hash::check($request->validated('password'), $request->user()->password_hash)) {
            throw ValidationException::withMessages(['password' => ['Password tidak sesuai.']]);
        }
        $pegawai = $this->service->query('pegawai', $request->user())
            ->findOrFail((int) $request->route('id'));

        return response()->json(['data' => ['nip' => $pegawai->nip]]);
    }
}
