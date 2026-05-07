# TICKET-003 - Admin authentication

## Goal

Implement one-admin authentication for ShelfVault.

## Scope

- Login page
- Logout
- Password hashing
- Admin middleware
- Protected `/admin` routes
- Password change screen

## Out of scope

- No multi-user roles
- No registration screen after installation
- No social login

## Acceptance criteria

- Unauthenticated users cannot access admin
- Admin can log in and out
- Password is hashed
- Guest shared pages remain separate
