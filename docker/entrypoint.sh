#!/bin/sh
set -e

# Caches bake env values, so they're built at start rather than image build
mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
php artisan optimize --no-interaction

exec "$@"
