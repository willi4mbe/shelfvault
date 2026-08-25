# ShelfVault - Technical Architecture

## Recommended stack

- PHP 8.3+
- Laravel
- Livewire
- Alpine.js
- Tailwind CSS
- MariaDB/MySQL default
- PostgreSQL compatible later
- Docker Compose
- Nginx + PHP-FPM for production Docker
- ZXing JS for barcode scanning

## Deployment modes

### NAS/local Docker

- `app` container: Laravel/PHP-FPM
- `web` container: Nginx
- `db` container: MariaDB
- volumes:
  - database data
  - uploaded covers
  - backups

### Classic web server

- Upload application files
- Point web root to `/public`
- Open `/install.php`
- Configure DB and admin account

## Main modules

```text
ShelfVault
├── Installer
├── Admin Auth
├── Collection
├── Media Details
├── Locations
├── Loans
├── Wishlist
├── Guest Sharing
├── Barcode Scanner
├── PWA
├── Imports/Exports
└── Optional Integrations
```

## Suggested database models

### users

One admin user in V1.

Installer-created admin fields:

- login
- email
- password
- preferred_locale
- two_factor_secret nullable, reserved for future 2FA
- two_factor_recovery_codes nullable, reserved for future 2FA
- two_factor_confirmed_at nullable, reserved for future 2FA

### items

- id
- type: film, video_game, board_game
- title
- subtitle
- description
- release_year
- publisher
- barcode
- condition
- completeness
- status
- location_id
- cover_path
- notes
- created_at
- updated_at

### item_details

Flexible details depending on item type.

- item_id
- platform
- format
- edition
- region
- language
- subtitles
- player_count_min
- player_count_max
- duration_minutes
- age_rating
- metadata_json

### locations

- name
- room
- shelf
- notes

### loans

- item_id
- borrower_name
- borrower_contact
- loaned_at
- expected_return_at
- returned_at
- status
- notes

### barcodes

- item_id
- value
- format
- source

### external_references

- item_id
- provider
- external_id
- url
- metadata_json

### settings

- key
- value

### share_links

- token
- enabled
- password_hash nullable
- show_locations
- show_loan_status
- expires_at nullable

## Installer requirements

The web-based setup wizard must:

1. Check PHP version.
2. Check extensions.
3. Check writable folders.
4. Test DB connection.
5. Write `.env` or equivalent config.
6. Generate app key.
7. Run migrations.
8. Create admin account.
9. Create installation lock.
10. Redirect to admin login.

The setup wizard is a standalone `public/install.php` entrypoint that runs before the Laravel bootstrap. It must not depend on Laravel sessions, cookies, Livewire, the encrypter, or a configured database for its first page. A successful setup creates `storage/app/shelfvault/installed.lock`; after that, `install.php` is blocked and normal application routes are available. `/install` is only a compatibility entrypoint that is intercepted before Laravel where possible.

## Security notes

- Store API keys server-side only.
- Hash passwords with Laravel defaults.
- CSRF protection enabled.
- Validate uploads by MIME and size.
- Store uploads outside executable paths when possible.
- Protect `install.php` after install with the lock file.
- Use signed/random share tokens.

## Barcode scanner flow

```text
Phone camera
↓
Browser getUserMedia
↓
ZXing JS
↓
Detected EAN/UPC
↓
Laravel lookup endpoint
↓
Local barcode match
↓
Optional external providers
↓
Prefill item form
```
