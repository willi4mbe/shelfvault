# ShelfVault

ShelfVault is a free, self-hosted web application for cataloging a personal physical media collection: films, video games, and board games.

## Product direction

- Free and self-hosted
- PHP/Laravel application
- Installable on NAS through Docker Compose
- Installable on a classic PHP web server with a WordPress-like installer
- Mobile-first PWA for iOS and Android
- One admin account
- Optional read-only guest sharing
- Optional barcode scanning and metadata providers

## Repository status

This repository currently contains the planning kit used before coding starts.
The first implementation task is `tickets/TICKET-001-scaffold-laravel.md`.

## Recommended workflow

1. Read `AGENTS.md`.
2. Read `docs/PRODUCT_SPEC.md`.
3. Read `docs/ARCHITECTURE.md`.
4. Work ticket by ticket.
5. Never commit secrets.
6. Use feature branches and Pull Requests.

## Technical target

- PHP 8.3+
- Laravel
- Livewire
- Alpine.js
- Tailwind CSS
- MariaDB by default
- PostgreSQL compatibility later
- ZXing JS for barcode scanning
- Docker Compose for NAS/server deployments

## License

To be decided before public release. Recommended: AGPL-3.0 or GPL-3.0 if the project is intended to remain free and open source.
