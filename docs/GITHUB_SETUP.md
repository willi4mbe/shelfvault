# GitHub Setup - ShelfVault

## Goal

Use GitHub as the central source of truth so the project can be developed from home and work computers.

## Recommended repository

- Name: `shelfvault`
- Visibility: private during development
- Default branch: `main`
- Description: `Self-hosted physical media collection manager for films, video games and board games.`

## Initial setup

```bash
mkdir shelfvault
cd shelfvault
git init
```

Copy this kit into the folder, then:

```bash
git add .
git commit -m "docs: add ShelfVault planning kit"
git branch -M main
git remote add origin git@github.com:YOUR_USERNAME/shelfvault.git
git push -u origin main
```

## Working from another computer

```bash
git clone git@github.com:YOUR_USERNAME/shelfvault.git
cd shelfvault
```

## Daily sync

Start work:

```bash
git checkout main
git pull
```

Create a feature branch:

```bash
git checkout -b feature/ticket-001-scaffold-laravel
```

Finish work:

```bash
git status
git add .
git commit -m "feat: scaffold Laravel application"
git push -u origin feature/ticket-001-scaffold-laravel
```

Then open a Pull Request on GitHub.

## Never commit

- `.env`
- passwords
- API keys
- database dumps with private data
- personal access tokens
- SSH private keys
