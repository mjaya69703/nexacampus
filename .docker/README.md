# NexaCampus Docker

All Docker-related files live in this `.docker` directory.

## Start with PostgreSQL

```bash
cd .docker
copy .env.example .env
docker compose up -d --build
```

App: <http://localhost:8080>

Mailpit: <http://localhost:8025>

## Start with MariaDB

Edit `.docker/.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
```

Then run:

```bash
cd .docker
docker compose --profile mariadb up -d --build
```

## Useful Commands

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan notifications:prune-logs --dry-run
docker compose exec app php artisan whatsapp:sidecar:install
docker compose exec app php artisan whatsapp:sidecar:start
```

## Notes

- The default database is PostgreSQL.
- MariaDB is opt-in via the `mariadb` profile.
- Composer dependencies are stored in the `app-vendor` volume.
- Node dependencies are stored in the `app-node-modules` volume.
- Laravel storage is stored in the `app-storage` volume.
- WhatsApp web session state is stored in the `whatsapp-sidecar` volume.
