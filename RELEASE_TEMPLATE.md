# ShelfVault x.y.z-beta.n - Short Title

Use this template for GitHub Releases. Keep it short, concrete, and written for someone installing or testing the app.

## What's New

- User-facing change.
- Install/update change.
- Notable fix or limitation removed.

## Install / Update

- Fresh install: download the ZIP, upload it to the host, and open `/install.php`.
- Update: configure `SHELFVAULT_UPDATE_MANIFEST_URL`, then use `Admin > Settings > Updates`.
- Back up the database and files before testing beta updates.

## Known Limitations

- List only limitations that matter for this release.
- Mention manual steps clearly.
- Avoid commit lists and internal implementation detail unless they affect the user.

## Checks Before Publishing

- `VERSION` matches the release version.
- `config/shelfvault.php` fallback version matches the release version.
- `releases/update-manifest.json` points to the right ZIP and SHA-256.
- `README-INSTALL-EN.md` and `README-INSTALL-FR.md` mention the right beta package.
- Package ZIP was tested on a clean install.
- In-app update was tested from the previous beta.
