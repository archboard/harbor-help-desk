<?php

use App\Actions\Api\BuildOpenApiSpec;
use App\Data\Tickets\UpdateTicketData;
use App\Http\Attributes\ApiBody;
use App\Support\DataSchema;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/**
 * @return list<LaravelRoute>
 */
function apiV1Routes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'api/v1/'),
    ));
}

function openApiPath(LaravelRoute $route): string
{
    return str_replace('?}', '}', substr($route->uri(), strlen('api/v1')));
}

/**
 * @param  array<string, mixed>  $node
 * @return list<string>
 */
function openApiRefs(array $node): array
{
    $refs = [];

    array_walk_recursive($node, function (mixed $value, string|int $key) use (&$refs): void {
        if ($key === '$ref') {
            $refs[] = $value;
        }
    });

    return $refs;
}

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
});

test('serves the spec without authentication, on any host', function () {
    $spec = $this->getJson('http://harbor.test/api/v1/openapi.json')->assertOk()->json();

    expect($spec['openapi'])->toBe('3.1.0')
        ->and($spec['servers'][0]['url'])->toBe('http://harbor.test/api/v1')
        ->and($spec['paths']['/openapi.json']['get']['security'])->toBe([]);
});

test('describes every api/v1 route', function () {
    $spec = $this->getJson('/api/v1/openapi.json')->json();

    foreach (apiV1Routes() as $route) {
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            expect($spec['paths'])->toHaveKey(openApiPath($route))
                ->and($spec['paths'][openApiPath($route)])->toHaveKey(strtolower($method));
        }
    }
});

test('gives every POST, PUT and PATCH route a request body or marks it bodiless', function () {
    $spec = $this->getJson('/api/v1/openapi.json')->json();

    foreach (apiV1Routes() as $route) {
        foreach (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']) as $method) {
            $hasBody = isset($spec['paths'][openApiPath($route)][strtolower($method)]['requestBody']);
            $bodiless = ($attribute = (new ReflectionMethod(...app(BuildOpenApiSpec::class)->action($route)))->getAttributes(ApiBody::class)[0] ?? null)
                && $attribute->newInstance()->class === null && $attribute->newInstance()->schema === [];

            expect($hasBody || $bodiless)->toBeTrue("{$method} {$route->uri()} has neither #[ApiBody(...)] nor a bodiless #[ApiBody]");
        }
    }
});

test('documents the ticket update body from its input Data class', function () {
    $spec = $this->getJson('/api/v1/openapi.json')->json();

    expect($spec['paths']['/tickets/{ticket}']['patch']['requestBody']['content']['application/json']['schema'])
        ->toEqual(JsonSchema::object(DataSchema::for(UpdateTicketData::class))->toArray());
});

test('lists OAuth2 and bearer security schemes', function () {
    $spec = $this->getJson('/api/v1/openapi.json')->json();

    expect($spec['components']['securitySchemes'])->toHaveKeys(['oauth2', 'bearer'])
        ->and($spec['components']['securitySchemes']['oauth2']['type'])->toBe('oauth2')
        ->and($spec['components']['securitySchemes']['bearer'])->toMatchArray(['type' => 'http', 'scheme' => 'bearer'])
        ->and($spec['security'])->toBe([['oauth2' => []], ['bearer' => []]]);
});

test('resolves every schema reference to a component', function () {
    $spec = $this->getJson('/api/v1/openapi.json')->json();

    foreach (openApiRefs($spec) as $ref) {
        expect($spec['components']['schemas'])->toHaveKey(str_replace('#/components/schemas/', '', $ref));
    }

    expect($spec['components']['schemas']['TicketData']['properties'])->toHaveKeys(['id', 'status', 'requester'])
        ->and($spec['components']['schemas']['TicketData']['required'])->toContain('id')->not->toContain('requester');
});
