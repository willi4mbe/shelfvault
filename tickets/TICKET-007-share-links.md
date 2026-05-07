# TICKET-007 - Guest share links

## Goal

Allow the admin to share the collection in read-only mode.

## Scope

- Generate share token
- Enable/disable link
- Optional password
- Read-only public route
- Privacy options:
  - show/hide precise location
  - show/hide loan status

## Acceptance criteria

- Guests cannot modify data
- Disabled links stop working
- Password-protected links require password
- Tokens are random and not guessable
