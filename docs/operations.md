# Operating Harbor Help

How to monitor, back up and upgrade a self-hosted install. Commands for Docker are run from the install directory (`/opt/harbor-help` by default); commands for a VM install are run from `/var/www/harbor-help` as the `harbor` user. Set `HARBOR_MULTITENANT=false` for self-hosting (the install scripts do); multitenant mode (many workspaces on subdomains) is for the hosted cloud and is not covered here.

Harbor Help needs PHP, PostgreSQL, Redis and Reverb. Cache, sessions and queues use Redis, queues run under Horizon, and the scheduler must run every minute.

## Health

- `GET /up` returns `200` when the app can reach PostgreSQL and Redis, and an error otherwise. Point your uptime monitor at it. The Docker `app` service uses it as its healthcheck.
- The checklist at `/install` shows Postgres, Redis, Horizon, the scheduler and Reverb while the install is being set up.
- Afterwards, the **System status** card on Workspace → General shows the same checks (self-hosted only): whether Horizon is running, whether the scheduler has run in the last few minutes (it writes a heartbeat every minute), and whether Reverb is reachable.
- If the scheduler check fails, no reminders, SLA checks, automations sweeps or mailbox polls are running. Docker: `docker compose ps scheduler`. VM: check the `schedule:run` cron entry of the `harbor` user (`sudo crontab -u harbor -l`).

## Horizon

The Horizon dashboard is at `/horizon`. Self-hosted, workspace owners and admins may open it. It shows throughput, waits, recent and failed jobs; retry failed jobs from there.

Docker runs Horizon in the `horizon` service; the VM install runs it as `harbor-help-horizon`. After deploying new code, Horizon must restart to pick it up: Docker recreates the container on `up -d`, and `update-vm.sh` runs `horizon:terminate` so systemd restarts it.

## Alerts

Operators are mailed when:

- A queued job fails. At most one email per job class per hour.
- Horizon detects a long queue wait (60 seconds on the `default` queue; see `waits` in `config/horizon.php`).

Self-hosted, the recipients are the workspace's owners and admins. The mail goes through the workspace's notification mailbox, so set one up under Workspace → Email, or alerts have no way out. In the multitenant cloud the recipients are the comma-separated emails in `HARBOR_HORIZON_EMAILS`.

## Logs

- Docker: the containers log to stderr. `docker compose logs -f app horizon scheduler reverb`. Docker rotates them by its logging driver settings; set `max-size` and `max-file` in `/etc/docker/daemon.json` if you want a cap.
- VM: Laravel writes `storage/logs/laravel.log` (set `LOG_STACK=daily` to rotate by day), Nginx writes `/var/log/nginx/`, and the services log to the journal: `journalctl -fu harbor-help-horizon` (also `harbor-help-reverb`, `harbor-help-mail-watch`).

Set `LOG_LEVEL=warning` or higher in production if debug logs are too noisy.

## Behind a proxy

`TRUSTED_PROXIES` lists the proxies whose `X-Forwarded-*` headers are trusted (`*` or IPs/CIDRs). The Compose file sets `*`, which is safe only when the app port is bound to localhost or otherwise reachable only through your proxy; `setup-docker.sh` does that. On a VM, Nginx talks to PHP-FPM directly, so leave it unset unless a load balancer sits in front of Nginx.

## Backups

State lives in four places. Back up all of them; the database alone is not enough.

| What                                         | Where                                                                                                                                                                                                               |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PostgreSQL database                          | Docker: `postgres` volume. VM: the `harbor_help` database.                                                                                                                                                          |
| Uploaded files (ticket attachments, exports) | Docker: `storage` volume (mounted at `/app/storage/app`). VM: `storage/app` under the install directory. If you set `HARBOR_ATTACHMENTS_DISK=s3`, attachments are in the bucket; use its versioning or replication. |
| `.env`                                       | Holds `APP_KEY` and the Passport signing keys (`PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`, or `storage/oauth-*.key` if you didn't set them).                                                                    |
| Redis                                        | Not needed: it holds cache, sessions and queue state. Jobs still queued at the moment of a restore are lost; the next scheduler pass picks up scheduled work again.                                                 |

`APP_KEY` encrypts stored secrets, such as mailbox passwords and OAuth tokens. Without it a restored database cannot decrypt them and you must reconnect every mailbox. Without the Passport keys, every issued API and MCP token stops working.

### Back up

Docker, from `/opt/harbor-help`:

```sh
mkdir -p backups
docker compose exec -T postgres sh -c 'pg_dump -U $POSTGRES_USER $POSTGRES_DB' | gzip > backups/harbor-$(date +%F).sql.gz
docker run --rm -v harbor-help_storage:/data -v "$PWD/backups":/backup alpine \
  tar czf /backup/storage-$(date +%F).tar.gz -C /data .
cp .env backups/env-$(date +%F) && chmod 600 backups/env-$(date +%F)
```

Compose prefixes volume names with the project name, which is the install directory's name (`harbor-help`); `docker volume ls` shows the real name.

VM:

```sh
sudo -u postgres pg_dump harbor_help | gzip > harbor-$(date +%F).sql.gz
sudo tar czf storage-$(date +%F).tar.gz -C /var/www/harbor-help storage/app
sudo cp /var/www/harbor-help/.env env-$(date +%F) && sudo chmod 600 env-$(date +%F)
```

Copy the backups off the server, and run them from cron. Test a restore now and then.

### Restore

On a new (or the same) server, install the same release first, then stop the app, restore, and start it again. Put the backed-up `.env` in place before the first start so `APP_KEY` and the Passport keys match.

Docker, from `/opt/harbor-help`:

```sh
docker compose stop app horizon scheduler reverb
docker compose exec -T postgres sh -c 'dropdb -U $POSTGRES_USER --force $POSTGRES_DB && createdb -U $POSTGRES_USER $POSTGRES_DB'
gunzip -c backups/harbor-YYYY-MM-DD.sql.gz | docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER $POSTGRES_DB'
docker run --rm -v harbor-help_storage:/data -v "$PWD/backups":/backup alpine \
  tar xzf /backup/storage-YYYY-MM-DD.tar.gz -C /data
docker compose up -d
```

VM:

```sh
cd /var/www/harbor-help && sudo -u harbor php artisan down
sudo systemctl stop harbor-help-horizon harbor-help-reverb harbor-help-mail-watch
sudo -u postgres dropdb --force harbor_help && sudo -u postgres createdb -O harbor harbor_help
gunzip -c harbor-YYYY-MM-DD.sql.gz | sudo -u postgres psql harbor_help
sudo tar xzf storage-YYYY-MM-DD.tar.gz -C /var/www/harbor-help
sudo chown -R harbor: /var/www/harbor-help/storage
sudo systemctl start harbor-help-horizon harbor-help-reverb harbor-help-mail-watch
sudo -u harbor php artisan up
```

If the restore is to a different release than the backup, run the matching install or update script afterwards so migrations run.

### Per-workspace exports

Separate from server backups, Workspace → Data & privacy can export the whole workspace (tickets, contacts, settings and attachments, with a manifest) and import such an export into a workspace, and export or erase a single contact's data for GDPR requests. These are for moving data and handling privacy requests, not for disaster recovery: they do not replace the backups above or carry over `.env` secrets.

## Upgrades

Releases are SemVer tags (`vX.Y.Z`). The Docker image is tagged to match (`ghcr.io/archboard/harbor-help:vX.Y.Z`), and `latest` follows `main`, so pin a tag in production. Each release upgrades cleanly from the previous minor version; to skip minors, go through each one in turn. Read the release's **Upgrade notes** in [CHANGELOG.md](../CHANGELOG.md) before upgrading: any manual step is listed there. Take a [backup](#backups) first.

The running version is shown by `HARBOR_VERSION` (recorded in `.env`).

### Docker

```sh
curl -fsSL https://raw.githubusercontent.com/archboard/harbor-help-desk/main/setup-docker.sh \
  | sudo bash -s -- --domain help.example.org --version vX.Y.Z
```

Re-running the setup script keeps your secrets and `.env`, fetches that release's `docker-compose.yml`, pulls the image and restarts the stack. By hand: set `HARBOR_VERSION=vX.Y.Z` in `.env`, replace `docker-compose.yml` with the one from that tag, then

```sh
docker compose pull
docker compose up -d
```

The `app` service runs `php artisan migrate --force` on start, and the other services wait until it is healthy. If you build from source (`--build`), `git fetch --tags && git checkout vX.Y.Z` and `docker compose up -d --build`.

### VM

```sh
cd /var/www/harbor-help
sudo ./update-vm.sh --version vX.Y.Z
```

The script puts the site in maintenance mode, checks out the tag, runs `composer install`, `npm ci`, `npm run build`, `migrate --force` and `optimize`, records `HARBOR_VERSION`, restarts Horizon, Reverb and the mailbox watcher, reloads PHP-FPM and brings the site back up. It leaves Nginx and the systemd units alone; re-run `install-vm.sh` if a release's upgrade notes say those changed.

### Rolling back

Migrations are forward-only. To go back, restore the pre-upgrade backup (database and storage) and deploy the previous tag.
