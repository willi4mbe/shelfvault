# TICKET-002 - WordPress-like install wizard

## Goal

Build the first version of the `/install` flow.

## Scope

- Redirect to `/install` if app not installed
- Environment check page
- Database configuration form
- Database connection test
- Admin account creation form
- Application settings form
- Run migrations
- Create installation lock
- Block `/install` after successful installation

## Requirements

- Installation must not require external APIs
- Errors must be understandable by non-developers
- Installer must not expose secrets after setup

## Acceptance criteria

- Fresh install can be completed from browser
- Admin account exists after install
- `/install` is locked after setup
- Invalid DB credentials show a useful error
