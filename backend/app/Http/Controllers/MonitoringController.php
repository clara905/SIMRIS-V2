<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonitoringRequest;
use App\Http\Resources\AsesmenResource;
use App\Http\Resources\DokumenResource;
use App\Http\Resources\KepatuhanResource;
use App\Http\Resources\LampiranRisikoResource;
use App\Http\Resources\LogAuditResource;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\AsesmenService;
use App\Services\DokumenService;
use App\Services\KatalogService;
use App\Services\KepatuhanService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitoringController extends Controller
{
    public function query(string $resource, User $actor, array $input): Builder
    {
        $query = match ($resource) {
            'asesmen' => app(AsesmenService::class)->assessments($actor),'soa','sop' => app(KepatuhanService::class)->query($resource, $actor),'log-audit' => LogAudit::query(),default => app(DokumenService::class)->query($resource, $actor)
        };
        $fields = $resource === 'log-audit' ? ['id', 'user_id', 'entitas', 'entitas_id', 'aksi', 'waktu'] : ['id', ...$query->getModel()->getFillable()];

        return app(KatalogService::class)->filtered($query, $input, array_diff($fields, ['isi_teks', 'data_lama', 'data_baru', 'storage_key', 'checksum_sha256']), $resource !== 'log-audit');
    }

    public function resource(string $resource): string
    {
        return match ($resource) {
            'asesmen' => AsesmenResource::class,'soa','sop' => KepatuhanResource::class,'log-audit' => LogAuditResource::class,default => DokumenResource::class
        };
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function index(MonitoringRequest $request): AnonymousResourceCollection
    {
        $resource = $request->route('resource');
        $class = $this->resource($resource);

        return $class::collection($this->query($resource, $request->user(), $request->validated())->paginate((int) $request->input('per_page', 20))->withQueryString());
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function show(MonitoringRequest $request): JsonResource
    {
        $class = $this->resource($request->route('resource'));

        return new $class($this->query($request->route('resource'), $request->user(), [])->findOrFail($request->route('id')));
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function report(MonitoringRequest $request): AnonymousResourceCollection
    {
        $service = app(AsesmenService::class);
        $assessment = $service->assessments($request->user())->findOrFail($request->integer('asesmen_id'));
        $query = $service->risks($request->user())->where('asesmen_id', $assessment->id);
        app(KatalogService::class)->filtered($query, $request->validated(), ['id', 'kode', 'ancaman_id', 'area_dampak_id', 'pemilik_risiko_id']);

        return LampiranRisikoResource::collection($query->paginate((int) $request->input('per_page', 20))->withQueryString())->additional(['asesmen' => (new AsesmenResource($assessment))->resolve($request), 'status_template' => 'BELUM_TERSEDIA']);
    }

    #[Response(status: 401, description: 'Autentikasi diperlukan.', type: 'array{message: string}')]
    #[Response(status: 403, description: 'Hak akses tidak memenuhi syarat.', type: 'array{message: string, code?: string}')]
    #[Response(status: 404, description: 'Data tidak ada atau di luar scope.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Validasi input gagal.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function export(MonitoringRequest $request): StreamedResponse
    {
        $query = $this->query('log-audit', $request->user(), $request->validated());

        return response()->streamDownload(function () use ($query, $request): void {
            $stream = fopen('php://output', 'wb');
            $fields = ['id', 'user_id', 'entitas', 'entitas_id', 'aksi', 'data_lama', 'data_baru', 'ip_address', 'waktu'];
            fputcsv($stream, $fields, ',', '"', '');
            foreach ($query->cursor() as $row) {
                $data = (new LogAuditResource($row))->resolve($request);
                $values = [];
                foreach ($fields as $field) {
                    $value = $data[$field] ?? '';
                    $value = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : (string) $value;
                    if (preg_match('/^[=+@\-\t\r\n]/', $value)) {
                        $value = "'".$value;
                    } $values[] = $value;
                } fputcsv($stream, $values, ',', '"', '');
            } fclose($stream);
        }, 'log-audit.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
