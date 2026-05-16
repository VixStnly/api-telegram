#!/usr/bin/env sh
set -e

if [ -x userbot_worker/.venv/bin/python ]; then
    echo "Installing Pyrogram worker dependencies..."
    userbot_worker/.venv/bin/python -m pip install -r userbot_worker/requirements.txt
else
    echo "Pyrogram worker virtualenv was not found."
fi

php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
