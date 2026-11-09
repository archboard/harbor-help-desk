#!/usr/bin/env bash
#
# Installs (or updates) Harbor Help on a fresh Debian 12+ / Ubuntu 24.04+ server:
# PHP-FPM, PostgreSQL, Redis, Nginx + Let's Encrypt, Horizon, Reverb and the mailbox
# watcher under systemd, and the scheduler in cron.
#
# Safe to re-run: existing secrets and .env values are kept, and a re-run pulls
# the code, rebuilds, migrates and restarts everything.
#
#   curl -fsSL https://raw.githubusercontent.com/archboard/harbor-help-desk/main/install-vm.sh \
#     | sudo bash -s -- --domain help.example.org --email you@example.org --version v1.0.0

set -euo pipefail

DOMAIN="${DOMAIN:-}"
EMAIL="${EMAIL:-}"
REF="${HARBOR_VERSION:-${REF:-main}}"
REPO="${HARBOR_REPO:-https://github.com/archboard/harbor-help-desk.git}"
APP_DIR="${APP_DIR:-/var/www/harbor-help}"
APP_USER="${APP_USER:-harbor}"
DB_NAME="${DB_NAME:-harbor_help}"
NO_TLS="${NO_TLS:-0}"

PHP=8.5
NODE_MAJOR=24
PG_MAJOR=18
ACME_ROOT=/var/www/letsencrypt
APP_HOME="/home/$APP_USER"
FPM_SOCK="/run/php/$APP_USER.sock"

usage() {
    cat <<EOF
Usage: install-vm.sh --domain help.example.org [options]

  --domain DOMAIN    Public hostname (required; prompted for if omitted)
  --email EMAIL      Let's Encrypt expiry notices (optional)
  --version REF      Git branch or tag to deploy (default: main)
  --repo URL         Git repository or path (default: $REPO)
  --no-tls           Skip Let's Encrypt and serve plain HTTP
  --dir PATH         Install directory (default: /var/www/harbor-help)
  -h, --help         Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain) DOMAIN="$2"; shift 2 ;;
        --email) EMAIL="$2"; shift 2 ;;
        --version) REF="$2"; shift 2 ;;
        --repo) REPO="$2"; shift 2 ;;
        --no-tls) NO_TLS=1; shift ;;
        --dir) APP_DIR="$2"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
    esac
done

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33mWarning:\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31mError:\033[0m %s\n' "$*" >&2; exit 1; }

# Runs a command as the app user from the app directory with a sane HOME
# (runuser, unlike sudo, is always present on Debian minimal images)
as_app() { (cd "$APP_DIR" && runuser -u "$APP_USER" -- env HOME="$APP_HOME" "$@"); }

# Prompts read from the terminal so they still work under `curl | bash`
ask() {
    local prompt="$1" var="$2"
    if [[ -z "${!var}" && -r /dev/tty ]]; then
        # shellcheck disable=SC2229 # reads into the variable named by $var
        { read -rp "$prompt" "$var" < /dev/tty; } 2> /dev/null || true
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
# literal \n, which both Laravel's dotenv and Passport read back as newlines.
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

step "Checking the system"

[[ $EUID -eq 0 ]] || die "Run as root (sudo bash install-vm.sh ...)."
[[ -r /etc/os-release ]] || die "Cannot read /etc/os-release."
# shellcheck source=/dev/null
. /etc/os-release
case "${ID:-}" in
    debian|ubuntu) ;;
    *) die "Only Debian and Ubuntu are supported (found '${ID:-unknown}')." ;;
esac

# On a re-run, default to the domain the previous run set up
if [[ -z "$DOMAIN" ]]; then
    DOMAIN="$(grep -m1 '^APP_URL=' "$APP_DIR/.env" 2> /dev/null | sed -E 's|^APP_URL=https?://||; s|/.*$||' || true)"
    [[ "$DOMAIN" == localhost* ]] && DOMAIN=
fi

ask "Public domain for Harbor Help (e.g. help.example.org): " DOMAIN
[[ -n "$DOMAIN" ]] || die "--domain is required."
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "'$DOMAIN' is not a valid hostname."
if [[ "$NO_TLS" != 1 ]]; then
    ask "Email for Let's Encrypt notices (optional, Enter to skip): " EMAIL
fi

# Without swap a small server runs out of memory during the asset build
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

# ---------------------------------------------------------------------------

step "Installing system packages"

apt-get update -q
apt-get install -yq ca-certificates curl git unzip gnupg cron openssl

# Sury's repository covers PHP 8.5 on both Debian and Ubuntu
if ! apt-cache show "php$PHP-fpm" > /dev/null 2>&1; then
    curl -fsIo /dev/null "https://packages.sury.org/php/dists/${VERSION_CODENAME:-}/Release" \
        || die "packages.sury.org has no PHP packages for $PRETTY_NAME."
    curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
    dpkg -i /tmp/debsuryorg-archive-keyring.deb
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $VERSION_CODENAME main" \
        > /etc/apt/sources.list.d/php.list
    apt-get update -q
    apt-cache show "php$PHP-fpm" > /dev/null 2>&1 || die "PHP $PHP is not available for $PRETTY_NAME."
fi

if ! apt-cache show "postgresql-$PG_MAJOR" > /dev/null 2>&1; then
    apt-get install -yq postgresql-common
    /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
fi

node_major=$(node -v 2>/dev/null | sed -E 's/^v([0-9]+).*/\1/' || true)
if [[ -z "$node_major" || "$node_major" -lt 20 ]]; then
    curl -fsSL "https://deb.nodesource.com/setup_$NODE_MAJOR.x" | bash -
fi

# pcntl and opcache ship inside php-cli / php-fpm
apt-get install -yq \
    "php$PHP-fpm" "php$PHP-cli" "php$PHP-pgsql" "php$PHP-redis" "php$PHP-intl" \
    "php$PHP-zip" "php$PHP-mbstring" "php$PHP-xml" "php$PHP-curl" "php$PHP-bcmath" \
    "postgresql-$PG_MAJOR" redis-server nginx certbot nodejs

if ! command -v composer > /dev/null; then
    expected=$(curl -fsSL https://composer.github.io/installer.sig)
    curl -fsSLo /tmp/composer-setup.php https://getcomposer.org/installer
    actual=$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")
    [[ "$expected" == "$actual" ]] || die "Composer installer checksum mismatch."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

systemctl enable --now postgresql redis-server nginx "php$PHP-fpm" cron

# ---------------------------------------------------------------------------

step "Fetching the code ($REF)"

id "$APP_USER" > /dev/null 2>&1 || useradd --create-home --home-dir "$APP_HOME" --shell /bin/bash "$APP_USER"
mkdir -p "$APP_DIR"
chown "$APP_USER:$APP_USER" "$APP_DIR"

if [[ -d "$APP_DIR/.git" ]]; then
    UPDATING=1
    # Show the maintenance page while the update runs (skipped if an earlier
    # run stopped before composer install)
    [[ -f "$APP_DIR/vendor/autoload.php" ]] && { as_app php artisan down --retry=15 || true; }
    trap 'as_app php artisan up > /dev/null 2>&1 || true' EXIT
    as_app git fetch --tags --force origin
else
    UPDATING=0
    [[ -z "$(ls -A "$APP_DIR")" ]] || die "$APP_DIR exists and is not a git checkout."
    runuser -u "$APP_USER" -- git clone "$REPO" "$APP_DIR"
fi

as_app git checkout -q "$REF"
# Branches fast-forward to the remote; tags are already exact
if as_app git symbolic-ref -q HEAD > /dev/null; then
    as_app git merge -q --ff-only "origin/$REF"
fi

# ---------------------------------------------------------------------------

step "Configuring PostgreSQL"

if [[ ! -f "$APP_DIR/.env" ]]; then
    install -o "$APP_USER" -g "$APP_USER" -m 640 "$APP_DIR/.env.example" "$APP_DIR/.env"
    # First-run defaults that admins may later tune; never reset on re-runs
    env_set APP_NAME '"Harbor Help"'
    env_set LOG_CHANNEL daily
    env_set LOG_LEVEL warning
fi

env_default DB_PASSWORD "$(openssl rand -hex 24)"
db_password=$(env_get DB_PASSWORD)

runuser -u postgres -- psql -v ON_ERROR_STOP=1 -q <<SQL
SELECT 'CREATE ROLE "$APP_USER" LOGIN' WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$APP_USER')\gexec
ALTER ROLE "$APP_USER" PASSWORD '$db_password';
SELECT 'CREATE DATABASE "$DB_NAME" OWNER "$APP_USER"' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '$DB_NAME')\gexec
SQL

# ---------------------------------------------------------------------------

step "Configuring PHP-FPM"

cat > "/etc/php/$PHP/fpm/pool.d/harbor-help.conf" <<EOF
[harbor-help]
user = $APP_USER
group = $APP_USER
listen = $FPM_SOCK
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
EOF

systemctl reload "php$PHP-fpm"

# ---------------------------------------------------------------------------

step "Configuring Nginx"

CERT_DIR="/etc/letsencrypt/live/$DOMAIN"
mkdir -p "$ACME_ROOT"

write_nginx() {
    local tls="$1"
    local app_server
    app_server=$(cat <<EOF
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 25m;

    location ^~ /.well-known/acme-challenge/ {
        root $ACME_ROOT;
    }

    # Reverb websockets
    location ~ ^/apps?(/|\$) {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \$connection_upgrade;
        proxy_set_header Host \$host;
        proxy_read_timeout 86400;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|\$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
        # The Link preload headers for fonts and assets overflow the 4k default
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 16k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
EOF
)

    {
        cat <<'EOF'
# Managed by install-vm.sh; re-running the script overwrites this file.
map $http_upgrade $connection_upgrade {
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

    ssl_certificate     $CERT_DIR/fullchain.pem;
    ssl_certificate_key $CERT_DIR/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

$app_server
}
EOF
        else
            cat <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

$app_server
}
EOF
        fi
    } > /etc/nginx/sites-available/harbor-help

    ln -sf /etc/nginx/sites-available/harbor-help /etc/nginx/sites-enabled/harbor-help
    rm -f /etc/nginx/sites-enabled/default
    nginx -t -q || die "Nginx rejected the generated config (/etc/nginx/sites-available/harbor-help)."
    systemctl reload nginx
}

if ufw status 2> /dev/null | grep -q "Status: active"; then
    ufw allow 'Nginx Full' > /dev/null
fi

TLS=0
if [[ "$NO_TLS" != 1 ]]; then
    if [[ -f "$CERT_DIR/fullchain.pem" ]]; then
        TLS=1
    else
        # Serve the ACME challenge over HTTP first, then switch to HTTPS
        write_nginx 0
        email_args=(--register-unsafely-without-email)
        [[ -n "$EMAIL" ]] && email_args=(--email "$EMAIL")
        if certbot certonly --webroot -w "$ACME_ROOT" -d "$DOMAIN" --non-interactive --agree-tos \
            "${email_args[@]}" --deploy-hook "systemctl reload nginx"; then
            TLS=1
        else
            warn "Could not get a certificate for $DOMAIN. Check that its DNS points at this server and ports 80/443 are open, then re-run this script. Continuing over plain HTTP."
        fi
    fi
fi
write_nginx "$TLS"

if [[ "$TLS" == 1 ]]; then scheme=https; port=443; else scheme=http; port=80; fi

# ---------------------------------------------------------------------------

step "Writing .env"

env_default APP_KEY "base64:$(openssl rand -base64 32)"
env_default REVERB_APP_ID "$(shuf -i 100000-999999 -n 1)"
env_default REVERB_APP_KEY "$(openssl rand -hex 16)"
env_default REVERB_APP_SECRET "$(openssl rand -hex 32)"
env_passport_keys

env_set APP_ENV production
env_set APP_DEBUG false
env_set APP_URL "$scheme://$DOMAIN"
env_set HARBOR_MULTITENANT false
env_set DB_CONNECTION pgsql
env_set DB_HOST 127.0.0.1
env_set DB_PORT 5432
env_set DB_DATABASE "$DB_NAME"
env_set DB_USERNAME "$APP_USER"
env_set CACHE_STORE redis
env_set SESSION_DRIVER redis
env_set QUEUE_CONNECTION redis
env_set REDIS_HOST 127.0.0.1
env_set BROADCAST_CONNECTION reverb
env_set REVERB_HOST "$DOMAIN"
env_set REVERB_PORT "$port"
env_set REVERB_SCHEME "$scheme"
env_set REVERB_SERVER_HOST 127.0.0.1
env_set REVERB_SERVER_PORT 8080
# The unit below runs the mailbox watcher, which refuses to start without this
env_set HARBOR_MAIL_WATCH true
# A tag records itself as the running version; a branch has no version to record
if as_app git describe --tags --exact-match > /dev/null 2>&1; then
    env_set HARBOR_VERSION "$(as_app git describe --tags --exact-match)"
fi

chown "$APP_USER:$APP_USER" "$APP_DIR/.env"
chmod 640 "$APP_DIR/.env"

# ---------------------------------------------------------------------------

step "Building the application"

as_app composer install --no-dev --optimize-autoloader --no-interaction --no-progress
as_app npm ci --no-audit --no-fund
as_app npm run build
as_app php artisan migrate --force --no-interaction
as_app php artisan optimize --no-interaction

# ---------------------------------------------------------------------------

step "Configuring background services"

write_unit() {
    local name="$1" description="$2" command="$3" extra="${4:-}"
    cat > "/etc/systemd/system/harbor-help-$name.service" <<EOF
[Unit]
Description=Harbor Help $description
After=network.target redis-server.service postgresql.service

[Service]
User=$APP_USER
Group=$APP_USER
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan $command
Restart=always
RestartSec=3
$extra

[Install]
WantedBy=multi-user.target
EOF
}

write_unit reverb "Reverb websocket server" "reverb:start --host=127.0.0.1 --port=8080" "LimitNOFILE=10000"
write_unit horizon "Horizon queue workers" "horizon" "TimeoutStopSec=60"
write_unit mail-watch "mailbox watcher" "mailboxes:watch"

echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" | crontab -u "$APP_USER" -

systemctl daemon-reload
systemctl enable -q harbor-help-reverb harbor-help-horizon harbor-help-mail-watch
# Long-running workers keep old code in memory, so always restart them
systemctl restart harbor-help-reverb harbor-help-horizon harbor-help-mail-watch
systemctl reload "php$PHP-fpm"

if [[ "$UPDATING" == 1 ]]; then
    as_app php artisan up
    trap - EXIT
fi

# ---------------------------------------------------------------------------

step "Checking the site"

sleep 2
status=$(curl -s -o /dev/null -w '%{http_code}' --resolve "$DOMAIN:$port:127.0.0.1" "$scheme://$DOMAIN/up" || true)
if [[ "$status" == 200 ]]; then
    echo "$scheme://$DOMAIN/up returned 200."
else
    warn "$scheme://$DOMAIN/up returned '$status'. Check $APP_DIR/storage/logs and /var/log/nginx/error.log."
fi

cat <<EOF

Harbor Help is installed at $APP_DIR.

Next step: visit $scheme://$DOMAIN/install to create the workspace and the owner account.

Update later with: cd $APP_DIR && sudo ./update-vm.sh --version <tag>
EOF
