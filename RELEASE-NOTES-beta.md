# ShelfVault Beta Release Notes

These notes are written for GitHub Releases and package pages. They keep the focus on what a tester needs to know instead of listing every commit.

## 0.1.0-beta.6 - Shared-hosting polish

### What's New

- Unified the dark ShelfVault interface across the public library, admin area, login, and standalone installer.
- Added BoardGameGeek metadata lookup for board games, with ranking improvements and language-aware description handling.
- Improved TMDb and IGDB metadata search with clearer result loading.
- Added cover removal from the item edit form.
- Improved the public home page with recent additions, active loans, and compact library stats.
- Added public/private library visibility in admin settings.
- Strengthened the shared-hosting update flow with backup, SHA-256 ZIP verification, migrations, cache clear, and file rollback.

### Install / Update

- Fresh install: upload the beta.6 ZIP, point the domain to `public/` when possible, then open `/install.php`.
- Update: configure `SHELFVAULT_UPDATE_MANIFEST_URL`, then use `Admin > Settings > Updates`.
- A private backup is created before files are replaced.

### Known Limitations

- Database restore is still manual if you need to roll back data changes.
- The installer targets MySQL/MariaDB in this beta.
- External metadata providers must be configured after installation.
- The root `.htaccess` fallback is meant for constrained cPanel hosting; using `public/` as document root remains recommended.

## 0.1.0-beta.5 - Update package test

### What's New

- Refreshed the beta package and update manifest for a test release.
- Kept the shared-hosting installer and update path aligned with the beta package naming.

### Install / Update

- Fresh install: use the beta.5 ZIP and open `/install.php`.
- Update: publish the matching manifest and install from the admin update screen.

### Known Limitations

- This was mainly a packaging/update validation release.
- Backup restore remained manual.
- MySQL/MariaDB was the only installer target.

## 0.1.0-beta.4 - First shared-hosting beta

### What's New

- Added a standalone `/install.php` installer for classic PHP 8.3+ and MySQL/MariaDB hosting.
- Added English/French language choice at the start of the installer.
- Added server checks for PHP version, required extensions, and writable paths.
- Added database connection validation, automatic migrations, admin account creation, and `APP_KEY` generation.
- Added install locking through `storage/app/shelfvault/installed.lock`.
- Added `public/storage` creation with a Laravel fallback route for hosts that block symlinks.
- Added a root `.htaccess` fallback for cPanel deployments that must live under `public_html`.
- Added a beta packaging script and generated update manifests.
- Added the first shared-hosting update service with private staging, backup, SHA-256 checks, and rollback for replaced files.

### Install / Update

- Fresh install: upload the beta.4 ZIP, extract it, and open `/install.php`.
- Update testing: publish the generated ZIP and manifest, then point `SHELFVAULT_UPDATE_MANIFEST_URL` to the manifest.

### Known Limitations

- Shared-hosting support was new and needed real host testing.
- Database restore was manual.
- The installer only supported MySQL/MariaDB.
- External APIs were not part of the installation wizard.
