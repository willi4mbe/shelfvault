# ShelfVault 0.1.0-beta.4

## Included

- Standalone `public/install.php` installer for classic PHP 8.3+ and MySQL/MariaDB hosting.
- First-page language choice in English and French.
- Server pre-checks for PHP version, PHP extensions, and writable paths.
- MySQL connection validation before installation continues.
- Automatic migrations during setup.
- Admin account creation with login, email, password, confirmation, and preferred language.
- Fresh `APP_KEY` generation during installation.
- `/install.php` and `/install` load before Laravel, so a fresh package without `.env` or `APP_KEY` can display setup without resolving Laravel sessions, cookies, Livewire, or the encrypter.
- `installed.lock` creation after a successful setup.
- Public storage link attempt plus Laravel fallback route for covers when symlinks are unavailable.
- Root `.htaccess` fallback for cPanel deployments that put the full project under `public_html`, with sensitive paths blocked.
- Production-safe `.env.example`.
- Unified dark ShelfVault visual system across public library, admin, login, and standalone installer.
- Public/private library visibility setting in admin settings.
- Shared-hosting update workflow based on a configurable HTTPS manifest, SHA-256 ZIP validation, private staging, automatic backup, migrations, cache clear, and file rollback.
- Beta packaging workflow with compiled assets, `vendor` included, and generated update manifests.

## Known limits

- Backup restore is manual in this beta. Update rollback restores replaced files automatically, but database restore still requires using the generated backup archive.
- External APIs are configured after installation in Settings and are not required for setup.
- The beta installer targets MySQL/MariaDB only.
- The recommended web root is still `public/`. The project-root `.htaccess` fallback is for constrained cPanel hosts and depends on Apache honoring rewrite/deny rules.
