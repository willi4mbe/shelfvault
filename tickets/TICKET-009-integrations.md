# TICKET-009 - Optional metadata integrations

## Goal

Create provider architecture for optional enrichment.

## Scope

- Provider settings screen
- Server-side service classes
- IGDB/Twitch provider skeleton
- TMDb provider skeleton
- Generic barcode/movie provider interface
- Lookup cache table usage

## Requirements

- No provider is mandatory
- Secrets are stored server-side
- Failures do not break core cataloging

## Acceptance criteria

- Providers can be configured later
- Manual cataloging still works if all providers are disabled
- Provider errors are shown clearly
