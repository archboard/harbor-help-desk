#!/usr/bin/env bash
#
# Installs (or updates) Harbor Help with Docker on a fresh Debian / Ubuntu server,
# end to end.
#
#   1. Check the system and collect the domain, email and reverse proxy choice
#   2. Install Docker Engine and the Compose plugin (if missing)
#   3. Get docker-compose.yml (or the source checkout with --build)
#   4. Write .env (secrets are generated once and kept on re-runs)
#   5. Install and configure the reverse proxy with Let's Encrypt (Caddy or Nginx)
#   6. Pull (or build) the image and start the stack
#   7. Check that the site responds
#
# Safe to re-run: existing secrets and .env values are kept, and a re-run
# refreshes the Compose file, pulls new images and restarts the stack.
#
#   curl -fsSL https://raw.githubusercontent.com/archboard/harbor-help-desk/main/setup-docker.sh \
#     | sudo bash -s -- --domain help.example.org --email you@example.org

set -euo pipefail

DOMAIN="${DOMAIN:-}"
EMAIL="${EMAIL:-}"
PROXY="${PROXY:-}"
HARBOR_VERSION="${HARBOR_VERSION:-}"
HARBOR_REPO="${HARBOR_REPO:-https://github.com/archboard/harbor-help-desk}"
APP_DIR="${APP_DIR:-/opt/harbor-help}"
NO_TLS="${NO_TLS:-0}"
BUILD="${BUILD:-0}"

ACME_ROOT=/var/www/letsencrypt
CADDYFILE=/etc/caddy/Caddyfile
NGINX_SITE=/etc/nginx/sites-available/harbor-help
MANAGED="# Managed by setup-docker.sh; re-running the script overwrites this file."

usage() {
    cat <<EOF
Usage: setup-docker.sh --domain help.example.org [options]

  --domain DOMAIN    Public hostname (required; prompted for if omitted)
  --email EMAIL      Let's Encrypt expiry notices (optional)
  --proxy NAME       Reverse proxy: caddy (default) or nginx
  --version TAG      Release to run, e.g. v1.2.0 (default: latest, which tracks main)
  --repo URL         Git repository (default: $HARBOR_REPO)
  --build            Build the image from source instead of pulling the published one
  --dir PATH         Install directory (default: /opt/harbor-help)
  --no-tls           Skip Let's Encrypt and serve plain HTTP
  -h, --help         Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain) DOMAIN="$2"; shift 2 ;;
        --email) EMAIL="$2"; shift 2 ;;
        --proxy) PROXY="$2"; shift 2 ;;
        --version) HARBOR_VERSION="$2"; shift 2 ;;
        --repo) HARBOR_REPO="$2"; shift 2 ;;
        --build) BUILD=1; shift ;;
        --dir) APP_DIR="$2"; shift 2 ;;
        --no-tls) NO_TLS=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
    esac
done

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33mWarning:\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31mError:\033[0m %s\n' "$*" >&2; exit 1; }

# Prompts read from the terminal so they still work under `curl | bash`
ask() {
    local prompt="$1" var="$2"
    if [[ -z "${!var}" && -r /dev/tty ]]; then
        # shellcheck disable=SC2229 # reads into the variable named by $var
        # read -p prompts on stderr, which is silenced here, so write the prompt to the tty
        { printf '%s' "$prompt" > /dev/tty && read -r "$var" < /dev/tty; } 2> /dev/null || true
    fi
}

env_get() { grep -m1 "^$1=" "$APP_DIR/.env" 2>/dev/null | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }

# Values go through ENVIRON because awk -v would interpret the \n in the Passport keys
env_set() {
    local file="$APP_DIR/.env"
    K="$1" V="$2" awk '
        index($0, ENVIRON["K"] "=") == 1 { print ENVIRON["K"] "=" ENVIRON["V"]; done = 1; next }
        { print }
        END { if (!done) print ENVIRON["K"] "=" ENVIRON["V"] }
    ' "$file" > "$file.tmp" && cat "$file.tmp" > "$file" && rm "$file.tmp"
}

# Only fills a value that is missing or blank, so re-runs never rotate secrets
env_default() { [[ -n "$(env_get "$1")" ]] || env_set "$1" "$2"; }

# Passport signs tokens with an RSA key pair. Stored on one line in double quotes with
# literal \n, which both Laravel's dotenv and Docker Compose read back as newlines.
env_passport_keys() {
    local key pub
    if [[ -z "$(env_get PASSPORT_PRIVATE_KEY)" ]]; then
        key=$(openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 2> /dev/null)
        env_set PASSPORT_PRIVATE_KEY "\"$(printf '%s' "$key" | awk '{ printf "%s%s", sep, $0; sep = "\\n" }')\""
    fi
    if [[ -z "$(env_get PASSPORT_PUBLIC_KEY)" ]]; then
        key=$(env_get PASSPORT_PRIVATE_KEY)
        pub=$(printf '%b' "$key" | openssl pkey -pubout)
        env_set PASSPORT_PUBLIC_KEY "\"$(printf '%s' "$pub" | awk '{ printf "%s%s", sep, $0; sep = "\\n" }')\""
    fi
}

# ---------------------------------------------------------------------------
# 1. Check the system and collect the settings
# ---------------------------------------------------------------------------

step "Checking the system"

[[ $EUID -eq 0 ]] || die "Run as root (curl ... | sudo bash -s -- --domain ...)."
[[ -r /etc/os-release ]] || die "Cannot read /etc/os-release."
# shellcheck source=/dev/null
. /etc/os-release
case "${ID:-}" in
    debian|ubuntu) ;;
    *) die "Only Debian and Ubuntu are supported (found '${ID:-unknown}')." ;;
esac

# On a re-run, default to the domain and proxy the previous run set up
if [[ -z "$DOMAIN" ]]; then
    DOMAIN="$(env_get APP_URL | sed -E 's|^https?://||; s|/.*$||')"
    [[ "$DOMAIN" == localhost* ]] && DOMAIN=
fi
if [[ -z "$PROXY" ]]; then
    if grep -qsF "$MANAGED" "$NGINX_SITE"; then
        PROXY=nginx
    elif grep -qsF "$MANAGED" "$CADDYFILE"; then
        PROXY=caddy
    fi
fi

ask "Public domain for Harbor Help (e.g. help.example.org): " DOMAIN
[[ -n "$DOMAIN" ]] || die "--domain is required."
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "'$DOMAIN' is not a valid hostname."

ask "Reverse proxy, caddy or nginx [caddy]: " PROXY
PROXY="${PROXY:-caddy}"
[[ "$PROXY" == caddy || "$PROXY" == nginx ]] || die "--proxy must be caddy or nginx."

if [[ "$NO_TLS" != 1 ]]; then
    ask "Email for Let's Encrypt notices (optional, Enter to skip): " EMAIL
fi

# Without swap a small server runs out of memory, thrashes and stops responding
mem_mb=$(awk '/MemTotal/ { print int($2 / 1024) }' /proc/meminfo)
if (( mem_mb < 1500 )) && [[ -z "$(swapon --noheadings)" ]]; then
    warn "Only ${mem_mb} MB of RAM (2 GB recommended). Adding a 2 GB swap file at /swapfile."
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile > /dev/null
    swapon /swapfile
    grep -qF /swapfile /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a

apt-get update -q
apt-get install -yq ca-certificates curl git gnupg openssl

# ---------------------------------------------------------------------------
# 2. Install Docker Engine and the Compose plugin
# ---------------------------------------------------------------------------

# Docker's official apt repository ships the Compose plugin; the distro's docker.io does not
if ! docker compose version > /dev/null 2>&1; then
    step "Installing Docker"

    apt-get remove -yq docker.io docker-doc docker-compose podman-docker containerd runc 2> /dev/null || true
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL "https://download.docker.com/linux/$ID/gpg" -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/$ID $VERSION_CODENAME stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -yq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

systemctl enable --now docker

# Let the user who ran sudo manage the stack without sudo (takes effect on next login)
if [[ -n "${SUDO_USER:-}" && "$SUDO_USER" != root ]]; then
    usermod -aG docker "$SUDO_USER"
fi

# ---------------------------------------------------------------------------
# 3. Get the Compose file (or the source)
# ---------------------------------------------------------------------------

step "Preparing $APP_DIR"

mkdir -p "$APP_DIR"
cd "$APP_DIR"

REF="${HARBOR_VERSION:-main}"
RAW="$(printf '%s' "${HARBOR_REPO%.git}" | sed 's|https://github.com/|https://raw.githubusercontent.com/|')/$REF"

if [[ "$BUILD" == 1 ]]; then
    if [[ ! -f Dockerfile ]]; then
        [[ -z "$(ls -A .)" ]] || die "$APP_DIR is not empty and has no Dockerfile."
        git clone "$HARBOR_REPO" .
    elif [[ -d .git && -n "$HARBOR_VERSION" ]]; then
        git fetch --tags --force origin
    fi
    [[ ! -d .git || -z "$HARBOR_VERSION" ]] || git checkout -q "$HARBOR_VERSION"
else
    curl -fsSLo docker-compose.yml "$RAW/docker-compose.yml" \
        || die "Could not download $RAW/docker-compose.yml. For a private repository, clone it and use --build."
fi

[[ -f .env ]] || cp .env.example .env 2> /dev/null || curl -fsSLo .env "$RAW/.env.example"

# Only the reverse proxy may reach the app: the Compose file sets TRUSTED_PROXIES=*,
# which is only safe when the port isn't public. `!override` replaces the default list.
env_default HARBOR_PORT 8000
HARBOR_PORT="$(env_get HARBOR_PORT)"
cat > docker-compose.override.yml <<EOF
$MANAGED
services:
  app:
    ports: !override
      - "127.0.0.1:$HARBOR_PORT:8000"
EOF

# ---------------------------------------------------------------------------
# 4. Write .env
# ---------------------------------------------------------------------------

step "Writing .env"

chmod 600 .env

env_default APP_KEY "base64:$(openssl rand -base64 32)"
env_default DB_PASSWORD "$(openssl rand -hex 24)"
env_default REVERB_APP_ID "$((RANDOM % 900000 + 100000))"
env_default REVERB_APP_KEY "$(openssl rand -hex 16)"
env_default REVERB_APP_SECRET "$(openssl rand -hex 32)"
env_passport_keys

env_set APP_ENV production
env_set APP_DEBUG false
env_set HARBOR_MULTITENANT false
[[ "$(env_get APP_NAME)" != Laravel ]] || env_set APP_NAME '"Harbor Help"'
[[ -z "$HARBOR_VERSION" ]] || env_set HARBOR_VERSION "$HARBOR_VERSION"

# APP_URL and REVERB_* depend on whether TLS succeeds, so they're set in step 5

if [[ -n "${SUDO_USER:-}" && "$SUDO_USER" != root ]]; then
    chown "$SUDO_USER:" "$APP_DIR/.env" "$APP_DIR/docker-compose.override.yml"
fi

# ---------------------------------------------------------------------------
# 5. Reverse proxy and Let's Encrypt
# ---------------------------------------------------------------------------

step "Configuring $PROXY"

# Only one proxy can own ports 80 and 443
other_proxy=$([[ "$PROXY" == caddy ]] && echo nginx || echo caddy)
if systemctl is-active -q "$other_proxy" 2> /dev/null; then
    warn "Stopping and disabling $other_proxy so $PROXY can use ports 80 and 443."
    systemctl disable --now -q "$other_proxy"
fi

if ufw status 2> /dev/null | grep -q "Status: active"; then
    ufw allow 80/tcp > /dev/null
    ufw allow 443/tcp > /dev/null
fi

TLS=0

# The app container's own Caddy already forwards /app and /apps to Reverb, so the
# proxy only needs to pass everything (websockets included) to HARBOR_PORT.

setup_caddy() {
    # Caddy's official apt repository, per caddyserver.com/docs/install
    if ! command -v caddy > /dev/null; then
        apt-get install -yq debian-keyring debian-archive-keyring apt-transport-https
        curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/gpg.key \
            | gpg --batch --yes --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
        curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt \
            > /etc/apt/sources.list.d/caddy-stable.list
        apt-get update -q
        apt-get install -yq caddy
    fi

    local site="$DOMAIN" global=""
    if [[ "$NO_TLS" == 1 ]]; then
        site="http://$DOMAIN"
    else
        TLS=1
        [[ -n "$EMAIL" ]] && global=$'{\n    email '"$EMAIL"$'\n}\n\n'
    fi

    cat > "$CADDYFILE" <<EOF
$MANAGED
$global$site {
    request_body {
        max_size 25MB
    }
    reverse_proxy 127.0.0.1:$HARBOR_PORT
}
EOF

    caddy validate --config "$CADDYFILE" --adapter caddyfile > /dev/null || die "Caddy rejected $CADDYFILE."
    systemctl enable -q caddy
    systemctl reload-or-restart caddy
}

write_nginx() {
    local tls="$1"
    local locations
    locations=$(cat <<'EOF'
    client_max_body_size 25m;

    location ^~ /.well-known/acme-challenge/ {
        root ACME_ROOT;
    }

    location / {
        proxy_pass http://127.0.0.1:HARBOR_PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_read_timeout 86400;
    }
EOF
)
    locations="${locations//ACME_ROOT/$ACME_ROOT}"
    locations="${locations//HARBOR_PORT/$HARBOR_PORT}"

    {
        cat <<EOF
$MANAGED
map \$http_upgrade \$connection_upgrade {
    default upgrade;
    ''      close;
}

EOF
        if [[ "$tls" == 1 ]]; then
            cat <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    location ^~ /.well-known/acme-challenge/ {
        root $ACME_ROOT;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    # The old http2 syntax still works on Nginx 1.22/1.24 (Debian 12, Ubuntu 24.04)
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $DOMAIN;

    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

$locations
}
EOF
        else
            cat <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

$locations
}
EOF
        fi
    } > "$NGINX_SITE"

    ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/harbor-help
    rm -f /etc/nginx/sites-enabled/default
    nginx -t -q || die "Nginx rejected the generated config ($NGINX_SITE)."
    systemctl enable -q nginx
    systemctl reload-or-restart nginx
}

setup_nginx() {
    # certbot's package installs a systemd timer that renews certificates
    apt-get install -yq nginx certbot
    mkdir -p "$ACME_ROOT"

    if [[ "$NO_TLS" == 1 ]]; then
        write_nginx 0
        return
    fi

    if [[ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]]; then
        # Serve the ACME challenge over HTTP first, then switch to HTTPS
        write_nginx 0
        local email_args=(--register-unsafely-without-email)
        [[ -n "$EMAIL" ]] && email_args=(--email "$EMAIL")
        if ! certbot certonly --webroot -w "$ACME_ROOT" -d "$DOMAIN" --non-interactive --agree-tos \
            "${email_args[@]}" --deploy-hook "systemctl reload nginx"; then
            warn "Could not get a certificate for $DOMAIN. Check that its DNS points at this server and ports 80/443 are open, then re-run this script. Continuing over plain HTTP."
            return
        fi
    fi

    TLS=1
    write_nginx 1
}

"setup_$PROXY"

if [[ "$TLS" == 1 ]]; then scheme=https; port=443; else scheme=http; port=80; fi

env_set APP_URL "$scheme://$DOMAIN"
# The public hostname browsers connect to, not the container
env_set REVERB_HOST "$DOMAIN"
env_set REVERB_PORT "$port"
env_set REVERB_SCHEME "$scheme"

# ---------------------------------------------------------------------------
# 6. Pull (or build) the image and start the stack
# ---------------------------------------------------------------------------

step "Starting the stack"

if [[ "$BUILD" == 1 ]]; then
    docker compose build
else
    docker compose pull -q
fi

# Migrations run when the app container starts; --wait blocks until it's healthy
docker compose up -d --remove-orphans --wait --wait-timeout 300 \
    || die "The stack did not become healthy. Check: docker compose -f $APP_DIR/docker-compose.yml logs app"

# ---------------------------------------------------------------------------
# 7. Check that the site responds through the proxy
# ---------------------------------------------------------------------------

step "Checking the site"

# Caddy may still be fetching its certificate, so retry for up to a minute
if curl -fsS -o /dev/null --retry 12 --retry-delay 5 --retry-all-errors \
    --resolve "$DOMAIN:$port:127.0.0.1" "$scheme://$DOMAIN/up"; then
    echo "$scheme://$DOMAIN/up returned 200."
else
    warn "$scheme://$DOMAIN/up did not return 200. Check: journalctl -u $PROXY, and docker compose logs app (from $APP_DIR)."
fi

cat <<EOF

Harbor Help is running from $APP_DIR behind $PROXY.

Next step: visit $scheme://$DOMAIN/install to create the workspace and the owner account.

Re-run this script at any time to update. To watch IMAP mailboxes for new mail within
seconds, set HARBOR_MAIL_WATCH=true in $APP_DIR/.env and run:
  docker compose --profile mail-watch up -d
EOF
if [[ -n "${SUDO_USER:-}" && "$SUDO_USER" != root ]]; then
    echo "Log out and back in so $SUDO_USER can run docker without sudo."
fi
