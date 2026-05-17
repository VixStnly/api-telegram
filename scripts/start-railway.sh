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
    (
        while true; do
            echo "[$(date -Is)] Starting Pyrogram !share watcher..."
            userbot_worker/.venv/bin/python userbot_worker/worker.py watch-shares --delay "${SHARE_DELAY:-0}" --refresh "${SHARE_REFRESH:-1}"
            code="$?"
            echo "[$(date -Is)] Pyrogram !share watcher stopped with exit code ${code}; restarting in 5 seconds..."
            sleep 5
        done
    ) > storage/logs/userbot-share-watcher.log 2>&1 &
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
