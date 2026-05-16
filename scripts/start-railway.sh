#!/usr/bin/env sh
set -e

php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
