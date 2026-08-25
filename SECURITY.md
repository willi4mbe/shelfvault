# Security Policy

ShelfVault is beta software. Please do not use it yet as the only copy of important collection data without your own backups.

## Reporting a Vulnerability

If you find a security issue, do not open a public issue with exploit details.

Send a private report to the maintainer through GitHub, or contact the maintainer directly if a private channel is available on the profile.

Please include:

- affected version or commit;
- installation type, for example Docker, local development, o2switch, or cPanel;
- steps to reproduce;
- impact;
- any relevant logs with secrets removed.

## Current Beta Notes

- Keep `.env`, database dumps, generated backups, uploaded files, and API tokens private.
- Prefer using `public/` as the web root.
- If a shared host requires installing under `public_html`, keep the root `.htaccess` in place.
- The in-app update workflow creates private backups before replacing files, but database restore is still manual.
