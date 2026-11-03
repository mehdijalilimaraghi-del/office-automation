## 2026-11-03 — Fix needs-action index closure scope

- Root cause of the reported HTTP 500: the nested `needs_action` query closure referenced `$isSecretariatManager` and `$secretariatDepartmentId` without capturing them in its enclosing closures. PHP raised `Undefined variable $isSecretariatManager` at `CorrespondenceController.php` during correspondence index requests.
- Captured both values in the `when(needs_action)` callback and its `$pending` callback. No workflow states, visibility rules, secretariat authorization, assignment rules, or query semantics were changed.
- Existing `CorrespondenceHttpTest` cases exercise the affected index/filter path, including manager and department routing scenarios. Validation is pending on the project owner's local environment; run the focused command provided in the handoff. `development` only; `master` unchanged. No migration or dependency changes.
# Office Automation System — Development Guide

## 1. Version-Oriented Development

The product roadmap is versioned in ROADMAP.md. That file is the authoritative source for product scope and version boundaries.

Current state:
- V1 — Foundation & Work Management: implementation scope complete.
- Current checkpoint: V1 technical/debugging and manual UI/functional acceptance.
- Next version: V2 — Office Automation.
- V2 must not start until V1 acceptance is closed.

Version order:
1. V1 — Foundation & Work Management
2. V2 — Office Automation
3. V3 — Collaboration & Planning
4. V4 — Customer & Commercial Operations
5. V5 — Procurement, Inventory & Assets
6. V6 — HR & Internal Collaboration
7. V7 — Intelligence, Automation & Integrations
8. V8 — Production & Enterprise Readiness

Normal development loop:
**Inspect → Root Cause / Boundary Review → Smallest Coherent Change → Test → Build → Manual Acceptance → Documentation/Status Update → Next Item**

### Engineering Rules
- Inspect the GitHub repository and persistent project documents first.
- Make reasonable architectural decisions directly when user approval is not required.
- Do not wait for confirmation for ordinary implementation/debugging work.
- Keep Modular Monolith boundaries intact.
- Use standard Laravel/PHP/Vue/TypeScript patterns and production-quality code.
- Add or strengthen tests for meaningful behavior and regressions.
- Fix root causes rather than masking symptoms.
- Avoid unnecessary refactors, packages and architectural changes.
- UI/UX should follow modern Google Material Design principles while respecting Persian RTL requirements.
- Record meaningful implementation decisions and completed work in persistent documentation.

### V1 Completion Gate
- Relevant backend/regression tests pass.
- Frontend production build passes.
- RTL/Persian/Jalali UI is reviewed where applicable.
- Critical user journeys are manually accepted.
- Known runtime defects are fixed at root cause.
- Documentation matches repository reality.
- No V2 implementation starts before this gate closes.

## 2. Git Workflow

Git is part of the project from the beginning.

Commits should be:
- Small
- Focused
- Meaningful
- Related to one logical change

Active development branch: development

Stable branch: master

Do not develop directly on master.

## 3. Change Workflow

Before every meaningful change:
1. Inspect the relevant existing implementation.
2. Identify the root cause or requirement.
3. Make the smallest coherent change.
4. Add/update relevant tests.
5. Inspect the result.
6. Update documentation/status when needed.

For local validation, the user runs the project on their own computer. After code changes, provide the exact PowerShell validation command.

Only include `git pull origin development` at the beginning of a validation command when code changes were made immediately before that validation. Do not include it when no code change was made.

Prefer one validation step at a time.

## 4. Troubleshooting

- Fix the root cause.
- Do not change unrelated variables at the same time.
- Do not weaken tests, remove security controls, or use destructive database changes to hide an error.
- Inspect environment differences before changing application architecture.

## 5. Dependencies

A new Composer/NPM package requires a concrete architectural reason and compatibility review. Avoid unnecessary dependencies.

## 6. Database and Security Rules

- SQL Server is the target database.
- Schema changes use migrations.
- Existing shared migrations are not rewritten to hide development problems.
- Backend authorization is mandatory.
- File uploads require validation, safe generated names, controlled storage and authorization.

## 7. Frontend Rules

- Vue 3 + TypeScript + Inertia.js is the frontend architecture.
- Keep components focused.
- Use reusable components only where reuse is meaningful.
- Keep business authorization on the backend.
- Respect Persian RTL and Jalali presentation requirements.

## 8. Documentation Authority

- ROADMAP.md — product versions, scope and acceptance gates
- CURRENT_STATUS.md — concise current checkpoint and immediate blockers
- PROJECT_CONTEXT.md — persistent detailed context
- ARCHITECTURE.md — architecture and module boundaries
- PROJECT_SPEC.md — product/system specification
- DEVELOPMENT.md — engineering workflow

Meaningful requirements, architecture decisions, implementation status and blockers must be recorded in the appropriate project document.

## 9. Definition of Done

A change is complete only when intended behavior is implemented, relevant tests are added/passing, authorization and validation are correct, no known regression is introduced, and documentation is reconciled when the change affects project scope/status.

Current environment target: Windows + PHP 8.5.x + Composer 2.10.x + Node.js 24.x + NPM 11.x + Laravel 12.x + Microsoft SQL Server.

