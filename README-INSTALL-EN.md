# ShelfVault beta 0.1.0-beta.4 - PHP + MySQL install

## Requirements

- PHP 8.3 or newer.
- Empty MySQL or MariaDB database.
- PHP extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pdo, pdo_mysql, session, tokenizer, xml.
- Writable `storage/`, `storage/app/public/`, `storage/framework/`, `storage/logs/`, `bootstrap/cache/`, and project root during installation.
- Recommended permissions: files 640/644, directories 755 or 775 depending on the host. Avoid 777.

## o2switch / cPanel install

1. Create an empty MySQL/MariaDB database and MySQL user in cPanel, then keep the host, port, database name, username, and password.
2. Upload `ShelfVault-0.1.0-beta.4.zip` or `ShelfVault-beta.zip`, then extract it.
3. Recommended: keep the project outside the web root, for example `~/shelfvault`, and point the domain document root to `~/shelfvault/public`.
4. cPanel fallback: if the host forces everything into `public_html`, extract the whole project there. The root `.htaccess` blocks `app/`, `config/`, `database/`, sensitive `storage/` subdirectories, `vendor/`, `.env`, `composer.*`, and routes requests into `public/`. This is less clean than pointing the document root at `public/`.
5. Open `https://your-domain/install.php` and choose English or Francais.
6. Fix any reported requirement, enter the MySQL/MariaDB details, and let ShelfVault test the connection.
7. Adjust `APP_URL` if auto-detection is not correct, then create the admin account with a password of at least 12 characters.
8. Confirm the review step. The installer generates a random `APP_KEY`, writes `.env`, runs migrations, creates the admin, writes `storage/app/shelfvault/installed.lock`, and redirects to `/admin/login`.

## Covers and storage

ShelfVault tries to create `public/storage` during setup. If the host blocks symlinks, covers are still served through the safe Laravel fallback route `/storage/...`.

## Security after installation

`install.php` is automatically blocked by `storage/app/shelfvault/installed.lock`. You may delete it manually after setup to reduce the visible surface, but deletion is not required for wizard security.

## Public or private library

In `Admin > Settings`, `Library visibility` offers:

- `Public`: the read-only frontend stays available without login.
- `Private`: library pages redirect to `/admin/login`, then return to the requested URL after login. The admin area always remains protected.

The default remains `public` to avoid breaking existing beta installs.

## o2switch updates

1. Publish the ZIP for a newer beta on an HTTPS URL.
2. Publish an HTTPS JSON manifest using this format:

```json
{
  "version": "0.1.0-beta.5",
  "tag_name": "v0.1.0-beta.5",
  "name": "ShelfVault 0.1.0-beta.5",
  "html_url": "https://example.com/releases/0.1.0-beta.5",
  "zip_url": "https://example.com/ShelfVault-0.1.0-beta.5.zip",
  "sha256": "ZIP_SHA256",
  "notes": "Release notes",
  "minimum_php": "8.3.0",
  "requires_migrations": true
}
```

3. Set `SHELFVAULT_UPDATE_MANIFEST_URL=https://.../update-manifest.json` in `.env`.
4. In `Admin > Settings > Updates`, click `Check for updates`, then `Download and install`.

Before replacing files, ShelfVault creates a backup in `storage/app/shelfvault/backups`. The ZIP is downloaded to `storage/app/shelfvault/updates`, verified with SHA-256, extracted to staging, then application files are replaced while preserving `.env`, `storage/`, `storage/app/shelfvault/installed.lock`, and `public/storage`. Migrations run with `--force`, caches are cleared, and `storage:link` is retried. If replacement or post-update commands fail, replaced files are restored from the rollback copy.

`php scripts/build-beta-package.php` also generates `dist/update-manifest-<version>.json` and `dist/update-manifest-beta.json`. To publish a test beta, run the script with `SHELFVAULT_UPDATE_ZIP_URL=https://your-url/ShelfVault-<version>.zip`, then upload the ZIP and manifest.

## Backup and rollback

Backups are not public: they stay under `storage/app/shelfvault/backups`. They contain a SQLite/database dump or portable SQL dump, `.env`, `installed.lock`, and `storage/app/public`. Never publish these archives because they contain the secrets needed for reliable restore.
