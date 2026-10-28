<?php

namespace App\Services;

use App\Models;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\OperationExtensions\ResponseExtension;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class OpenApiResponses extends ResponseExtension
{
    private function object(array $properties, array $required = []): ObjectType
    {
        $type = new ObjectType;
        foreach ($properties as $name => $property) {
            $type->addProperty($name, $property);
        }

        return $type->setRequired($required);
    }

    private function list(Type $item): ArrayType
    {
        return (new ArrayType)->setItems($item);
    }

    private function model(string $class): Reference
    {
        $components = $this->openApiTransformer->context->openApi->components;
        $name = 'Simris'.class_basename($class);
        if ($components->hasSchema($name)) {
            return new Reference('schemas', $name, $components);
        }
        $model = new $class;
        $properties = [];
        $casts = $model->getCasts();
        foreach (DatabaseSchema::getColumns($model->getTable()) as $column) {
            $field = $column['name'];
            if (in_array($field, $model->getHidden(), true)) {
                continue;
            } $cast = $casts[$field] ?? '';
            $sql = strtolower($column['type_name']);
            $type = match (true) {
                $cast === 'boolean' => new BooleanType,
                $cast === 'array' || $sql === 'json' => in_array($field, ['peringatan'], true) ? $this->list(new MixedType) : new MixedType,
                str_starts_with($cast, 'decimal') => new StringType,
                in_array($cast, ['integer', 'int'], true) || str_contains($sql, 'int') => new IntegerType,
                in_array($cast, ['float', 'double', 'real'], true) || in_array($sql, ['float', 'double', 'decimal', 'numeric'], true) => new NumberType,
                default => new StringType,
            };
            if (str_contains($cast, 'datetime') || in_array($field, ['created_at', 'updated_at'], true)) {
                $type->format('date-time');
            } elseif (str_starts_with($cast, 'date') || $sql === 'date') {
                $type->format('date');
            }
            $type->nullable((bool) $column['nullable']);
            $properties[$field] = $type;
        }
        if ($class === Models\Ancaman::class) {
            $properties['sumber_ids'] = $this->list(new IntegerType);
        }
        if ($class === Models\Risiko::class) {
            $properties['asesmen_aset_ids'] = $this->list(new IntegerType);
            $properties['residual_tercapai'] = (new BooleanType)->nullable(true);
        }

        return $components->addSchema($name, Schema::fromType($this->object($properties, ['id'])));
    }

    private function page(Type $item, array $extra = []): ObjectType
    {
        $links = $this->object(array_fill_keys(['first', 'last', 'prev', 'next'], (new StringType)->nullable(true)));
        $meta = $this->object(['current_page' => new IntegerType, 'from' => (new IntegerType)->nullable(true), 'last_page' => new IntegerType, 'per_page' => new IntegerType, 'to' => (new IntegerType)->nullable(true), 'total' => new IntegerType, 'path' => new StringType, 'links' => $this->list($this->object(['url' => (new StringType)->nullable(true), 'label' => new StringType, 'active' => new BooleanType]))]);

        return $this->object(['data' => $this->list($item), 'links' => $links, 'meta' => $meta, ...$extra], ['data', 'links', 'meta', ...array_keys($extra)]);
    }

    private function add(Operation $operation, int $status, Type $type, string $description, string $media = 'application/json'): void
    {
        $operation->addResponse(Response::make($status)->setDescription($description)->setContent($media, Schema::fromType($type)));
    }

    private function rowClass(string $resource): ?string
    {
        return [...AdminService::MODELS, ...MetodeRisikoService::MODELS, ...KatalogService::MODELS, ...KepatuhanService::MODELS, 'aset' => Models\Aset::class, 'penugasan-aset' => Models\PenugasanAset::class, 'aset-sinkron' => Models\SinkronAset::class, 'dokumen' => Models\Dokumen::class, 'dokumen-versi' => Models\DokumenVersi::class, 'file-lampiran' => Models\FileLampiran::class, 'asesmen' => Models\AsesmenRisiko::class, 'risiko' => Models\Risiko::class, 'asesmen-aset' => Models\AsesmenAset::class, 'penilaian' => Models\PenilaianRisiko::class, 'usulan-keputusan' => Models\UsulanKeputusan::class, 'rencana' => Models\RencanaPenanganan::class, 'progres' => Models\ProgresPenanganan::class, 'log-audit' => Models\LogAudit::class][$resource] ?? null;
    }

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! in_array(class_basename($routeInfo->className() ?? ''), ['AdminController', 'MetodeRisikoController', 'KatalogController', 'AsetController', 'DokumenController', 'AsesmenController', 'AsesmenWorkflowController', 'PenangananController', 'KepatuhanController', 'MonitoringController', 'AuthController'], true)) {
            parent::handle($operation, $routeInfo);

            return;
        }
        $defaults = $routeInfo->route->defaults;
        $resource = $defaults['resource'] ?? '';
        $action = $defaults['action'] ?? '';
        $relation = $defaults['relation'] ?? null;
        $method = $routeInfo->methodName();
        $controller = class_basename($routeInfo->className());
        $verb = $routeInfo->method;
        $components = $this->openApiTransformer->context->openApi->components;
        $components->addSecurityScheme('sanctumCookie', SecurityScheme::apiKey('cookie', config('session.cookie'))->setDescription('Cookie session Sanctum SPA. Ambil CSRF cookie sebelum login; kirim X-XSRF-TOKEN untuk mutasi.'));
        $operation->security = $method === 'login' ? [] : [new SecurityRequirement(['sanctumCookie' => []])];
        $operation->setOperationId(strtolower($verb).'_'.trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $routeInfo->route->uri()), '_'));
        $operation->summary = $method.' '.($resource ?: str_replace('Controller', '', $controller)).($action ? ' '.$action : '');
        $operation->description .= "\nScope unit diperiksa sebelum akses ID. Daftar memakai page/per_page (maksimum 100), sort dan filter kolom yang diizinkan.";
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->in === 'query' && $parameter->name === 'filter[]') {
                $parameter->name = 'filter';
                $parameter->style = 'deepObject';
                $parameter->explode = true;
                $parameter->schema = Schema::fromType((new ObjectType)->additionalProperties(new StringType));
            }
        }
        $operation->responses = [];
        $message = $this->object(['message' => new StringType, 'code' => new StringType], ['message']);
        foreach ([401 => 'Belum login atau kredensial tidak valid.', 403 => 'Role, akun aktif, atau konfirmasi password tidak memenuhi syarat.', 404 => 'Data tidak ditemukan atau di luar scope.', 422 => 'Validasi input gagal.'] as $code => $description) {
            $error = $code === 422 ? $this->object(['message' => new StringType, 'errors' => (new ObjectType)->additionalProperties($this->list(new StringType))], ['message', 'errors']) : $message;
            $this->add($operation, $code, $error, $description);
        }
        if ($verb !== 'get') {
            $this->add($operation, 409, $this->object(['message' => new StringType, 'code' => new StringType, 'masalah' => $this->list(new StringType)], ['message', 'code', 'masalah']), 'Aturan bisnis atau transisi tidak terpenuhi.');
            $this->add($operation, 419, $message, 'Token CSRF tidak cocok atau session kedaluwarsa.');
        }
        if (in_array($method, ['login', 'confirm'], true)) {
            $this->add($operation, 429, $message, 'Batas percobaan terlampaui.');
        }
        if ($controller === 'AdminController' && $method === 'unitKinds') {
            $rules = config('aturan_unit', []);
            $properties = [];
            foreach (array_keys($rules) as $kind) {
                $properties[$kind] = $this->list(new StringType);
            }
            $this->add($operation, 200, $this->object(['data' => $this->object($properties, array_keys($properties))], ['data']), 'Jenis unit dan tingkat anak yang diperbolehkan dari konfigurasi backend.');

            return;
        }
        if (in_array($method, ['export', 'download'], true)) {
            $this->add($operation, 200, (new StringType)->format('binary'), $method === 'export' ? 'Ekspor CSV terfilter.' : 'Berkas privat yang telah diperiksa.', $method === 'export' ? 'text/csv' : 'application/octet-stream');

            return;
        }
        if (in_array($method, ['destroy', 'detach', 'logout', 'password', 'confirm'], true)) {
            $this->add($operation, 200, $this->object(['message' => new StringType], ['message']), 'Aksi berhasil.');

            return;
        }
        if ($controller === 'AuthController') {
            $user = $this->object(['id' => new IntegerType, 'pegawai_id' => (new IntegerType)->nullable(true), 'username' => new StringType, 'email_login' => new StringType, 'aktif' => new BooleanType, 'wajib_ganti_password' => new BooleanType]);
            $roles = $this->list($this->object(['id' => new IntegerType, 'role_id' => new IntegerType, 'kode' => new StringType, 'nama' => new StringType, 'unit_id' => (new IntegerType)->nullable(true), 'mulai' => (new StringType)->format('date-time'), 'selesai' => (new StringType)->format('date-time')->nullable(true)]));
            $payload = $this->object(['user' => $user, 'pegawai' => $this->model(Models\Pegawai::class)->nullable(true), 'roles' => $roles, 'jalur_unit' => $this->employeePath()->nullable(true)], ['user', 'pegawai', 'roles', 'jalur_unit']);
            $this->add($operation, 200, $this->object(['data' => $payload], ['data']), 'Identitas dan role aktif.');

            return;
        }
        if ($method === 'path') {
            $this->add($operation, 200, $this->object(['data' => $this->employeePath()->nullable(true)], ['data']), 'Jalur penempatan utama aktif.');

            return;
        }
        if ($method === 'assign' || $method === 'import') {
            $problem = $this->object(['id' => new IntegerType, 'baris' => new IntegerType, 'pesan' => new StringType, 'errors' => (new ObjectType)->additionalProperties($this->list(new StringType))]);
            $this->add($operation, 200, $this->object(['data' => $this->object(['berhasil' => new IntegerType, 'gagal' => new IntegerType, 'masalah' => $this->list($problem)], ['berhasil', 'gagal', 'masalah'])], ['data']), 'Hasil per baris, termasuk kegagalan parsial.');

            return;
        }
        if ($action === 'validasi') {
            $this->add($operation, 200, $this->object(['data' => $this->object(['valid' => new BooleanType, 'masalah' => $this->list(new StringType)], ['valid', 'masalah'])], ['data']), 'Hasil validasi metode.');

            return;
        }
        if ($relation) {
            [$table,$source,$target,$column,$fields] = TautanKepatuhanService::LINKS[$resource][$relation];
            $properties = [$column => new IntegerType];
            foreach ($fields as $field) {
                $properties[$field] = str_ends_with($field, '_id') ? new IntegerType : new StringType;
            }
            if ($method === 'links') {
                $properties[$source] = new IntegerType;
                $properties['created_at'] = new StringType;
                $properties['updated_at'] = new StringType;
            }
            $row = $this->object($properties);
            $this->add($operation, $verb === 'post' ? 201 : 200, $method === 'links' ? $this->page($row) : $this->object(['data' => $row], ['data']), 'Tautan sesuai tabel ERD.');

            return;
        }
        if ($controller === 'PenangananController' && $action === 'bukti') {
            $this->add($operation, 201, $this->object(['data' => $this->object(['dokumen_versi_id' => new IntegerType, 'keterangan' => new StringType], ['dokumen_versi_id', 'keterangan'])], ['data']), 'Bukti FINAL ditautkan.');

            return;
        }
        if ($method === 'report') {
            $row = clone $this->model(Models\Risiko::class)->resolve()->type;
            foreach (['penilaian' => Models\PenilaianRisiko::class, 'usulan_keputusan' => Models\UsulanKeputusan::class, 'keputusan_risiko' => Models\KeputusanRisiko::class, 'rencana_penanganan' => Models\RencanaPenanganan::class] as $key => $class) {
                $row->addProperty($key, $this->list($this->model($class)));
            }
            $this->add($operation, 200, $this->page($row, ['asesmen' => $this->model(Models\AsesmenRisiko::class), 'status_template' => (new StringType)->enum(['BELUM_TERSEDIA'])]), 'Kerangka JSON Lampiran 14; ekspor berkas menunggu templat.');

            return;
        }
        $class = $this->rowClass($resource);
        if ($controller === 'AsesmenWorkflowController') {
            $class = $method === 'reviews' ? Models\ReviewAsesmen::class : Models\AsesmenRisiko::class;
        }
        if ($action === 'generate') {
            $class = Models\VersiMetodeRisiko::class;
        }
        if (! $class) {
            throw new \LogicException('Kontrak respons belum terdaftar: '.$routeInfo->route->uri());
        }
        $row = $this->model($class);
        if ($method === 'tree') {
            $treeName = 'Pohon'.class_basename($class);
            $node = clone $row->resolve()->type;
            $reference = $components->addSchema($treeName, Schema::fromType($node));
            $node->addProperty('children', $this->list($reference));
            $this->add($operation, 200, $this->object(['data' => $this->list($reference)], ['data']), 'Pohon hierarki dalam scope.');

            return;
        }
        $status = $method === 'sync' ? 202 : ($method === 'store' || in_array($action, ['buat-revisi', 'duplikasi'], true) ? 201 : 200);
        $payload = in_array($method, ['index', 'history', 'reviews'], true) ? $this->page($row) : $this->object(['data' => $row], ['data']);
        $this->add($operation, $status, $payload, 'Data berhasil diproses.');
        if ($controller === 'AsesmenController' && $method === 'store' && in_array($resource, ['penilaian', 'usulan-keputusan'], true)) {
            $this->add($operation, 200, $payload, 'Penilaian/usulan draft yang sudah ada diperbarui.');
        }
    }

    private function employeePath(): ObjectType
    {
        $properties = ['pegawai_id' => new IntegerType];
        foreach (['satker', 'bidang', 'bagian', 'subbidang', 'subbagian'] as $kind) {
            $properties[$kind.'_id'] = (new IntegerType)->nullable(true);
            $properties[$kind] = (new StringType)->nullable(true);
        }

        return $this->object($properties);
    }
}
