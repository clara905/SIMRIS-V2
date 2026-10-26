<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AsesmenController;
use App\Http\Controllers\AsesmenWorkflowController;
use App\Http\Controllers\AsetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\KatalogController;
use App\Http\Controllers\KepatuhanController;
use App\Http\Controllers\MetodeRisikoController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PenangananController;
use App\Services\AdminService;
use App\Services\KatalogService;
use App\Services\KepatuhanService;
use App\Services\MetodeRisikoService;
use App\Services\TautanKepatuhanService;
use Illuminate\Support\Facades\Route;

Route::get('/items', [ItemController::class, 'index']);

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::put('/profil', [AuthController::class, 'updateProfile'])->name('auth.profile');
    Route::post('/ganti-password', [AuthController::class, 'password'])->name('auth.password');
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = AdminController::class;
    Route::get('/unit-organisasi/pohon', [$controller, 'tree'])->defaults('resource', 'unit-organisasi');
    Route::get('/unit-organisasi/jenis', [$controller, 'unitKinds']);
    Route::get('/pegawai/{id}/jalur', [$controller, 'path'])->whereNumber('id')->defaults('resource', 'pegawai');
    foreach (AdminService::MODELS as $resource => $class) {
        Route::get('/'.$resource, [$controller, 'index'])->defaults('resource', $resource);
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
        if ($resource === 'roles') {
            continue;
        }
        $sensitive = $resource === 'user-role-scope' ? ['password.confirmed'] : [];
        Route::post('/'.$resource, [$controller, 'store'])->defaults('resource', $resource)->middleware($sensitive);
        Route::match(['PUT', 'PATCH'], '/'.$resource.'/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', $resource)->middleware($sensitive);
        Route::delete('/'.$resource.'/{id}', [$controller, 'destroy'])->whereNumber('id')->defaults('resource', $resource)->middleware($sensitive);
    }
    foreach (['reset-password', 'aktifkan', 'nonaktifkan'] as $action) {
        Route::post('/users/{id}/'.$action, [$controller, 'update'])->whereNumber('id')->defaults('resource', 'users')->defaults('action', $action)->middleware($action === 'reset-password' ? ['password.confirmed'] : []);
    }
    Route::post('/unit-organisasi/{id}/pindah-induk', [$controller, 'update'])->whereNumber('id')->defaults('resource', 'unit-organisasi')->defaults('action', 'pindah-induk');
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = MetodeRisikoController::class;
    Route::post('/matriks-risiko/generate', [$controller, 'action'])->defaults('resource', 'matriks-risiko')->defaults('action', 'generate');
    foreach (['validasi', 'bekukan', 'duplikasi'] as $action) {
        Route::post('/versi-metode-risiko/{id}/'.$action, [$controller, 'action'])->whereNumber('id')->defaults('resource', 'versi-metode-risiko')->defaults('action', $action)->middleware($action === 'bekukan' ? ['password.confirmed'] : []);
    }
    foreach (MetodeRisikoService::MODELS as $resource => $class) {
        Route::get('/'.$resource, [$controller, 'index'])->defaults('resource', $resource);
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
        Route::post('/'.$resource, [$controller, 'store'])->defaults('resource', $resource);
        Route::match(['PUT', 'PATCH'], '/'.$resource.'/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', $resource);
        Route::delete('/'.$resource.'/{id}', [$controller, 'destroy'])->whereNumber('id')->defaults('resource', $resource);
    }
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = KatalogController::class;
    Route::get('/kontrol/pohon', [$controller, 'tree'])->defaults('resource', 'kontrol');
    foreach (KatalogService::MODELS as $resource => $class) {
        if (in_array($resource, ['ancaman', 'kerentanan', 'kontrol'], true)) {
            Route::post('/'.$resource.'/impor', [$controller, 'import'])->defaults('resource', $resource)->defaults('action', 'impor');
            Route::get('/'.$resource.'/ekspor', [$controller, 'export'])->defaults('resource', $resource);
        }
        Route::get('/'.$resource, [$controller, 'index'])->defaults('resource', $resource);
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
        Route::post('/'.$resource, [$controller, 'store'])->defaults('resource', $resource);
        Route::match(['PUT', 'PATCH'], '/'.$resource.'/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', $resource);
        Route::delete('/'.$resource.'/{id}', [$controller, 'destroy'])->whereNumber('id')->defaults('resource', $resource);
    }
    $controller = AsetController::class;
    Route::post('/aset/tetapkan', [$controller, 'assign'])->defaults('resource', 'aset')->defaults('action', 'tetapkan');
    Route::post('/aset-sinkron/jalankan', [$controller, 'sync'])->defaults('resource', 'aset-sinkron');
    foreach (['aset', 'penugasan-aset', 'aset-sinkron'] as $resource) {
        Route::get('/'.$resource, [$controller, 'index'])->defaults('resource', $resource);
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
    }
    Route::patch('/aset/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', 'aset');
    Route::post('/penugasan-aset', [$controller, 'store'])->defaults('resource', 'penugasan-aset');
    Route::match(['PUT', 'PATCH'], '/penugasan-aset/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', 'penugasan-aset');
    Route::delete('/penugasan-aset/{id}', [$controller, 'destroy'])->whereNumber('id')->defaults('resource', 'penugasan-aset');
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = DokumenController::class;
    foreach (['dokumen', 'dokumen-versi', 'file-lampiran'] as $resource) {
        Route::get('/'.$resource, [$controller, 'index'])->defaults('resource', $resource);
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
    }
    Route::post('/dokumen', [$controller, 'store'])->defaults('resource', 'dokumen');
    Route::get('/dokumen/{document}/versi', [$controller, 'index'])->whereNumber('document')->defaults('resource', 'dokumen-versi');
    Route::post('/dokumen/{document}/versi', [$controller, 'store'])->whereNumber('document')->defaults('resource', 'dokumen-versi');
    Route::patch('/dokumen-versi/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', 'dokumen-versi');
    Route::post('/dokumen-versi/{id}/finalkan', [$controller, 'update'])->whereNumber('id')->defaults('resource', 'dokumen-versi')->defaults('action', 'finalkan');
    Route::delete('/dokumen-versi/{id}/file/{file}', [$controller, 'detach'])->whereNumber(['id', 'file'])->defaults('resource', 'dokumen-versi');
    Route::post('/file-lampiran', [$controller, 'store'])->defaults('resource', 'file-lampiran');
    Route::get('/file-lampiran/{id}/unduh', [$controller, 'download'])->whereNumber('id')->defaults('resource', 'file-lampiran');
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = AsesmenController::class;
    Route::get('/asesmen', [$controller, 'index'])->defaults('resource', 'asesmen');
    Route::post('/asesmen', [$controller, 'store'])->defaults('resource', 'asesmen');
    foreach (['asesmen', 'risiko', 'asesmen-aset', 'penilaian', 'usulan-keputusan'] as $resource) {
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
    }
    foreach (['asesmen', 'risiko', 'asesmen-aset'] as $resource) {
        Route::patch('/'.$resource.'/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', $resource);
    }
    foreach (['risiko' => 'risiko', 'aset' => 'asesmen-aset'] as $path => $resource) {
        Route::get('/asesmen/{assessment}/'.$path, [$controller, 'index'])->whereNumber('assessment')->defaults('resource', $resource);
        Route::post('/asesmen/{assessment}/'.$path, [$controller, 'store'])->whereNumber('assessment')->defaults('resource', $resource);
    }
    foreach (['penilaian', 'usulan-keputusan'] as $resource) {
        Route::get('/risiko/{risk}/'.$resource, [$controller, 'index'])->whereNumber('risk')->defaults('resource', $resource);
        Route::post('/risiko/{risk}/'.$resource, [$controller, 'store'])->whereNumber('risk')->defaults('resource', $resource);
    }
    foreach (['risiko', 'asesmen-aset'] as $resource) {
        Route::delete('/'.$resource.'/{id}', [$controller, 'destroy'])->whereNumber('id')->defaults('resource', $resource);
    }
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = AsesmenWorkflowController::class;
    foreach (['ajukan', 'review', 'buat-revisi', 'selesai-penanganan', 'ajukan-residual'] as $action) {
        Route::post('/asesmen/{id}/'.$action, [$controller, 'action'])->whereNumber('id')->defaults('action', $action);
    }
    Route::get('/asesmen/{id}/riwayat-versi', [$controller, 'history'])->whereNumber('id');
    Route::get('/asesmen/{id}/review', [$controller, 'reviews'])->whereNumber('id');
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = PenangananController::class;
    Route::get('/asesmen/{assessment}/rencana-penanganan', [$controller, 'index'])->whereNumber('assessment')->defaults('resource', 'rencana');
    Route::post('/asesmen/{assessment}/rencana-penanganan', [$controller, 'store'])->whereNumber('assessment')->defaults('resource', 'rencana');
    Route::get('/rencana-penanganan/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', 'rencana');
    Route::patch('/rencana-penanganan/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', 'rencana');
    Route::get('/rencana-penanganan/{plan}/progres', [$controller, 'index'])->whereNumber('plan')->defaults('resource', 'progres');
    Route::post('/rencana-penanganan/{plan}/progres', [$controller, 'store'])->whereNumber('plan')->defaults('resource', 'progres');
    Route::get('/progres/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', 'progres');
    foreach (['progres' => 'progres', 'penilaian' => 'penilaian'] as $path => $resource) {
        Route::post('/'.$path.'/{id}/bukti', [$controller, 'store'])->whereNumber('id')->defaults('resource', $resource)->defaults('action', 'bukti');
    }
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = KepatuhanController::class;
    foreach (KepatuhanService::MODELS as $resource => $class) {
        Route::get('/'.$resource, [$controller, 'index'])->defaults('resource', $resource);
        Route::get('/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
        if (str_ends_with($resource, 'review')) {
            continue;
        }
        Route::patch('/'.$resource.'/{id}', [$controller, 'update'])->whereNumber('id')->defaults('resource', $resource);
        Route::delete('/'.$resource.'/{id}', [$controller, 'destroy'])->whereNumber('id')->defaults('resource', $resource);
        if (! isset(KepatuhanService::PARENTS[$resource])) {
            Route::post('/'.$resource, [$controller, 'store'])->defaults('resource', $resource);
        }
    }
    foreach (['sop/versi' => 'sop-versi', 'soa/versi' => 'soa-versi', 'soa-versi/detail' => 'soa-detail', 'soa-detail/benchmark' => 'soa-benchmark'] as $path => $resource) {
        [$parent,$suffix] = explode('/', $path);
        foreach (['get' => 'index', 'post' => 'store'] as $verb => $method) {
            Route::$verb('/'.$parent.'/{parent}/'.$suffix, [$controller, $method])->whereNumber('parent')->defaults('resource', $resource);
        }
    }
    foreach (['sop-versi', 'soa-versi'] as $resource) {
        foreach (['ajukan', 'review'] as $action) {
            Route::post('/'.$resource.'/{id}/'.$action, [$controller, 'review'])->whereNumber('id')->defaults('resource', $resource)->defaults('action', $action);
        }
    }
    foreach (TautanKepatuhanService::LINKS as $resource => $relations) {
        foreach ($relations as $relation => $definition) {
            foreach (['get' => 'links', 'post' => 'link', 'delete' => 'link'] as $verb => $method) {
                Route::$verb('/'.$resource.'/{id}/'.$relation, [$controller, $method])->whereNumber('id')->defaults('resource', $resource)->defaults('relation', $relation);
            }
        }
    }
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    $controller = MonitoringController::class;
    foreach (['asesmen', 'soa', 'sop', 'dokumen', 'file-lampiran'] as $resource) {
        Route::get('/monitoring/'.$resource, [$controller, 'index'])->defaults('resource', $resource);
        Route::get('/monitoring/'.$resource.'/{id}', [$controller, 'show'])->whereNumber('id')->defaults('resource', $resource);
    }
    Route::get('/log-audit/ekspor', [$controller, 'export'])->defaults('resource', 'log-audit');
    Route::get('/log-audit', [$controller, 'index'])->defaults('resource', 'log-audit');
    Route::get('/laporan/lampiran-14', [$controller, 'report'])->defaults('resource', 'lampiran-14');
});
