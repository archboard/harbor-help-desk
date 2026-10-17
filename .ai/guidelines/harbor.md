# Harbor Help

An open-source helpdesk. This app is the Inertia web UI and the backend. Other clients and MCP tools will be built on the same backend later. It runs either as a multi-tenant cloud app on one Postgres database or self-hosted with a single workspace.

## Product Shape

- Put business logic in Action classes in `app/Actions`. Controllers, API endpoints, jobs and MCP tools call those Actions. Logic must never exist only in an Inertia controller.
- Anything a user can do in the UI must also be possible through the versioned JSON API (`/api/v1`, Eloquent API Resources) once that API exists, so other clients and MCP tools have the same capabilities.

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

| Prefix | Model       |
| ------ | ----------- |
| `usr_` | `User`      |
| `pky_` | `Passkey`   |
| `wsp_` | `Workspace` |
| `dsk_` | `Desk`      |
| `ctc_` | `Contact`   |
| `tkt_` | `Ticket`    |
| `tev_` | `TicketEvent` |
| `tag_` | `Tag`       |
| `cmt_` | `TicketComment` |
| `att_` | `Attachment` |
| `rmd_` | `TicketReminder` |

## Tenancy

- Tenancy uses `spatie/laravel-multitenancy` with a single database. `Workspace` is the tenant model and `workspace_id` is the scoping column. In the cloud the tenant is found by subdomain. With `HARBOR_MULTITENANT=false` (self-hosted), the single workspace is always the current one.
- `users` is a global table. Agents belong to workspaces through the `workspace_user` pivot, which holds their role. End customers are workspace-scoped `contacts`, not `users`.
- Every tenant-owned table has a non-null `workspace_id` FK, and its model uses the workspace scoping trait. Every index and unique constraint on a tenant table starts with `workspace_id`.
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

- Never add a required service beyond PHP and Postgres. Cache, queue and session default to the `database` driver.
- Optional services (Redis, S3, Reverb, external mail providers) sit behind config, with a database or local fallback.

## Testing

- Tests run on Postgres (`harbor_help_testing`), never SQLite.

## License

- The app is AGPL-3.0-or-later. Do not add dependencies whose license is incompatible with it.
