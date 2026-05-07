# Git Workflow - ShelfVault

## Branches

- `main`: stable branch
- `feature/...`: new features
- `fix/...`: bug fixes
- `docs/...`: documentation only

## Rule

Do not work directly on `main`.

## Start a task

```bash
git checkout main
git pull
git checkout -b feature/ticket-xxx-short-name
```

## Review changes

```bash
git status
git diff
```

## Commit

```bash
git add .
git commit -m "feat: short description"
```

Recommended prefixes:

- `feat:` feature
- `fix:` bug fix
- `docs:` documentation
- `chore:` maintenance
- `test:` tests
- `refactor:` internal code cleanup

## Push

```bash
git push -u origin feature/ticket-xxx-short-name
```

## Pull Request checklist

- App boots
- Tests pass if available
- No secrets
- Docs updated
- Mobile impact checked
- Installer/Docker not broken
