<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AsetSyncDriver;
use App\Contracts\CaptchaVerifier;
use App\Contracts\FirebaseAuthGateway;
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
use App\Services\Captcha\RecaptchaVerifier;
use App\Services\Firebase\RestFirebaseAuthGateway;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        // Sinkronisasi aset.
        $this->app->bind(
            AsetSyncDriver::class,
            AsetApiDriver::class
        );

        // Pemindai berkas.
        $this->app->bind(
            PemindaiBerkas::class,
            PemindaiBerkasDasar::class
        );

        // Firebase Authentication.
        $this->app->bind(
            FirebaseAuthGateway::class,
            fn () => new RestFirebaseAuthGateway(
                (string) config('simris.firebase.api_key')
            )
        );

        // reCAPTCHA.
        $this->app->bind(
            CaptchaVerifier::class,
            function () {
                $minScore = config('simris.captcha.min_score');

                return new RecaptchaVerifier(
                    secret: (string) config('simris.captcha.secret'),
                    verifyUrl: (string) config('simris.captcha.verify_url'),
                    hostname: config('simris.captcha.hostname') ?: null,
                    minScore: $minScore === null || $minScore === ''
                        ? null
                        : (float) $minScore,
                );
            }
        );
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        /*
         * OpenAPI / Scramble
         */
        FormRequestParametersExtractor::ignoreInstanceOf(
            ApiRequest::class
        );

        Scramble::configure()
            ->withParametersExtractors(
                fn (ParametersExtractors $extractors) =>
                    $extractors->append(OpenApiParameters::class)
            )
            ->withOperationTransformers(
                fn (OperationTransformers $transformers) =>
                    $transformers->use([
                        RequestEssentialsExtension::class,
                        RequestBodyExtension::class,
                        OpenApiResponses::class,
                        ResponseHeadersExtension::class,
                        DeprecationExtension::class,
                    ])
            );

        /*
         * Authorization / Gates
         */
        Gate::define(
            'kepatuhan.write',
            [KepatuhanPolicy::class, 'write']
        );
        Gate::define(
            'kepatuhan.review-sop',
            [KepatuhanPolicy::class, 'reviewSop']
        );

        Gate::define(
            'asesmen.view',
            [AsesmenPolicy::class, 'view']
        );

        Gate::define(
            'asesmen.create',
            [AsesmenPolicy::class, 'create']
        );

        Gate::define(
            'asesmen.write',
            [AsesmenPolicy::class, 'write']
        );

        Gate::define(
            'dokumen.view',
            [DokumenPolicy::class, 'view']
        );

        Gate::define(
            'dokumen.write',
            [DokumenPolicy::class, 'write']
        );

        Gate::define(
            'metode.view',
            [MetodeRisikoPolicy::class, 'view']
        );

        Gate::define(
            'metode.write',
            [MetodeRisikoPolicy::class, 'write']
        );

        Gate::define(
            'katalog.view',
            [KatalogPolicy::class, 'view']
        );

        Gate::define(
            'katalog.write',
            [KatalogPolicy::class, 'write']
        );

        Gate::define(
            'aset.view',
            [AsetPolicy::class, 'view']
        );

        Gate::define(
            'aset.write',
            [AsetPolicy::class, 'write']
        );

        Gate::define(
            'aset.sync',
            [AsetPolicy::class, 'sync']
        );

        Gate::define(
            'admin.view',
            [AdministrationPolicy::class, 'viewAny']
        );

        Gate::define(
            'admin.create',
            [AdministrationPolicy::class, 'create']
        );

        Gate::define(
            'admin.update',
            [AdministrationPolicy::class, 'update']
        );

        Gate::define(
            'admin.delete',
            [AdministrationPolicy::class, 'delete']
        );

        Gate::define(
            'audit.view',
            fn (User $user) => app(ScopeUnit::class)->isSuper($user)
        );
        Gate::define(
            'audit.delete',
            fn (User $user) => app(ScopeUnit::class)->isSuper($user)
        );

        Gate::define(
            'viewApiDocs',
            fn (User $user) => app(ScopeUnit::class)->isSuper($user)
        );

        /*
         * Rate Limiting - Login
         */
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) ($request->input('email') ?? $request->input('login') ?? $request->input('nip')));

            return [
                Limit::perMinute(5)->by(
                    $email.'|'.$request->ip()
                ),

                Limit::perMinute(30)->by(
                    (string) $request->ip()
                ),
            ];
        });

        RateLimiter::for('signup', function (Request $request) {
            $nip = trim((string) $request->input('nip'));
            return [Limit::perMinute(5)->by($nip.'|'.$request->ip()), Limit::perMinute(30)->by((string)$request->ip())];
        });

        /*
         * Rate Limiting - Lupa Kata Sandi
         */
        RateLimiter::for('lupa-kata-sandi', function (Request $request) {
            $email = mb_strtolower(
                (string) $request->input('email')
            );

            return [
                Limit::perMinute(3)->by(
                    $email.'|'.$request->ip()
                ),

                Limit::perMinute(10)->by(
                    (string) $request->ip()
                ),
            ];
        });
    }
}
