<?php

namespace App\Actions\Api;

use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiQuery;
use App\Http\Attributes\ApiResponse;
use App\Support\DataSchema;
use Illuminate\Http\Response;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\DataConfig;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the OpenAPI 3.1 description of the `/api/v1` routes from the routes themselves: the
 * controller methods' `#[ApiBody]`, `#[ApiQuery]` and `#[ApiResponse]` attributes, their return
 * types and `@return` docblocks, and the Data classes' properties for the schemas.
 */
class BuildOpenApiSpec
{
    protected const string PREFIX = 'api/v1';

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $schemas = [];

    /**
     * The spec without `servers`, which depend on the host that serves it.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        // ponytail: cached per deploy via the route cache file (`php artisan optimize` rewrites it); without cached routes the spec is built on every request.
        if (! app()->routesAreCached()) {
            return $this->build();
        }

        return Cache::rememberForever('openapi:'.filemtime(app()->getCachedRoutesPath()), $this->build(...));
    }

    /**
     * @return array<string, mixed>
     */
    protected function build(): array
    {
        $this->schemas = [];
        $paths = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), self::PREFIX.'/')) {
                continue;
            }

            $path = str_replace('?}', '}', substr($route->uri(), strlen(self::PREFIX)));
            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            foreach ($methods as $index => $method) {
                $paths[$path][strtolower($method)] = $this->operation($route, $method, $index > 0);
            }
        }

        ksort($paths);
        ksort($this->schemas);

        return [
            'openapi' => '3.1.0',
            'info' => ['title' => config('app.name').' API', 'version' => '1'],
            'security' => [['oauth2' => []], ['bearer' => []]],
            'paths' => $paths,
            'components' => [
                'schemas' => $this->schemas,
                'securitySchemes' => [
                    'oauth2' => [
                        'type' => 'oauth2',
                        'description' => 'Tokens are scoped to workspaces: request the `workspace:{id}` scope of the workspace you call.',
                        'flows' => ['authorizationCode' => [
                            'authorizationUrl' => url('/oauth/authorize'),
                            'tokenUrl' => url('/oauth/token'),
                            'scopes' => ['mcp:use' => 'Use the MCP server'],
                        ]],
                    ],
                    'bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'A personal access token.'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function operation(LaravelRoute $route, string $method, bool $secondary): array
    {
        $name = substr((string) $route->getName(), strlen('api.v1.'));
        $segments = explode('/', substr($route->uri(), strlen(self::PREFIX) + 1));
        $operation = [
            'operationId' => $secondary ? $name.'.'.strtolower($method) : $name,
            'tags' => [$segments[0]],
        ];

        $reflection = new ReflectionMethod(...$this->action($route));
        $operation += $this->describe($reflection);

        $parameters = array_map(fn (string $parameter): array => [
            'name' => $parameter,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ], $route->parameterNames());

        if ($query = $this->attribute($reflection, ApiQuery::class)) {
            $object = JsonSchema::object(DataSchema::for($query->class))->toArray();

            foreach ($object['properties'] ?? [] as $key => $schema) {
                $parameters[] = [
                    'name' => $key,
                    'in' => 'query',
                    'required' => in_array($key, $object['required'] ?? [], true),
                    'schema' => $schema,
                ];
            }
        }

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $body = $this->attribute($reflection, ApiBody::class);

        if ($body !== null && ($body->class !== null || $body->schema !== [])) {
            $schema = $body->class !== null ? JsonSchema::object(DataSchema::for($body->class))->toArray() : $body->schema;
            $operation['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $schema]]];
        }

        $operation['responses'] = $this->responses($reflection, $method);

        if ($route->getName() === 'api.v1.openapi') {
            $operation['security'] = [];
        }

        return $operation;
    }

    /**
     * The controller class and method of a route, `__invoke` for a single-action controller.
     *
     * @return array{0: string, 1: string}
     */
    public function action(LaravelRoute $route): array
    {
        return [$route->getControllerClass(), str_contains($route->getActionName(), '@') ? $route->getActionMethod() : '__invoke'];
    }

    /**
     * The summary and description from the method's docblock.
     *
     * @return array<string, string>
     */
    protected function describe(ReflectionMethod $method): array
    {
        $lines = [];

        foreach (explode("\n", $method->getDocComment() ?: '') as $line) {
            $line = trim($line, " \t/*");

            if (str_starts_with($line, '@')) {
                break;
            }

            $lines[] = $line;
        }

        $text = trim(implode("\n", $lines));

        return $text === '' ? [] : ['summary' => strtok($text, "\n"), 'description' => preg_replace('/\s*\n\s*/', ' ', $text)];
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    protected function responses(ReflectionMethod $method, string $httpMethod): array
    {
        $names = $this->typeNames($method->getReturnType());

        if (in_array(StreamedResponse::class, $names, true)) {
            return ['200' => [
                'description' => 'The file.',
                'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]],
            ]];
        }

        $responses = [];
        $attribute = $this->attribute($method, ApiResponse::class);
        $status = $attribute->status ?? ($httpMethod === 'POST' ? 201 : 200);

        if (in_array(Response::class, $names, true)) {
            $responses['204'] = ['description' => 'No content.'];
        }

        $schema = $attribute !== null ? $this->declaredSchema($attribute) : $this->inferredSchema($method, $names);

        if ($attribute === null && $names === [Response::class]) {
            return $responses;
        }

        $responses[(string) $status] = $schema === null
            ? ['description' => 'Accepted.']
            : ['description' => 'Success.', 'content' => ['application/json' => ['schema' => $schema]]];

        return $responses;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function declaredSchema(ApiResponse $attribute): ?array
    {
        if ($attribute->schema !== []) {
            return $attribute->schema;
        }

        if ($attribute->class === null) {
            return null;
        }

        $item = $this->reference($attribute->class);

        return match (true) {
            $attribute->paginated, $attribute->cursor => $this->page($item),
            $attribute->list => ['type' => 'array', 'items' => $item],
            default => $item,
        };
    }

    /**
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    protected function inferredSchema(ReflectionMethod $method, array $names): array
    {
        $doc = $method->getDocComment() ?: '';
        $context = $method->getDeclaringClass();

        if (preg_match('/@return\s+\S*PaginatedDataCollection<[^,>]+,\s*([\w\\\\]+)>/', $doc, $match) === 1) {
            return $this->page($this->itemSchema($match[1], $context));
        }

        if (preg_match('/@return\s+(?:\S*Collection|array|list)<(?:int,\s*)?([\w\\\\]+)>/', $doc, $match) === 1) {
            return ['type' => 'array', 'items' => $this->itemSchema($match[1], $context)];
        }

        $class = $names[0] ?? null;

        // ponytail: a JsonResponse, array or bare Data return with no #[ApiResponse] is documented as a bare object; annotate the method when clients need its shape.
        return $class !== null && is_subclass_of($class, Data::class) ? $this->reference($class) : ['type' => 'object'];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function page(array $item): array
    {
        // ponytail: `links` and `meta` are bare objects, shared by page and cursor pagination; spell them out if clients rely on them.
        return [
            'type' => 'object',
            'properties' => ['data' => ['type' => 'array', 'items' => $item], 'links' => ['type' => 'object'], 'meta' => ['type' => 'object']],
            'required' => ['data'],
        ];
    }

    /**
     * The schema of a docblock's item type, which is a Data class by short name or a scalar.
     *
     * @param  ReflectionClass<covariant object>  $context
     * @return array<string, mixed>
     */
    protected function itemSchema(string $name, ReflectionClass $context): array
    {
        $class = $this->resolve($name, $context);

        return is_subclass_of($class, Data::class) ? $this->reference($class) : DataSchema::base($class)->toArray();
    }

    /**
     * Register the Data class as a component and point at it.
     *
     * @param  class-string<Data>  $class
     * @return array<string, string>
     */
    protected function reference(string $class): array
    {
        $name = class_basename($class);

        if (! isset($this->schemas[$name])) {
            $this->schemas[$name] = [];
            $this->schemas[$name] = $this->component($class);
        }

        return ['$ref' => '#/components/schemas/'.$name];
    }

    /**
     * @param  class-string<Data>  $class
     * @return array<string, mixed>
     */
    protected function component(string $class): array
    {
        $docs = DataSchema::docblock($class);
        $properties = [];
        $required = [];

        foreach (app(DataConfig::class)->getDataClass($class)->properties as $property) {
            if ($property->computed || $property->isVirtual) {
                continue;
            }

            $key = $property->outputMappedName ?? $property->name;
            $reflected = (new ReflectionProperty($class, $property->name))->getType();
            $names = $this->typeNames($reflected);
            $schema = $this->propertySchema($names, $docs[$property->name][0] ?? '', new ReflectionClass($class));

            if ($reflected?->allowsNull()) {
                $schema = ['oneOf' => [$schema, ['type' => 'null']]];
            }

            $properties[$key] = $schema;

            if (! array_intersect($names, [Lazy::class, Optional::class])) {
                $required[] = $key;
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ]);
    }

    /**
     * @param  list<string>  $names
     * @param  ReflectionClass<covariant object>  $context
     * @return array<string, mixed>
     */
    protected function propertySchema(array $names, string $docType, ReflectionClass $context): array
    {
        $name = array_values(array_diff($names, [Lazy::class, Optional::class, 'null']))[0] ?? 'mixed';

        if ($name === 'mixed') {
            return [];
        }

        if ($name === stdClass::class || ($name === 'array' && str_starts_with($docType, 'array<string'))) {
            return ['type' => 'object'];
        }

        if ($name === 'array' || is_a($name, Enumerable::class, true) || is_a($name, BaseDataCollectable::class, true)) {
            $item = preg_match('/<(?:int,\s*)?([\w\\\\]+)>|^([\w\\\\]+)\[\]/', $docType, $match) === 1
                ? $this->itemSchema($match[1] ?: ($match[2] ?? ''), $context)
                : [];

            return ['type' => 'array', 'items' => $item];
        }

        return is_subclass_of($name, Data::class) ? $this->reference($name) : DataSchema::base($name)->toArray();
    }

    /**
     * The class a short name in a docblock stands for, from the file's `use` lines or its namespace.
     *
     * @param  ReflectionClass<covariant object>  $context
     */
    protected function resolve(string $name, ReflectionClass $context): string
    {
        $name = ltrim($name, '\\');

        if (class_exists($name) || in_array($name, ['string', 'int', 'float', 'bool'], true)) {
            return $name;
        }

        $source = file_get_contents((string) $context->getFileName()) ?: '';

        if (preg_match('/^use\s+([\w\\\\]+\\\\'.preg_quote($name, '/').');/m', $source, $match) === 1) {
            return $match[1];
        }

        return $context->getNamespaceName().'\\'.$name;
    }

    /**
     * @return list<string>
     */
    protected function typeNames(?ReflectionType $type): array
    {
        return array_values(array_map(
            fn (ReflectionType $part): string => $part instanceof ReflectionNamedType ? $part->getName() : 'mixed',
            $type instanceof ReflectionUnionType ? $type->getTypes() : array_filter([$type]),
        ));
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    protected function attribute(ReflectionMethod $method, string $attribute): ?object
    {
        return ($method->getAttributes($attribute)[0] ?? null)?->newInstance();
    }
}
