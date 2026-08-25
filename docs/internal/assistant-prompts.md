# Assistant Prompt Archive

These prompts were used during early implementation. They are internal references and should not drive the public roadmap.

## Prompt 0 - Context only

```text
You are working on ShelfVault.
Read AGENTS.md, docs/PRODUCT_SPEC.md, docs/ARCHITECTURE.md and docs/BACKLOG.md.
Do not change files.
Summarize the project and propose the safest order to start implementation.
```

## Prompt 1 - Scaffold

```text
Implement TICKET-001.
Create the Laravel application foundation for ShelfVault.
Respect AGENTS.md.
Keep the core minimal.
No business features yet.
```

## Prompt 2 - Installer

```text
Implement TICKET-002.
Build a web-based setup wizard skeleton at /install.
It should check environment requirements, collect database config, create admin account, run migrations and lock installation.
If full implementation is too large, implement it in safe incremental steps.
```

## Prompt 3 - Admin auth

```text
Implement TICKET-003.
Add one-admin authentication for ShelfVault.
Protect /admin routes.
Keep guest routes separate.
Do not add multi-user roles.
```

## Prompt 4 - Data model

```text
Implement TICKET-004.
Create migrations, models and relationships for items, item details, locations, loans, barcodes, external references, settings and share links.
```

## Prompt 5 - CRUD

```text
Implement TICKET-005.
Create mobile-first admin CRUD screens for the collection using Laravel, Livewire, Alpine.js and Tailwind.
```
