<?php

namespace App\Providers;

use App\Automations\Actions\AddNote;
use App\Automations\Actions\ApplyMacroAction;
use App\Automations\Actions\Assign;
use App\Automations\Actions\MoveDesk;
use App\Automations\Actions\Notify;
use App\Automations\Actions\SendReply;
use App\Automations\Actions\SetField;
use App\Automations\Actions\SetReminder;
use App\Automations\Actions\SetSlaPolicy;
use App\Automations\Actions\Tags;
use App\Automations\AutomationRegistry;
use App\Automations\EditorScope;
use App\Automations\RunCausation;
use App\Automations\Triggers\CommentAdded;
use App\Automations\Triggers\FieldChanged;
use App\Automations\Triggers\Schedule;
use App\Automations\Triggers\SlaEvent;
use App\Automations\Triggers\TicketCreated;
use App\Http\Controllers\OAuth\ApproveAuthorizationController;
use App\Http\Controllers\OAuth\AuthorizationController;
use App\Mail\WorkspaceTransport;
use App\Models\Attachment;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Passkey;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\TicketReminder;
use App\Models\User;
use App\Models\Workspace;
use App\OAuth\WorkspaceScopeRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use InvalidArgumentException;
use Laravel\Passkeys\Passkeys;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController as PassportAuthorizationController;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;

        $this->app->singleton(AutomationRegistry::class, fn () => new AutomationRegistry(
            triggers: [TicketCreated::class, CommentAdded::class, FieldChanged::class, SlaEvent::class, Schedule::class],
            actions: [SetField::class, Tags::class, Assign::class, MoveDesk::class, SetSlaPolicy::class, SetReminder::class, AddNote::class, SendReply::class, ApplyMacroAction::class, Notify::class],
        ));
        $this->app->scoped(RunCausation::class);
        $this->app->scoped(EditorScope::class);
        $this->app->bind(ScopeRepository::class, WorkspaceScopeRepository::class);
        $this->app->bind(PassportAuthorizationController::class, AuthorizationController::class);
        $this->app->bind(PassportApproveAuthorizationController::class, ApproveAuthorizationController::class);
        $this->app->when(AuthorizationController::class)
            ->needs(StatefulGuard::class)
            ->give(fn () => Auth::guard(config('passport.guard')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Mail::extend('workspace', function (): WorkspaceTransport {
            // The platform mailer is the transport's fallback, so pointing it back here would recurse.
            if (config('harbor.mail.platform_mailer') === 'workspace') {
                throw new InvalidArgumentException('HARBOR_PLATFORM_MAILER cannot be "workspace". Use a real mailer such as smtp, postmark or log.');
            }

            return new WorkspaceTransport;
        });

        Passkeys::usePasskeyModel(Passkey::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'workspace' => Workspace::class,
            'passkey' => Passkey::class,
            'desk' => Desk::class,
            'contact' => Contact::class,
            'ticket' => Ticket::class,
            'ticket_event' => TicketEvent::class,
            'tag' => Tag::class,
            'ticket_comment' => TicketComment::class,
            'attachment' => Attachment::class,
            'ticket_reminder' => TicketReminder::class,
            'custom_field' => CustomField::class,
            'mailbox' => Mailbox::class,
            'automation' => Automation::class,
            'automation_run' => AutomationRun::class,
        ]);

        Passport::tokensExpireIn(CarbonImmutable::now()->addHour());
        Passport::refreshTokensExpireIn(CarbonImmutable::now()->addDays(30));

        Passport::authorizationView(fn (array $parameters) => Inertia::render('auth/OAuthAuthorize', [
            'client' => ['id' => $parameters['client']->getKey(), 'name' => $parameters['client']->name],
            'scopes' => array_map(fn (Scope $scope): string => $scope->description, $parameters['scopes']),
            'workspaces' => $parameters['user']->workspaces()->get(['workspaces.id', 'workspaces.name'])
                ->map(fn (Workspace $workspace): array => ['id' => $workspace->id, 'name' => $workspace->name]),
            'state' => $parameters['request']->state,
            'authToken' => $parameters['authToken'],
            'csrfToken' => csrf_token(),
        ])->toResponse($parameters['request']));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
