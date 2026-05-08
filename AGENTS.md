# AGENTS.md - ShelfVault

This file gives coding agents the permanent rules for working on ShelfVault.

## Project summary

ShelfVault is a free, self-hosted PHP/Laravel web application for managing a physical home collection of films, video games, and board games.

The app must be installable in two official ways:

1. NAS/local installation with Docker Compose.
2. Classic web server installation with a web-based setup wizard at `/install`.

## Product rules

- The core app must work without any external API.
- External integrations are optional.
- There is only one admin account in V1.
- Guests can only access read-only shared views.
- The app must be mobile-first and PWA-ready.
- The app must be usable on iOS and Android browsers.
- The app must be free to self-host.
- ShelfVault is bilingual by design.
- All pages in the project must exist in English and French.
- Every visible string must be translated in `lang/en` and `lang/fr`.
- No visible text may be hardcoded in views, controllers, or components.
- English is the default language.
- French must be maintained at the same quality level as English.

## Technical rules

- Use PHP 8.3+.
- Use Laravel.
- Prefer Livewire + Alpine.js + Tailwind CSS for UI.
- The web installer is the visual reference for the project.
- All pages must follow the same premium, sober, mobile-first style and stay aligned with the installer.
- Never introduce a page with the default Laravel look, a generic dashboard look, or an interface that is not aligned with the installer.
- All pages must use ShelfVault branding, the same card principles, spacing, buttons, typography, and responsive behavior.
- Use MariaDB/MySQL as the default database target.
- Keep PostgreSQL support possible by avoiding database-specific SQL unless necessary.
- Use Eloquent models and migrations.
- Use Laravel validation and policies/middleware.
- Use services for external providers.
- Never put API secrets in frontend JavaScript.
- Never commit real `.env` files or secrets.
- Uploads must be validated and stored safely.
- Admin routes must be protected.
- `/install` must be unavailable after successful installation.

## Naming

- Product name: ShelfVault
- Repository name: shelfvault
- Docker image target: ghcr.io/<owner>/shelfvault
- PHP namespace/application naming should use ShelfVault where relevant.

## Expected commands

When implementation exists, use the available project commands. Likely commands:

```bash
composer test
php artisan test
npm run build
npm run lint
```

If a command does not exist yet, add or document it when appropriate.

## Definition of done

A task is complete only when:

- The requested behavior is implemented.
- The app still boots.
- No secrets are committed.
- Database changes are made through migrations.
- Validation and authorization are handled.
- Mobile usability is considered.
- Documentation is updated if setup behavior changes.

## Do not do

- Do not build a marketplace.
- Do not require cloud services for the core app.
- Do not add multi-user complexity in V1.
- Do not depend on IGDB, TMDb, or barcode APIs for installation.
- Do not create native iOS/Android apps in V1.
- Do not implement payments in V1.
