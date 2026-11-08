<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/**
 * Every web route that changes something has an API route (the map) or a reason not to (the
 * exemptions), so a new web action fails this test until it is added to one of them.
 *
 * @return array<string, string> Web route name => API route name.
 */
function webToApiRoutes(): array
{
    return [
        'attachments.destroy' => 'api.v1.attachments.destroy',
        'automation-runs.cancel' => 'api.v1.automation-runs.cancel',
        'automations.store' => 'api.v1.automations.store',
        'automations.reorder' => 'api.v1.automations.reorder',
        'automations.test' => 'api.v1.automations.test',
        'automations.update' => 'api.v1.automations.update',
        'automations.destroy' => 'api.v1.automations.destroy',
        'automations.duplicate' => 'api.v1.automations.duplicate',
        'automations.enabled.update' => 'api.v1.automations.enabled.update',
        'automations.webhook-token' => 'api.v1.automations.webhook-token',
        'availability.update' => 'api.v1.availability.update',
        'comments.update' => 'api.v1.comments.update',
        'comments.destroy' => 'api.v1.comments.destroy',
        'contacts.store' => 'api.v1.contacts.store',
        'contacts.update' => 'api.v1.contacts.update',
        'contacts.erase' => 'api.v1.contacts.erase',
        'contacts.export' => 'api.v1.contacts.export',
        'desks.store' => 'api.v1.desks.store',
        'desks.update' => 'api.v1.desks.update',
        'desks.destroy' => 'api.v1.desks.destroy',
        'desks.custom-fields.update' => 'api.v1.desks.custom-fields.update',
        'desks.members.update' => 'api.v1.desks.members.update',
        'desks.members.destroy' => 'api.v1.desks.members.destroy',
        'desks.restore' => 'api.v1.desks.restore',
        'desks.tickets.store' => 'api.v1.desks.tickets.store',
        'notifications.read' => 'api.v1.notifications.read',
        'notifications.read-one' => 'api.v1.notifications.read-one',
        'reminders.destroy' => 'api.v1.reminders.destroy',
        'saved-views.store' => 'api.v1.saved-views.store',
        'saved-views.update' => 'api.v1.saved-views.update',
        'saved-views.destroy' => 'api.v1.saved-views.destroy',
        'business-hours.store' => 'api.v1.business-schedules.store',
        'business-hours.update' => 'api.v1.business-schedules.update',
        'business-hours.destroy' => 'api.v1.business-schedules.destroy',
        'macros.store' => 'api.v1.macros.store',
        'macros.update' => 'api.v1.macros.update',
        'macros.destroy' => 'api.v1.macros.destroy',
        'notification-preferences.update' => 'api.v1.notification-preferences.update',
        'profile.update' => 'api.v1.user.update',
        'profile.timezone.update' => 'api.v1.user.update',
        'sla-policies.store' => 'api.v1.sla-policies.store',
        'sla-policies.update' => 'api.v1.sla-policies.update',
        'sla-policies.destroy' => 'api.v1.sla-policies.destroy',
        'tickets.bulk' => 'api.v1.tickets.bulk',
        'tickets.claim' => 'api.v1.tickets.claim',
        'tickets.update' => 'api.v1.tickets.update',
        'tickets.destroy' => 'api.v1.tickets.destroy',
        'tickets.assignees.update' => 'api.v1.tickets.assignees.update',
        'tickets.attachments.store' => 'api.v1.tickets.attachments.store',
        'tickets.comments.store' => 'api.v1.tickets.comments.store',
        'tickets.desk.update' => 'api.v1.tickets.desk.update',
        'tickets.macros.apply' => 'api.v1.tickets.macros.apply',
        'tickets.reminders.store' => 'api.v1.tickets.reminders.store',
        'tickets.restore' => 'api.v1.tickets.restore',
        'tickets.tags.update' => 'api.v1.tickets.tags.update',
        'workspace.custom-fields.store' => 'api.v1.custom-fields.store',
        'workspace.custom-fields.update' => 'api.v1.custom-fields.update',
        'workspace.custom-fields.destroy' => 'api.v1.custom-fields.destroy',
        'workspace.email.store' => 'api.v1.mailboxes.store',
        'workspace.email.update' => 'api.v1.mailboxes.update',
        'workspace.email.destroy' => 'api.v1.mailboxes.destroy',
        'workspace.exports.store' => 'api.v1.workspace.export',
        'workspace.general.update' => 'api.v1.workspace.update',
        'workspace.import.store' => 'api.v1.workspace.import',
        'workspace.settings.update' => 'api.v1.workspace.settings.update',
        'workspace.webhooks.store' => 'api.v1.webhooks.store',
        'workspace.webhooks.update' => 'api.v1.webhooks.update',
        'workspace.webhooks.destroy' => 'api.v1.webhooks.destroy',
        'workspace.webhooks.secret' => 'api.v1.webhooks.secret',
        'workspace.webhooks.ping' => 'api.v1.webhooks.ping',
    ];
}

/**
 * Web-only on purpose: password, API tokens, install, auth, portal and inbound webhooks.
 *
 * @return array<int, string>
 */
function webOnlyRoutes(): array
{
    return [
        'install.store',
        'magic-link.store',
        'user-password.update',
        'profile.destroy',
        'api-tokens.store',
        'api-tokens.destroy',
        'portal.desks.tickets.store',
        'portal.login.store',
        'portal.logout',
        'portal.tickets.comments.store',
        'portal.ratings.store',
        'portal.tickets.rating.store',
        'webhooks.automations',
        'webhooks.google.gmail',
        'webhooks.microsoft.graph',
        'webhooks.postmark.bounce',
        'webhooks.postmark.inbound',
    ];
}

test('every mutating web route has an API route or an exemption', function () {
    $webRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->getActionName(), 'App\Http\Controllers\\')
            && ! str_starts_with($route->getActionName(), 'App\Http\Controllers\Api\\')
            && array_diff($route->methods(), ['GET', 'HEAD']) !== [])
        ->map(fn (LaravelRoute $route): string => (string) $route->getName());

    $unmapped = $webRoutes->reject(fn (string $name): bool => isset(webToApiRoutes()[$name]) || in_array($name, webOnlyRoutes(), true));

    expect($unmapped->values()->all())->toBe([], 'Give these web routes an API route in webToApiRoutes() or exempt them in webOnlyRoutes().');
});

test('the parity map only names routes that exist', function () {
    $routes = Route::getRoutes();

    foreach (webToApiRoutes() as $web => $api) {
        expect($routes->hasNamedRoute($web))->toBeTrue("Web route {$web} is gone.")
            ->and($routes->hasNamedRoute($api))->toBeTrue("API route {$api} is gone.");
    }

    foreach (webOnlyRoutes() as $web) {
        expect($routes->hasNamedRoute($web))->toBeTrue("Exempt web route {$web} is gone.");
    }
});
