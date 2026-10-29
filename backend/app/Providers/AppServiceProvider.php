<?php

namespace App\Providers;

use App\Contracts\AsetSyncDriver;
use App\Contracts\PemindaiBerkas;
use App\Http\Requests\ApiRequest;
use App\Models\User;
use App\Policies\AdministrationPolicy;
use App\Policies\AsesmenPolicy;
use App\Policies\AsetPolicy;
use App\Policies\DokumenPolicy;
use App\Policies\KatalogPolicy;
use App\Policies\KepatuhanPolicy;
use App\Policies\MetodeRisikoPolicy;
use App\Services\AsetApiDriver;
use App\Services\OpenApiParameters;
use App\Services\OpenApiResponses;
use App\Services\PemindaiBerkasDasar;
use App\Services\ScopeUnit;
use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\DeprecationExtension;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RequestEssentialsExtension;
use Dedoc\Scramble\Support\OperationExtensions\ResponseHeadersExtension;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AsetSyncDriver::class, AsetApiDriver::class);
        $this->app->bind(PemindaiBerkas::class, PemindaiBerkasDasar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FormRequestParametersExtractor::ignoreInstanceOf(ApiRequest::class);
        Scramble::configure()
            ->withParametersExtractors(fn (ParametersExtractors $extractors) => $extractors->append(OpenApiParameters::class))
            ->withOperationTransformers(fn (OperationTransformers $transformers) => $transformers->use([
                RequestEssentialsExtension::class,
                RequestBodyExtension::class,
                OpenApiResponses::class,
                ResponseHeadersExtension::class,
                DeprecationExtension::class,
            ]));
        Gate::define('kepatuhan.write', [KepatuhanPolicy::class, 'write']);
        Gate::define('kepatuhan.review-sop', [KepatuhanPolicy::class, 'reviewSop']);
        Gate::define('asesmen.view', [AsesmenPolicy::class, 'view']);
        Gate::define('asesmen.create', [AsesmenPolicy::class, 'create']);
        Gate::define('asesmen.write', [AsesmenPolicy::class, 'write']);
        Gate::define('dokumen.view', [DokumenPolicy::class, 'view']);
        Gate::define('dokumen.write', [DokumenPolicy::class, 'write']);
        Gate::define('metode.view', [MetodeRisikoPolicy::class, 'view']);
        Gate::define('metode.write', [MetodeRisikoPolicy::class, 'write']);
        Gate::define('katalog.view', [KatalogPolicy::class, 'view']);
        Gate::define('katalog.write', [KatalogPolicy::class, 'write']);
        Gate::define('aset.view', [AsetPolicy::class, 'view']);
        Gate::define('aset.write', [AsetPolicy::class, 'write']);
        Gate::define('aset.sync', [AsetPolicy::class, 'sync']);
        Gate::define('admin.view', [AdministrationPolicy::class, 'viewAny']);
        Gate::define('admin.create', [AdministrationPolicy::class, 'create']);
        Gate::define('admin.update', [AdministrationPolicy::class, 'update']);
        Gate::define('admin.delete', [AdministrationPolicy::class, 'delete']);
        Gate::define('audit.view', fn (User $user) => app(ScopeUnit::class)->isSuper($user));
        Gate::define('viewApiDocs', fn (User $user) => app(ScopeUnit::class)->isSuper($user));
    }
}
