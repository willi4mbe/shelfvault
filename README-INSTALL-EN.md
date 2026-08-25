# ShelfVault beta.6 - PHP/MySQL Install Guide

This guide covers the beta package for classic PHP hosting, including o2switch/cPanel.

## Requirements

- PHP 8.3 or newer.
- An empty MySQL or MariaDB database.
- PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`.
- Writable paths during installation: `storage/`, `storage/app/public/`, `storage/framework/`, `storage/logs/`, `bootstrap/cache/`, and the project root.
- Recommended permissions: files `640`/`644`, directories `755` or `775` depending on the host. Avoid `777`.

## Recommended Layout

Best option:

```text
~/shelfvault          application files
~/shelfvault/public  domain document root
```

This keeps Laravel internals outside the public web root.

cPanel fallback:

If your host forces everything into `public_html`, extract the whole project there. The root `.htaccess` blocks sensitive paths such as `app/`, `config/`, `database/`, private `storage/` folders, `vendor/`, `.env`, `composer.*`, and forwards requests to `public/`.

The fallback is useful for constrained shared hosting, but the `public/` document root remains the cleaner setup.

## Fresh Install

1. Create an empty MySQL/MariaDB database and database user in cPanel.
2. Upload `ShelfVault-0.1.0-beta.6.zip` or `ShelfVault-beta.zip`.
3. Extract the archive.
4. Open `https://your-domain/install.php`.
5. Choose English or French.
6. Fix any requirement flagged by the installer.
7. Enter the database host, port, database name, username, and password.
8. Review `APP_URL`; adjust it if auto-detection is wrong.
9. Create the admin account with a password of at least 12 characters.
10. Confirm the final step.

The installer writes `.env`, generates a fresh `APP_KEY`, runs migrations, creates the admin user, writes `storage/app/shelfvault/installed.lock`, and redirects to `/admin/login`.

## Covers and Public Storage

ShelfVault tries to create `public/storage` during setup. If the host blocks symlinks, covers are still served by a Laravel fallback route under `/storage/...`.

## After Installation

`install.php` is blocked automatically once `storage/app/shelfvault/installed.lock` exists.

You can delete `install.php` after setup to reduce the visible surface, but the lock file is what protects the installer.

## Public or Private Library

In `Admin > Settings`, `Library visibility` controls the read-only frontend:

- `Public`: visitors can browse the library without signing in.
- `Private`: library pages redirect to `/admin/login`, then return to the requested page after login.

The admin area is always protected.

## Metadata Providers

Configure providers in `Admin > Settings` after installation:

- TMDb for movies and TV shows.
- IGDB for video games.
- BoardGameGeek for board games.

Manual entries work even when no provider is configured.

## In-App Updates

1. Publish the ZIP for the next beta on an HTTPS URL.
2. Publish a matching HTTPS JSON manifest:

```json
{
  "version": "0.1.0-beta.6",
  "tag_name": "v0.1.0-beta.6",
  "name": "ShelfVault 0.1.0-beta.6",
  "html_url": "https://example.com/releases/0.1.0-beta.6",
  "zip_url": "https://example.com/ShelfVault-0.1.0-beta.6.zip",
  "sha256": "ZIP_SHA256",
  "notes": "Release notes",
  "minimum_php": "8.3.0",
  "requires_migrations": true
}
```

3. Set `SHELFVAULT_UPDATE_MANIFEST_URL=https://.../update-manifest.json` in `.env`.
4. Go to `Admin > Settings > Updates`.
5. Click `Check for updates`, then `Download and install`.

Before replacing files, ShelfVault creates a backup in `storage/app/shelfvault/backups`, downloads the ZIP into `storage/app/shelfvault/updates`, verifies SHA-256, extracts to a private staging folder, replaces application files, runs migrations with `--force`, clears caches, and retries `storage:link`.

Preserved paths:

- `.env`
- `storage/`
- `storage/app/shelfvault/installed.lock`
- `public/storage`

If replacement or post-update commands fail, ShelfVault restores replaced files from the rollback copy. Database restore is still manual in this beta.

## Building a Beta Package

```bash
SHELFVAULT_UPDATE_ZIP_URL=https://your-url/ShelfVault-0.1.0-beta.6.zip php scripts/build-beta-package.php
```

The script writes the ZIP and manifests under `dist/`.

Do not commit or publish generated backups. They can contain `.env`, database dumps, uploaded covers, and other private data.
