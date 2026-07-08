#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f node_modules/.package-lock.json ] || [ ! -d public/build ]; then
    npm install
    npm run build
fi

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

if [ -f artisan ]; then
    php -r '
        $host = getenv("DB_HOST") ?: "postgres";
        $port = (int) (getenv("DB_PORT") ?: 5432);
        $deadline = time() + 90;
        do {
            $socket = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);
                exit(0);
            }
            fwrite(STDERR, "Waiting for database {$host}:{$port}...\n");
            sleep(2);
        } while (time() < $deadline);
        exit(1);
    ' || true

    php artisan key:generate --force --no-interaction >/dev/null 2>&1 || true
    php artisan storage:link --force >/dev/null 2>&1 || true
    php artisan migrate --force --no-interaction || true
fi

exec "$@"
