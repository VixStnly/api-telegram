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

mkdir -p storage/logs

if [ -x userbot_worker/.venv/bin/python ]; then
    echo "Starting Pyrogram !share watcher..."
    nohup userbot_worker/.venv/bin/python userbot_worker/worker.py watch-shares --delay "${SHARE_DELAY:-5}" --refresh 30 > storage/logs/userbot-share-watcher.log 2>&1 &
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
