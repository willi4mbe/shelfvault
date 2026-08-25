# GitHub Repository Setup

This page tracks the public repository presentation for ShelfVault.

## Recommended About Text

```text
Self-hosted media library for movies, TV shows, video games and board games.
```

## Recommended Topics

```text
self-hosted
media-library
laravel
php
movies
tv-shows
video-games
board-games
homelab
shared-hosting
tmdb
igdb
boardgamegeek
```

## Branches

- `develop`: active integration branch.
- `release/beta-shared-hosting`: beta package and manifest preparation.
- `feature/...`: focused feature work before merge.
- `fix/...`: focused bug fixes before merge.

## Release Checklist

Before publishing a GitHub Release:

- version updated in `VERSION`;
- fallback version updated in `config/shelfvault.php`;
- beta ZIP generated with `php scripts/build-beta-package.php`;
- `releases/update-manifest.json` points to the correct version, ZIP URL, and SHA-256;
- install guides mention the right beta version;
- release notes follow [`RELEASE_TEMPLATE.md`](../RELEASE_TEMPLATE.md);
- generated `dist/` files are not committed unless there is a deliberate release-storage decision.

## Never Commit

- `.env`
- passwords or API keys
- database dumps
- generated backups
- uploaded private covers
- personal access tokens
- SSH private keys
- `vendor/`, `node_modules/`, `dist/`, or `public/build/`
