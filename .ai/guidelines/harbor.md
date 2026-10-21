# Harbor Help

An open-source helpdesk. This app is the Inertia web UI and the backend. Other clients and MCP tools will be built on the same backend later. It runs either as a multi-tenant cloud app on one Postgres database or self-hosted with a single workspace.

Follow the commands in the Workflow guidelines (`.ai/guidelines/workflow.md`) after every change.

## Development

These apply to every agent, including subagents executing a plan step:

- Load the `laravel-best-practices` skill before writing or changing PHP, and `testing-best-practices` before writing or changing tests. Load the other matching skills too (`inertia-vue-development`, `wayfinder-development`, `tailwindcss-development`).
- Use Boost's `search-docs` before relying on a Laravel-ecosystem API, and `database-schema` before writing migrations or queries.
- After the workflow commands, run PhpStorm's `get_file_problems` on every changed file and fix what it reports.
- Report which skills were loaded, the inspection results, and the test results with the work.

## Product Shape

- Put business logic in Action classes in `app/Actions`. Controllers, API endpoints, jobs and MCP tools call those Actions. Logic must never exist only in an Inertia controller.
- Controllers only hold the resource methods (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`). Any other endpoint (marking read, restoring, merging) is its own single-action controller with `__invoke()`, e.g. `MarkNotificationsReadController`.
- Anything a user can do in the UI must also be possible through the versioned JSON API (`/api/v1`, `spatie/laravel-data` objects) once that API exists, so other clients and MCP tools have the same capabilities.
- Model data leaves the app through `spatie/laravel-data` objects only (`app/Data/{Domain}/{Model}Data`), in API responses, Inertia page props and shared props alike. Never hand-build an array of model attributes (`$model->only()`, `get([...])`, `map(fn () => [...])`). Relations use `Lazy::whenLoaded()`. Paginated API lists return `{Model}Data::collect($paginator, PaginatedDataCollection::class)`.
- Every Action that takes input has an input Data class (`app/Data/{Domain}/{Action}Data`). The Action keeps an `array $input` parameter and calls `{Action}Data::validateAndCreate($input)` first. Never accept a pre-built Data object from callers. Rules that depend on loaded context (the model being updated, the desk, the actor) stay in the Action and go through `validateAndCreateWith($input, $contextRules)`, so every error is reported at once.
- Frontend types come from `resources/js/types/generated.d.ts` (`composer run helpers`). Never hand-write a type that mirrors a Data class.
- Every ticket change goes through an Action that calls `Ticket::recordEvent()`, which dispatches `TicketActivityRecorded` after commit. Never change a ticket with a query-builder `update()`, because no event would fire. Code that reacts to tickets is a listener on `TicketActivityRecorded` that filters on the event type. It implements `ShouldQueue`, unless all it does is dispatch another queued event or job.

## Model IDs

- Every model uses `GrantHolle\Scru128Laravel\Concerns\HasScru128Ids` and overrides `idPrefix()` with a unique 3–4 letter prefix ending in `_`.
- Migrations use `$table->scru128('id', 'xxx_')` and `$table->foreignScru128('xxx_id', 'xxx_')`. Never use `id()`, `foreignId()`, `uuid()` or `ulid()`. The prefix passed in the migration must match the model's `idPrefix()`.
- Postgres does not index foreign keys automatically, so add an index to each FK unless a composite index already starts with that column.
- Vendor models (e.g. `Laravel\Passkeys\Passkey`) get an `App\Models` subclass that uses the trait, registered through the package's `use*Model()` hook.
- Exception: Passport's tables and models keep their default IDs (UUID clients, string tokens). Only their `user_id` columns use `foreignScru128('user_id', 'usr_')`.
- Exception: Laravel's `notifications` table keeps its UUID id, since Laravel generates it. Its `notifiable_id` is `string(40)`.
- For polymorphic relations, use `string('{name}_type')` and `string('{name}_id', 40)` with a composite index, and register every model in `Relation::enforceMorphMap()`.
- Models use `#[Unguarded]` instead of `#[Fillable]`. Actions only ever pass validated or explicitly built arrays to `create()`, `fill()` and `update()`, never raw request input.
- Treat IDs as strings everywhere: `string` type hints and `string` in TypeScript, never `int`.
- Add each new prefix to this registry:

| Prefix | Model              |
| ------ | ------------------ |
| `usr_` | `User`             |
| `pky_` | `Passkey`          |
| `wsp_` | `Workspace`        |
| `dsk_` | `Desk`             |
| `cfd_` | `CustomField`      |
| `ctc_` | `Contact`          |
| `tkt_` | `Ticket`           |
| `tev_` | `TicketEvent`      |
| `tag_` | `Tag`              |
| `cmt_` | `TicketComment`    |
| `att_` | `Attachment`       |
| `rmd_` | `TicketReminder`   |
| `svw_` | `SavedView`        |
| `mbx_` | `Mailbox`          |
| `mac_` | `Macro`            |
| `bsc_` | `BusinessSchedule` |
| `slp_` | `SlaPolicy`        |

## Tenancy

- Tenancy uses `spatie/laravel-multitenancy` with a single database. `Workspace` is the tenant model and `workspace_id` is the scoping column. In the cloud the tenant is found by subdomain. With `HARBOR_MULTITENANT=false` (self-hosted), the single workspace is always the current one.
- `users` is a global table. Agents belong to workspaces through the `workspace_user` pivot, which holds their role. End customers are workspace-scoped `contacts`, not `users`.
- Every tenant-owned table has a non-null `workspace_id` FK, and its model uses the workspace scoping trait. Every index and unique constraint on a tenant table starts with `workspace_id`. Exception: `mailboxes` has a landlord index on `lower(address) WHERE driver = 'google'`, because Gmail push notifications only carry the email address, so that webhook looks the mailbox up across workspaces.
- Queued jobs, notifications and listeners that touch tenant data must be tenant-aware.
- API tokens are scoped to workspaces through `workspace:{id}` OAuth scopes (`Workspace::tokenScope()`). `/api/v1` routes go through `EnsureTokenCanAccessWorkspace`, which requires the scope and current membership.
- Remove the tenant scope only explicitly, and only in landlord or admin code.
- Each tenant-scoped feature needs a test proving that workspace A cannot read or change workspace B's data.

## PostgreSQL

Postgres is the only supported database, so use its features wherever they help:

- Use `timestampsTz()` and `timestampTz()`, never `timestamps()` or `timestamp()`.
- Use `jsonb()` instead of `json()`, and add a GIN index when it is queried.
- Full-text search: a `$table->fullText()` index and `whereFullText()`, before considering Scout.
- Partial indexes (e.g. `WHERE deleted_at IS NULL`, `WHERE status = 'open'`) and functional indexes (`lower(email)`) go in the migration through `DB::statement()`.
- Use `whereLike()` for case-insensitive matching (ILIKE).
- Use `upsert()` for bulk writes, and `lock('for update skip locked')` for work-claiming queries such as ticket assignment.
- Use string columns with PHP backed enums, not native Postgres enum types. Enum case names are ALL_CAPS (`TicketStatus::ON_HOLD`) with lowercase snake_case values (`on_hold`). This overrides the TitleCase rule in the PHP guidelines.
- Enums shown to people use `App\Enums\Concerns\HasLabels` and implement `label()` as a `match` returning literal `__('...')` strings, so translation tooling finds them. Never build translation keys dynamically.
- Use `text()` when the length is unbounded. Don't pick arbitrary `string()` lengths.

## Self-Hosting

- The required services are PHP, Postgres, Redis and Reverb. Never add another. All of them ship with the self-hosted installation.
- Cache, session and queues use Redis. Queues run under Horizon. Use `Cache::lock()` for cross-process locks that aren't tied to a database row. Row-level work claiming still uses Postgres (`lock('for update skip locked')`). Durable data never lives only in Redis.
- Realtime features may rely on Reverb without a polling fallback. Broadcasts carry ids and event types only, never model data. Clients reload through policies and Data objects.
- Optional services (Redis, S3, external mail providers) sit behind config, with a database or local fallback.
- Anything a workspace admin could reasonably change is a workspace setting, managed in the UI and the API, never an `.env` variable. That includes outbound mail, limits, integrations and feature toggles. `.env` holds only infrastructure (database, Redis, Reverb, storage, app keys), platform-operator settings (multitenant mode, landlord access, platform secrets) and hard caps the cloud operator enforces across tenants. A workspace setting may be limited by such a cap, or hidden in the cloud, but self-hosters configure it in the UI.

## Testing

- Tests run on Postgres (`harbor_help_testing`), never SQLite.

## License

- The app is AGPL-3.0-or-later. Do not add dependencies whose license is incompatible with it.
