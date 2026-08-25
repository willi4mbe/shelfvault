# Git Workflow

ShelfVault currently uses `develop` as the active integration branch.

## Branches

- `develop`: active branch for reviewed work.
- `release/beta-shared-hosting`: beta packaging and update manifest preparation.
- `feature/...`: new user-facing or technical work.
- `fix/...`: focused bug fixes.
- `docs/...`: documentation-only changes.

## Start Work

```bash
git checkout develop
git pull origin develop
git checkout -b feature/short-description
```

## Review Local Changes

```bash
git status
git diff
```

## Commit Style

Use short, direct commit messages:

- `feat: add board game metadata lookup`
- `fix: preserve installed version after update`
- `docs: refresh shared-hosting install guide`

Common prefixes:

- `feat:` feature
- `fix:` bug fix
- `docs:` documentation
- `chore:` maintenance
- `test:` tests
- `refactor:` internal cleanup

## Pull Request Checklist

- App boots.
- Relevant tests pass.
- Frontend assets build when CSS or JS changed.
- No secrets, generated archives, local uploads, or backups are committed.
- Docs are updated for install, update, or user-facing changes.
- Shared-hosting and Docker impact is considered when relevant.
