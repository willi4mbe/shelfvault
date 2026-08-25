<p align="center">
  <img src="public/branding/shelfvault.png" alt="ShelfVault" width="520">
</p>

# ShelfVault

ShelfVault is a self-hosted media library for movies, TV shows, video games, and board games.

It is built for people who want a clean catalogue of their own collection, with covers, metadata, favorites, loans, and a public or private read-only library they can host themselves.

> Status: beta. ShelfVault is usable for testing, but the data model and update workflow can still change before a stable release.

## What It Does

- Browse a media-library style frontend with covers, filters, recent additions, favorites, and loan status.
- Add movies and TV shows with TMDb metadata.
- Add video games with IGDB metadata.
- Add board games with BoardGameGeek metadata.
- Scan or enter barcodes from the admin item form.
- Track loans, borrowers, return dates, and favorite items.
- Keep the library public for sharing or private behind the admin login.
- Install on classic PHP/MySQL hosting through a web installer.
- Update from the admin area with a manifest URL, SHA-256 ZIP checks, automatic backup, migrations, and file rollback.
- Run locally or on a NAS/server with Docker Compose for development and self-hosting experiments.

## Screenshots

Screenshots are not committed yet. The intended location is [`docs/screenshots/`](docs/screenshots/), with stable filenames that can be referenced from this README once real captures are available.

Recommended first captures:

- Public home page
- Collection grid
- Item detail page
- Admin item form with metadata search
- Settings/update screen
- Web installer

## Quick Install

ShelfVault beta.6 targets PHP 8.3+ and MySQL/MariaDB.

For shared hosting or o2switch/cPanel, use the packaged ZIP and the web installer:

1. Create an empty MySQL or MariaDB database.
2. Upload and extract `ShelfVault-0.1.0-beta.6.zip`.
3. Prefer pointing the domain document root to `public/`.
4. Open `https://your-domain/install.php`.
5. Follow the installer, create the admin account, then sign in at `/admin/login`.

Detailed guides:

- [English install guide](README-INSTALL-EN.md)
- [Guide d'installation en francais](README-INSTALL-FR.md)

## API Configuration

External metadata providers are optional during installation. Configure them later in `Admin > Settings`.

| Provider | Used for | Notes |
| --- | --- | --- |
| TMDb | Movies and TV shows | API key or bearer token |
| IGDB | Video games | Twitch client ID and access token |
| BoardGameGeek | Board games | Public XML API; optional local token setting |
| Barcode lookup | Barcode-assisted entries | Provider-based; not enabled by default |

If a provider is not configured, ShelfVault still works with manual entries.

## Updates

Beta updates can be installed from `Admin > Settings > Updates` when `SHELFVAULT_UPDATE_MANIFEST_URL` points to a valid HTTPS manifest.

Before replacing files, ShelfVault creates a private backup under `storage/app/shelfvault/backups`, verifies the update ZIP with SHA-256, preserves `.env` and `storage/`, runs migrations, clears caches, and attempts rollback if file replacement fails.

Backup restore is still manual in beta. Keep your own hosting/database backups before testing updates.

## Local Development

Requirements:

- PHP 8.3+
- Composer
- Node.js and npm
- MySQL/MariaDB, or another database supported by your local Laravel setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Run the Vite development server in a second terminal when working on assets:

```bash
npm run dev
```

Run tests:

```bash
composer test
npm run build
```

## Docker Development

Docker Compose is available for local development with PHP-FPM, Nginx, and MariaDB.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The Docker web entrypoint is `http://localhost:8080`.

## Roadmap

Short-term beta focus:

- polish the shared-hosting install and update path;
- add real screenshots and a small demo dataset;
- document backup restore clearly;
- stabilize release packaging and GitHub release notes;
- decide the final open-source license before a stable release.

## Repository Notes

- `develop` is the active integration branch.
- `release/beta-shared-hosting` is used for beta package/release preparation.
- Generated archives, dependencies, local `.env` files, caches, and uploaded covers should stay out of Git.

## License

No final license has been selected yet. Do not reuse the code as an open-source dependency until a license file is added.
