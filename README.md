# Harbor Help

An open-source helpdesk. This repository contains the web UI and the backend. Other clients and the MCP tools AI agents use to work tickets will be built on its API.

## Hosting modes

Both modes run the same codebase.

| Mode            | How tenants work                                                                                                  | Config                     |
| --------------- | ----------------------------------------------------------------------------------------------------------------- | -------------------------- |
| **Cloud**       | Many workspaces share one Postgres database. Each workspace is reached at its own subdomain (`acme.harbor.help`). | `HARBOR_MULTITENANT=true`  |
| **Self-hosted** | One workspace. Subdomains are ignored.                                                                            | `HARBOR_MULTITENANT=false` |

Self-hosting needs PHP, Postgres, Redis and Reverb. Redis holds the cache, sessions and queues (run by Horizon), and Reverb serves realtime updates. S3 for attachments and external mail providers are optional. The Docker stack and the install scripts set up all of it.

## Installing

- [INSTALLATION.md](INSTALLATION.md): Docker, with a setup script that also configures HTTPS.
- [INSTALLATION-VM.md](INSTALLATION-VM.md): directly on a Debian or Ubuntu server, without Docker.
- [docs/operations.md](docs/operations.md): health checks, alerts, backups and upgrades.
- [CHANGELOG.md](CHANGELOG.md): what changed in each release, with upgrade notes.

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

## Realtime (Reverb)

Reverb is a required process alongside the web server, queue worker and Horizon: run `php artisan reverb:start` and keep it running (`composer run dev` starts it locally). Serve it on the same domain or a proxied path (for example `/app` to port 8080) so browsers connect over the same origin.

Set these in `.env`:

- `BROADCAST_CONNECTION=reverb`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`: the credentials. The key is sent to browsers, the secret never is.
- `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`: the public-facing address browsers connect to, not the address the server binds to.
- `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`: where `reverb:start` binds (defaults `0.0.0.0:8080`).

The Docker stack runs Reverb as its own service, and the install scripts generate the keys (see [INSTALLATION.md](INSTALLATION.md)).

## Testing and quality

```bash
composer run test
```

This runs Pint, PHPStan and Pest (in parallel). Tests run against Postgres (`harbor_help_testing`), never SQLite.

## Contributing

Architecture rules and conventions are in [`.ai/guidelines/harbor.md`](.ai/guidelines/harbor.md), and they apply to human contributors too. If you use an AI coding agent, run `php artisan boost:install`. It generates `AGENTS.md`/`CLAUDE.md` (gitignored) from those guidelines, plus the Laravel ecosystem guidance.

## License

[AGPL-3.0-or-later](LICENSE). If you run a modified version as a network service, you must make its source available to the people who use it.
