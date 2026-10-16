# Harbor Help

An open-source helpdesk. This repository contains the web UI and the backend. Other clients and the MCP tools AI agents use to work tickets will be built on its API.

## Hosting modes

Both modes run the same codebase.

| Mode | How tenants work | Config |
| --- | --- | --- |
| **Cloud** | Many workspaces share one Postgres database. Each workspace is reached at its own subdomain (`acme.harbor.help`). | `HARBOR_MULTITENANT=true` |
| **Self-hosted** | One workspace. Subdomains are ignored. | `HARBOR_MULTITENANT=false` |

Self-hosting needs only PHP and Postgres. Cache, queues and sessions use the database by default. Redis, S3 and websockets are optional.

## Stack

- PHP 8.5, Laravel 13
- **PostgreSQL 18 (required).** Postgres is the only supported database, so the app uses Postgres-specific features freely.
- Inertia v3, Vue 3, Tailwind CSS 4
- Fortify (auth and passkeys), Wayfinder (typed routes)
- [`spatie/laravel-multitenancy`](https://github.com/spatie/laravel-multitenancy), single database
- [`grantholle/scru128-laravel`](https://github.com/grantholle/scru128-laravel) for every model ID. IDs carry a prefix, e.g. `usr_03h24vkex856a9k9a91dvwftq`.

## Local development

You need PHP 8.5, Composer, Node 22 and Postgres. [Laravel Herd](https://herd.laravel.com) provides all of them.

```bash
createdb harbor_help && createdb harbor_help_testing
composer setup
composer run dev
```

Set your `DB_*` values in `.env` if your Postgres isn't Herd's default (`root` with no password on `127.0.0.1:5432`).

## Testing and quality

```bash
composer run test
```

This runs Pint, PHPStan and Pest (in parallel). Tests run against Postgres (`harbor_help_testing`), never SQLite.

## Contributing

Architecture rules and conventions are in [`.ai/guidelines/harbor.md`](.ai/guidelines/harbor.md), and they apply to human contributors too. If you use an AI coding agent, run `php artisan boost:install`. It generates `AGENTS.md`/`CLAUDE.md` (gitignored) from those guidelines, plus the Laravel ecosystem guidance.

## License

[AGPL-3.0-or-later](LICENSE). If you run a modified version as a network service, you must make its source available to the people who use it.
