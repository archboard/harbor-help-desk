# Installing Harbor Help on a VM (without Docker)

This guide takes a fresh Debian 12+ or Ubuntu 24.04+ server to a running, self-hosted Harbor Help behind HTTPS, with every piece installed as a regular system package: Nginx and PHP-FPM serve the app, PostgreSQL and Redis run locally, and systemd keeps Horizon (queues), Reverb (websockets) and the mailbox watcher running. Prefer containers? See [INSTALLATION.md](INSTALLATION.md). For backups, upgrades and monitoring, see [docs/operations.md](docs/operations.md).

## Quick install

[`install-vm.sh`](install-vm.sh) automates every step below. Point your domain's DNS at the server first, then run:

```sh
curl -fsSL https://raw.githubusercontent.com/archboard/harbor-help-desk/main/install-vm.sh \
  | sudo bash -s -- --domain help.example.org --email you@example.org --version v1.0.0
```

| Option          |                                                                                            |
| --------------- | ------------------------------------------------------------------------------------------ |
| `--domain`      | Public hostname. Prompted for if omitted.                                                  |
| `--email`       | Let's Encrypt expiry notices (optional).                                                   |
| `--version REF` | Git branch or tag to deploy. Default `main`; use a tag in production.                      |
| `--repo URL`    | Git repository or path (forks).                                                            |
| `--no-tls`      | Skip Let's Encrypt and serve plain HTTP (e.g. behind a load balancer that terminates TLS). |
| `--dir PATH`    | Install directory. Default `/var/www/harbor-help`.                                         |

The script installs PHP 8.5, PostgreSQL 18, Redis, Node, Composer and Nginx, creates a `harbor` system user and database, writes `.env`, builds the app, runs the migrations, installs the systemd units (`harbor-help-horizon`, `harbor-help-reverb`, `harbor-help-mail-watch`) and a cron entry for the scheduler. On a server with under 1.5 GB of RAM and no swap it adds a 2 GB swap file for the asset build.

It is safe to re-run: it keeps existing secrets and `.env` values, and pulls, rebuilds, migrates and restarts everything. Re-run it after editing `.env` or after fixing DNS if the certificate request failed. To update to a new release, use `update-vm.sh` (see [upgrades](docs/operations.md#upgrades)). Then finish with [step 8](#8-finish-the-installation).

The rest of this guide is the same install done by hand.

## Manual install

Run the commands as a normal user with `sudo`. Replace `help.example.org` with your domain throughout.

## 1. Requirements

- A Debian 12+ or Ubuntu 24.04+ server (2 GB RAM is plenty to start) with a public IP.
- A DNS `A` record for your domain pointing at the server.
- Ports `80` and `443` open to the internet. Nothing else needs to be public.

## 2. Install the system packages

Install PHP 8.5 (from Ondřej Surý's repository on Debian and Ubuntu, or the distro if it ships 8.5), PostgreSQL 18 (from the PostgreSQL apt repository), Redis, Nginx, Certbot, Git, Composer and Node.js 24. The PHP packages are `php8.5-fpm`, `-cli`, `-pgsql`, `-redis`, `-intl`, `-zip`, `-mbstring`, `-xml`, `-curl` and `-bcmath`. The "Installing system packages" step of `install-vm.sh` has the exact commands, including the Sury, PostgreSQL and NodeSource repositories.

Don't use the distro `composer` package; it pulls in the distro's older PHP.

## 3. Create the user, database and code

```sh
sudo useradd --create-home --shell /bin/bash harbor
sudo -u postgres createuser harbor
sudo -u postgres createdb -O harbor harbor_help
sudo mkdir -p /var/www/harbor-help && sudo chown harbor: /var/www/harbor-help
sudo -u harbor git clone --branch v1.0.0 https://github.com/archboard/harbor-help-desk.git /var/www/harbor-help
```

Configure PHP-FPM with a pool for the `harbor` user (the script uses the socket `/run/php/harbor.sock`) and raise `upload_max_filesize` and `post_max_size` above your attachment limit.

## 4. Configure `.env`

```sh
cd /var/www/harbor-help
sudo -u harbor cp .env.example .env
sudo -u harbor php artisan key:generate
```

Edit `.env`:

| Variable                                               | Value                                                         |
| ------------------------------------------------------ | ------------------------------------------------------------- |
| `APP_NAME`                                             | `"Harbor Help"`                                               |
| `APP_ENV`, `APP_DEBUG`                                 | `production`, `false`                                         |
| `APP_URL`                                              | `https://help.example.org`                                    |
| `HARBOR_MULTITENANT`                                   | `false` (one workspace)                                       |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`     | `127.0.0.1`, `5432`, `harbor_help`, `harbor`                  |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`    | `redis`                                                       |
| `BROADCAST_CONNECTION`                                 | `reverb`                                                      |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Any number, `openssl rand -hex 16`, `openssl rand -hex 32`    |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`          | `help.example.org`, `443`, `https` (the public address)       |
| `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`             | `127.0.0.1`, `8080`                                           |
| `HARBOR_MAIL_WATCH`                                    | `true` (the mailbox watcher unit refuses to start without it) |
| `HARBOR_VERSION`                                       | The release tag you deployed                                  |

The OAuth (API and MCP) signing keys default to `storage/oauth-*.key`, created in step 5. `install-vm.sh` instead puts them in `.env` as `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`. Either way, back them up (see [operations](docs/operations.md#backups)).

## 5. Build the application

```sh
sudo -u harbor composer install --no-dev --optimize-autoloader
sudo -u harbor npm ci && sudo -u harbor npm run build
sudo -u harbor php artisan passport:keys
sudo -u harbor php artisan migrate --force
sudo -u harbor php artisan optimize
```

## 6. Nginx and HTTPS

Serve `/var/www/harbor-help/public` through the PHP-FPM socket, proxy `/app` and `/apps` to Reverb on `127.0.0.1:8080` with the `Upgrade`/`Connection` headers (and a long `proxy_read_timeout`), set `client_max_body_size` to at least 25m, then run `sudo certbot --nginx -d help.example.org`. `install-vm.sh` writes the full site file to `/etc/nginx/sites-available/harbor-help`; copy it as a reference.

## 7. Background services

Run these under systemd as the `harbor` user from `/var/www/harbor-help`, with `Restart=always`:

| Unit                     | `ExecStart`                                                      | Notes                              |
| ------------------------ | ---------------------------------------------------------------- | ---------------------------------- |
| `harbor-help-horizon`    | `/usr/bin/php artisan horizon`                                   | `TimeoutStopSec=60`                |
| `harbor-help-reverb`     | `/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080` | `LimitNOFILE=10000`                |
| `harbor-help-mail-watch` | `/usr/bin/php artisan mailboxes:watch`                           | Optional; new IMAP mail in seconds |

And the scheduler in the `harbor` user's crontab:

```
* * * * * cd /var/www/harbor-help && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Finish the installation

Visit `https://help.example.org/install`. The checklist shows whether the database, Redis, Horizon, scheduler and Reverb are working; fix anything it flags. Then create the workspace and the owner account, and configure email under Workspace settings.

## 9. Day-to-day operations

| Task          | Command (from `/var/www/harbor-help`)                                                               |
| ------------- | --------------------------------------------------------------------------------------------------- |
| Run Artisan   | `sudo -u harbor php artisan <command>`                                                              |
| Tail logs     | `tail -f storage/logs/laravel.log`, `journalctl -fu harbor-help-horizon`                            |
| Update        | `sudo ./update-vm.sh --version vX.Y.Z`                                                              |
| Change `.env` | Edit, then `sudo -u harbor php artisan optimize` and `sudo -u harbor php artisan horizon:terminate` |

Backups, restores, upgrades, alerts and health checks are in [docs/operations.md](docs/operations.md).
