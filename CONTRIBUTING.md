# Contributing to ShelfVault

ShelfVault is still in beta, so small, focused contributions are easiest to review.

## Before Opening a Pull Request

- Start from the `develop` branch.
- Keep the change focused on one problem.
- Avoid committing generated files, local caches, `.env`, uploads, backups, `vendor/`, `node_modules/`, `dist/`, or `public/build/`.
- Update documentation when install, update, or user-facing behavior changes.

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Checks

Run the relevant checks before submitting:

```bash
composer test
npm run build
```

If a check cannot be run, mention why in the pull request.

## Pull Request Notes

Useful PR descriptions answer:

- What changed?
- Why was it needed?
- How was it tested?
- Does it affect install, update, metadata providers, or existing data?
