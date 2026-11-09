<?php

use App\Enums\WorkspaceRole;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Routes outside the sweep, by URI prefix, each with the reason and where it is covered instead.
 */
const SWEEP_EXEMPT_PREFIXES = [
    'api/platform' => 'landlord routes, authorised by platform token and covered by PlatformWorkspaceApiTest',
    'portal' => 'contact guard, not an agent session; covered by tests/Feature/Portal',
    'webhooks' => 'authenticated by signature or token and resolve their workspace themselves; covered by the webhook tests',
    'horizon' => 'vendor dashboard',
];

/**
 * Tenant models the sweep cannot build in a foreign workspace (no factory), with the reason.
 */
const SWEEP_EXEMPT_MODELS = [
    'AutomationRun' => 'no factory; covered by AutomationRunTest cross-workspace cases',
    'Export' => 'no factory; covered by the export download tests',
];

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    ['workspace' => $this->acme, 'desk' => $this->acmeDesk] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->acme->makeCurrent();
});

/**
 * Route parameter name => bound model class, for the parameters that bind a model.
 *
 * @return array<string, class-string<Model>>
 */
function sweepBindings(LaravelRoute $route): array
{
    $bindings = [];
    foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
        foreach ($route->parameterNames() as $name) {
            if (Str::camel($name) === $parameter->getName()) {
                $bindings[$name] = $parameter->getType()->getName();
            }
        }
    }

    return $bindings;
}

test('no route serves another workspace\'s record by id', function () {
    $foreign = [];
    $failures = [];
    $swept = 0;

    foreach (Route::getRoutes() as $route) {
        if (collect(SWEEP_EXEMPT_PREFIXES)->keys()->contains(fn ($prefix) => str_starts_with($route->uri(), $prefix))) {
            continue;
        }

        $bindings = sweepBindings($route);
        $tenantBound = array_filter($bindings, fn ($class) => in_array(BelongsToWorkspace::class, class_uses_recursive($class), true));
        if ($tenantBound === []) {
            continue;
        }

        $parameters = [];
        foreach ($bindings as $name => $class) {
            if (array_key_exists(class_basename($class), SWEEP_EXEMPT_MODELS)) {
                continue 2;
            }
            $foreign[$class] ??= $this->other->execute(
                fn () => $class === User::class ? deskMember($this->other, Desk::factory()->create()) : $class::factory()->create(),
            );
            $parameters[$name] = $foreign[$class]->getKey();
        }

        $uri = $route->uri();
        foreach ($parameters as $name => $key) {
            $uri = str_replace(['{'.$name.'}', '{'.$name.'?}'], $key, $uri);
        }

        $method = collect($route->methods())->first(fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS'], true));
        $isApi = str_starts_with($route->uri(), 'api/');
        $isApi ? actAs($this->admin, $this->acme) : $this->actingAs($this->admin);

        $status = $this->call($method, "http://acme.harbor.test/{$uri}", [], [], [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode();
        $swept++;

        if (! in_array($status, [403, 404], true)) {
            $failures[] = "{$method} /{$uri} => {$status}";
        }
    }

    expect($swept)->toBeGreaterThan(100)->and($failures)->toBe([]);
});
