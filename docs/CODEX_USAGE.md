# Codex Usage - ShelfVault

## First prompt to Codex

Use this before any code change:

```text
You are working on ShelfVault, a free self-hosted Laravel application for cataloging physical films, video games and board games.

Read first:
- AGENTS.md
- docs/PRODUCT_SPEC.md
- docs/ARCHITECTURE.md
- docs/BACKLOG.md

Do not modify files yet.

Reply with:
1. project summary
2. main technical risks
3. recommended ticket order
4. execution plan for TICKET-001
5. blocking questions only if necessary
```

## Work prompt template

```text
Work on TICKET-XXX.

Read:
- AGENTS.md
- tickets/TICKET-XXX-name.md

First propose a plan and list the files you expect to modify.
Do not make changes until the plan is clear.
After implementation, run or document the relevant checks.
```

## Review prompt template

```text
Review your changes for TICKET-XXX.
Check:
- security
- installation impact
- mobile impact
- tests/build
- missing documentation
- secrets accidentally committed
Return a concise review and remaining risks.
```
