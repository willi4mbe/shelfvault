# ShelfVault

ShelfVault is a free, self-hosted web application for cataloging a personal physical media collection: films, video games, and board games.

## Product direction

- Free and self-hosted
- PHP/Laravel application
- Installable on NAS through Docker Compose
- Installable on a classic PHP web server with a web-based setup wizard
- Mobile-first PWA for iOS and Android
- One admin account
- Optional read-only guest sharing
- Optional barcode scanning and metadata providers

## Repository status

The Laravel foundation from `tickets/TICKET-001-scaffold-laravel.md` is in place.
The installer, admin authentication, catalog CRUD, barcode scanning, and external integrations are not implemented yet.

## Recommended workflow

1. Read `AGENTS.md`.
2. Read `docs/PRODUCT_SPEC.md`.
3. Read `docs/ARCHITECTURE.md`.
4. Work ticket by ticket.
5. Never commit secrets.
6. Use feature branches and Pull Requests.

## Technical target

- PHP 8.3+
- Laravel 13
- Livewire
- Alpine.js
- Tailwind CSS
- MariaDB by default
- PostgreSQL compatibility later
- ZXing JS for barcode scanning
- Docker Compose for NAS/server deployments

## Local development

Install PHP 8.3+, Composer, Node.js, and NPM.

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

The application will be available at `http://localhost:8000`.

For a Vite development server, run this in a second terminal:

```bash
npm run dev
```

## Docker development skeleton

Docker Compose is prepared for local development with PHP-FPM, Nginx, and MariaDB.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The Docker web entrypoint is `http://localhost:8080`.

## Verification

```bash
composer test
php artisan test
npm run build
```

Do not commit `.env`, generated application keys, database dumps, uploaded files, `vendor/`, or `node_modules/`.

## License

To be decided before public release. Recommended: AGPL-3.0 or GPL-3.0 if the project is intended to remain free and open source.
