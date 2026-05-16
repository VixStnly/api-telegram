FROM php:8.2-cli-bookworm

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        default-mysql-client \
        git \
        libzip-dev \
        nodejs \
        npm \
        python3 \
        python3-pip \
        python3-venv \
        unzip \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

COPY userbot_worker/requirements.txt userbot_worker/requirements.txt
RUN python3 -m venv userbot_worker/.venv \
    && userbot_worker/.venv/bin/python -m pip install --upgrade pip \
    && userbot_worker/.venv/bin/python -m pip install --no-cache-dir -r userbot_worker/requirements.txt

COPY . .

RUN composer dump-autoload --optimize \
    && npm run build \
    && mkdir -p storage/app/telegram-sessions storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x scripts/start-railway.sh

CMD ["scripts/start-railway.sh"]
