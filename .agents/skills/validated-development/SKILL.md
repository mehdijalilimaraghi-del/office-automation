---
name: validated-development
description: Safely implement and validate office-automation changes, then commit and push only to development after all required checks pass.
---

# Validated development and push

Use this skill for implementation, debugging, refactoring, and maintenance tasks in this repository.

## Mandatory preflight

1. Read `AGENTS.md` and the relevant context in all six project documents.
2. Run `git status --short` and `git branch --show-current`.
3. Continue only when the current branch is exactly `development`.
4. Never switch to, modify, merge into, or push to `master`, `release`, or `server`.
5. Inspect the target code, related tests, migrations, and current implementation before editing. Preserve unrelated working-tree changes.

## Find the affected files

- For V3 archive, documents, scanner, Cartable or workflow work, start with [the V3 code map](references/v3-code-map.md). Verify entry points against the current checkout.
- Start from a route name, error text, permission or symbol. Use `rg -l` or `rg --files` within the affected module, then read the relevant line ranges and follow callers, authorization and tests. Expand to other modules when the route or dependency crosses a module boundary.
- Use the current status and relevant headings to locate project context. Read the full applicable sections for architectural, security or release decisions. Follow the project owner’s onboarding document order when a full review is requested.
- Keep test and build output to the result summary and actionable warnings. On failure, read the relevant error and surrounding output. Review the complete staged diff before committing.

## Implementation

- State the root cause and smallest intended change before broad edits.
- Keep changes scoped; do not silently change architecture, dependencies, database history, authorization, or public behavior.
- Never run destructive database commands or access production data.
- Do not add secrets, local environment files, machine-specific settings, generated artifacts, or credentials to Git.
- Update all six project context documents when the implementation or project status changes.

## Validation gate

Run checks appropriate to the change, normally:

1. Focused PHPUnit tests for the affected behavior.
2. Full `php artisan test` suite when the configured SQL Server test database is available.
3. `npm run build` (includes `vue-tsc`).
4. Any additional static, migration, route, or browser checks required by the change.

A check is successful only when its command actually ran and exited successfully. If SQL Server, browser, printer, or another required dependency is unavailable, record the check as blocked; do not substitute SQLite or claim equivalent coverage.

For letter-template and print changes, distinguish automated tests/build from real browser preview and physical printer acceptance.

## Review and automatic push authorization

The user has authorized pushing completed changes automatically after tests pass.

Before commit/push:

- Re-check `git status --short` and `git branch --show-current`.
- Review the entire staged and unstaged diff.
- Confirm all relevant tests and the frontend build passed.
- Confirm all six project documents are updated when required.
- Confirm no secrets, `.env` files, unrelated files, or generated output are included.
- Use a focused commit message.
- Push only with `git push origin development`.

If any required check fails, is skipped without a valid reason, or is blocked, stop before pushing and report the exact blocker. Never force-push, reset, clean, bypass branch protection, or push to another branch. GitHub Actions runs after the push and is a second verification layer; it does not replace the local pre-push gate.
