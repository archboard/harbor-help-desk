# Installing Harbor Help with Docker

This guide takes a fresh Linux server to a running, self-hosted Harbor Help behind HTTPS. The Compose stack in this repository runs everything the application needs: the app (FrankenPHP), Horizon (queues), the scheduler, Reverb (websockets), PostgreSQL and Redis. One setup script installs Docker, configures the stack and puts a reverse proxy with a Let's Encrypt certificate in front of it.

To install each piece directly on the server instead, see [INSTALLATION-VM.md](INSTALLATION-VM.md). For backups, upgrades and monitoring, see [docs/operations.md](docs/operations.md).

## 1. Requirements

- A Debian 12+ or Ubuntu 24.04+ server (2 GB RAM is plenty to start) with a public IP and `sudo`.
- A DNS `A` record for your domain (e.g. `help.example.org`) pointing at the server, and ports `80` and `443` reachable from the internet for Let's Encrypt.

## 2. Run the setup script

```sh
curl -fsSL https://raw.githubusercontent.com/archboard/harbor-help-desk/main/setup-docker.sh \
  | sudo bash -s -- --domain help.example.org --email you@example.org --version v1.0.0
```

From start to finish, the script:

1. Installs Docker Engine and the Compose plugin from Docker's official apt repository, if `docker compose` isn't already available.
2. Downloads `docker-compose.yml` into `/opt/harbor-help` and adds a `docker-compose.override.yml` that binds the app port to localhost.
3. Writes `.env`: generates the app key, database password, Reverb credentials and the Passport signing keys, and sets the production URL and Reverb host from your domain.
4. Installs a reverse proxy and gets a Let's Encrypt certificate: Caddy (the default) or Nginx with Certbot.
5. Pulls the published image from `ghcr.io/archboard/harbor-help`, starts the stack and waits for it to be healthy (migrations run automatically).
6. Checks that `https://<domain>/up` responds.

| Option                 |                                                                       |
| ---------------------- | --------------------------------------------------------------------- |
| `--domain`             | Public hostname. Prompted for if omitted.                             |
| `--email`              | Let's Encrypt expiry notices (optional).                              |
| `--proxy caddy\|nginx` | Reverse proxy to install. Default `caddy`.                            |
| `--version TAG`        | Release to run, e.g. `v1.0.0`. Default `latest`, which tracks `main`. |
| `--repo URL`           | Git repository to fetch the Compose file from (forks).                |
| `--build`              | Build the image from source instead of pulling the published one.     |
| `--dir PATH`           | Install directory. Default `/opt/harbor-help`.                        |
| `--no-tls`             | Skip Let's Encrypt and serve plain HTTP.                              |

Pin `--version` to a release tag for production; `latest` follows `main`.

The script is safe to re-run: existing secrets and `.env` values are kept, and a re-run fetches the Compose file, pulls the image and restarts the stack. Your user is added to the `docker` group, so after logging out and back in you can run `docker compose` from `/opt/harbor-help` without `sudo`.

<details>
<summary>Doing it all manually</summary>

#### Install Docker

Skip this if `docker compose version` already works. Use Docker's official apt repository (the distro `docker.io` package has no Compose plugin): follow [docs.docker.com/engine/install](https://docs.docker.com/engine/install/).

#### Get the Compose file

```sh
sudo mkdir -p /opt/harbor-help && sudo chown "$USER" /opt/harbor-help
cd /opt/harbor-help
curl -fsSLO https://raw.githubusercontent.com/archboard/harbor-help-desk/main/docker-compose.yml
curl -fsSL -o .env https://raw.githubusercontent.com/archboard/harbor-help-desk/main/.env.example
chmod 600 .env
```

#### Configure `.env`

Set these in `.env`. The Compose file already sets `HARBOR_MULTITENANT=false`, the database, Redis, cache, session, queue and broadcast values and `TRUSTED_PROXIES=*`.

| Variable                                      | Value                                                                                                                                                              |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `APP_NAME`                                    | `"Harbor Help"`                                                                                                                                                    |
| `APP_ENV`                                     | `production`                                                                                                                                                       |
| `APP_DEBUG`                                   | `false`                                                                                                                                                            |
| `APP_KEY`                                     | `base64:` followed by `openssl rand -base64 32`                                                                                                                    |
| `APP_URL`                                     | `https://help.example.org`                                                                                                                                         |
| `DB_PASSWORD`                                 | A long random password (`openssl rand -hex 24`). Required; Compose refuses to start without it.                                                                    |
| `DB_DATABASE`, `DB_USERNAME`                  | Optional. Both default to `harbor` in Compose.                                                                                                                     |
| `REVERB_APP_ID`                               | Any number, e.g. `100001`                                                                                                                                          |
| `REVERB_APP_KEY`                              | `openssl rand -hex 16`                                                                                                                                             |
| `REVERB_APP_SECRET`                           | `openssl rand -hex 32`                                                                                                                                             |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | The public address browsers connect to, e.g. `help.example.org`, `443`, `https` (not the container).                                                               |
| `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` | The OAuth signing keys, on one line in double quotes with literal `\n` between the PEM lines. Required, since containers have no persistent `storage/oauth-*.key`. |
| `HARBOR_VERSION`                              | The release tag to run, e.g. `v1.0.0`.                                                                                                                             |

To generate the Passport keys:

```sh
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 2>/dev/null > private.pem
openssl pkey -in private.pem -pubout > public.pem
awk '{ printf "%s%s", sep, $0; sep = "\\n" }' private.pem   # value for PASSPORT_PRIVATE_KEY
awk '{ printf "%s%s", sep, $0; sep = "\\n" }' public.pem    # value for PASSPORT_PUBLIC_KEY
rm private.pem public.pem
```

Put each output between double quotes. `setup-docker.sh` does all of this for you.

#### Pull and start

```sh
docker compose pull
docker compose up -d
```

Migrations run automatically when the `app` service starts. Check that everything is healthy:

```sh
docker compose ps
docker compose logs -f app
```

#### Bind the app port to localhost

By default Compose publishes the app on `${HARBOR_PORT:-8000}` on every interface. Create `docker-compose.override.yml` so only the reverse proxy on the same host can reach it. `!override` replaces the default port list instead of appending to it.

```yaml
services:
  app:
    ports: !override
      - '127.0.0.1:8000:8000'
```

Then `docker compose up -d`. This matters: the Compose file sets `TRUSTED_PROXIES=*` so the app reads client IPs and the scheme from `X-Forwarded-*`, which is only safe when the port can't be reached except through your proxy.

#### Reverse proxy

The app container serves Laravel and forwards `/app` and `/apps` (websockets) to the Reverb service, so one upstream is enough: proxy everything to `127.0.0.1:8000` and pass the `Upgrade`/`Connection` headers.

Caddy (`/etc/caddy/Caddyfile`), which gets the certificate and proxies websockets on its own:

```caddyfile
help.example.org {
    reverse_proxy 127.0.0.1:8000
}
```

Nginx needs the websocket headers and a body limit at least as large as the attachment limit (25 MB by default):

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 80;
    server_name help.example.org;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name help.example.org;

    # Filled in by certbot
    ssl_certificate     /etc/letsencrypt/live/help.example.org/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/help.example.org/privkey.pem;

    client_max_body_size 25m;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
```

</details>

<details>
<summary>Building from source instead</summary>

Clone the repository into `/opt/harbor-help` and run `setup-docker.sh --build` there (or pass `--dir`). The script builds the image instead of pulling it and keeps your checkout's `docker-compose.yml`. By hand, use `docker compose build` and `docker compose up -d --build` wherever this guide says `pull`.

</details>

## 3. Verify

- `https://help.example.org/up` returns `200` (the script checks this for you). It returns an error when Postgres or Redis is unreachable.
- Open the browser dev tools on any page: there should be a websocket connection to `wss://help.example.org/app/<REVERB_APP_KEY>` with status `101`. If it fails, `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` don't match the public URL or the proxy isn't forwarding `Upgrade`.

## 4. Finish the installation

Visit `https://help.example.org/install`. The page shows a checklist of the services (database, Redis, Horizon, scheduler, Reverb) and creates the workspace and the owner account. Fix anything it flags before continuing.

Then configure email (a notification mailbox and your support mailboxes) under Workspace settings. Outbound mail and other limits are workspace settings in the UI, not `.env` variables.

## 5. Day-to-day operations

| Task                    | Command (from `/opt/harbor-help`)                                                             |
| ----------------------- | --------------------------------------------------------------------------------------------- |
| Run Artisan             | `docker compose exec app php artisan <command>`                                               |
| Tail logs               | `docker compose logs -f app horizon scheduler reverb`                                         |
| Update to a new version | Re-run the setup script with `--version vX.Y.Z` (see [upgrades](docs/operations.md#upgrades)) |
| Change `.env`           | Edit, then `docker compose up -d` (containers are recreated with the new values)              |
| Watch IMAP mailboxes    | Set `HARBOR_MAIL_WATCH=true`, then `docker compose --profile mail-watch up -d`                |

Data lives in the `postgres`, `redis` and `storage` Docker volumes. `docker compose down` keeps them; `docker compose down -v` deletes them. The `scheduler` service runs the scheduled work, so no host cron entry is needed.

Backups, restores, upgrades, alerts and health checks are in [docs/operations.md](docs/operations.md).
