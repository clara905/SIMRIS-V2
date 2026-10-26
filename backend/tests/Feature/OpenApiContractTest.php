<?php

use App\Models\User;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);
test('OpenAPI describes route specific bodies typed responses and Sanctum cookie security', function (): void {
    $user = User::factory()->create();
    grantRole($user, 'SUPER_ADMIN');
    $schema = $this->actingAs($user)->getJson('/docs/api.json')->assertOk()->json();
    $post = $schema['paths']['/api/users']['post'];
    $body = $post['requestBody']['content']['application/json']['schema'];
    if (isset($body['$ref'])) {
        $body = $schema['components']['schemas'][basename($body['$ref'])];
    }
    expect($body['properties'])->toHaveKeys(['username', 'email_login', 'password', 'password_confirmation'])->not->toHaveKey('password_hash');
    expect($post['responses'])->toHaveKeys(['201', '401', '403', '404', '409', '422']);
    expect($schema['components']['schemas']['SimrisUser']['properties'])->not->toHaveKey('password_hash');
    expect($schema['paths']['/api/risiko/{risk}/penilaian']['post']['responses'])->toHaveKeys(['200', '201']);
    expect($schema['paths']['/api/asesmen/{id}/review']['post']['requestBody'])->not->toBeNull();
    expect($schema['paths']['/api/file-lampiran']['post']['requestBody']['content'])->toHaveKey('multipart/form-data');
    expect($schema['components']['securitySchemes']['sanctumCookie']['in'])->toBe('cookie');
    expect($schema['paths']['/api/soa-versi/{id}/review']['post']['security'])->toBe([['sanctumCookie' => []]]);
    expect($schema['components']['schemas']['SimrisRisiko']['properties'])->toHaveKeys(['residual_tercapai', 'asesmen_aset_ids']);
    $ids = [];
    foreach ($schema['paths'] as $path => $operations) {
        foreach ($operations as $verb => $operation) {
            if (! in_array($verb, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            } $ids[] = $operation['operationId'];
            expect($operation['responses'])->not->toBeEmpty();
        }
    }
    expect(count($ids))->toBe(count(array_unique($ids)));
});

test('Scramble generation has no diagnostics and every local reference resolves', function (): void {
    Scramble::preventSchema(UnknownType::class, throw: false);
    $result = app(Generator::class)->generate(Scramble::getGeneratorConfig('default'));
    expect($result->diagnostics()->map(fn ($diagnostic) => $diagnostic->code())->all())->toBe([]);
    $schema = $result->spec();
    $walk = function (array $node) use (&$walk, $schema): void {
        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#/')) {
                $cursor = $schema;
                foreach (explode('/', substr($value, 2)) as $part) {
                    expect($cursor)->toHaveKey($part);
                    $cursor = $cursor[$part];
                }
            } elseif (is_array($value)) {
                $walk($value);
            }
        }
    };
    $walk($schema);
});
