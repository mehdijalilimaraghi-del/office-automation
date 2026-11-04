## 2026-11-04 — Correspondence test alignment with direct-registration policy

- Local correspondence test run exposed four failures caused by stale test assumptions, not four distinct runtime regressions: two HTTP tests still expected internal letters to remain drafts although the default internal policy is direct registration; two numbering-service tests attempted to register an internal letter as a separate Secretariat registrar although direct internal registration is creator-only.
- Updated the two draft-specific HTTP tests to explicitly set internal approval to `approval`, preserving the purpose of those tests (draft persistence and attachment handling).
- Updated the two numbering tests to register internal letters as their creator, matching the documented direct-registration authorization policy.
- The previous run had 71 passing tests and 4 failing assertions/exceptions. Rerun the focused correspondence suites locally; validation remains pending. Development only; master and server untouched.

## 2026-11-03 — Dropdown and correspondence registration corrections

- Both shared selectors now measure their rendered popup height before choosing above/below placement, preventing incorrect offsets near the bottom of the viewport.
- Drafts requiring approval are assigned to the active manager of the creating department and enter awaiting_manager_approval, so they appear in that manager's needs-action list.
- After approval, outgoing drafts move to the configured Secretariat manager for final registration. The needs-action filter includes this approved-for-registration state.
- With the direct setting, normal correspondence is finalized in the create request, including numbering and the normal post-registration workflow. The create form displays a final-registration action instead of a draft-save action. Special reply drafts retain their dedicated workflow.
- Registration authorization is aligned with the configured policy and workflow state. Regression tests cover manager assignment/visibility, direct internal finalization, and outgoing approval handoff. Local validation is pending. Development only; master and server are unchanged; no migrations or dependencies were added.

## 2026-11-03 — AppSelect change-event typing correction

- Updated `CustomFieldsForm.vue` so its select change handler accepts the documented AppSelect payload shape (`{ target: { value: string } }`) instead of a native DOM `Event`. This resolves the TypeScript incompatibility introduced when the native select was replaced.
- Runtime behavior remains unchanged: the selected string is stored, and an empty value is normalized to `null`.
- Validation pending: rerun `npm run build`. Development only; master and server remain untouched.

## 2026-11-03 — Shared application select rollout

- Added `resources/js/Components/AppSelect.vue` as the common application-rendered select control. It renders a consistent RTL option panel with a rounded surface, subtle border/shadow, selected-state indicator, viewport-aware positioning, outside-click and Escape dismissal, keyboard navigation, and automatic search for longer option lists.
- Replaced native `<select>` controls in the identified form/filter surfaces across Correspondence, Tasks, Projects, Core users/departments/settings/roles/custom fields/external parties, the reusable custom-fields form, and the Jalali date-time picker. The role permission-scope selector's controlled `:value` pattern and selects without `v-model` remain supported.
- Preserved `v-model`, controlled `:value`, `@change` target-value handling, disabled states, option conditions/loops, and required-field markers. Department selection continues to use `DepartmentTreeSelect.vue`; this component is for ordinary single-choice lists.
- Validation pending: run `npm run build`, then manually verify short and long lists, search, selection updates, disabled options, required markers, change handlers, keyboard navigation, outside click, Escape, and the Jalali hour/minute controls. `development` only; `master` remains at its prior revision. No backend, database, migration, or dependency changes.

## 2026-11-03 — Shared dropdown and popover UX correction

- Updated the shared `Dropdown.vue` used by the authenticated user menu: consistent rounded surface, border and shadow; document-level outside-pointer dismissal; Escape dismissal; and no longer closes merely because an inner content area receives a click.
- Updated `DepartmentTreeSelect.vue`: outside-pointer dismissal works with its teleported panel, Escape closes and restores focus to the trigger, and the popup uses a minimum usable width while remaining within the viewport. Multi-select remains open while options are selected and closes on outside click or explicit confirmation.
- Updated `AuthenticatedLayout.vue`: desktop navigation menus and notification panels close when the pointer goes outside their own popover; opening a navigation menu closes notifications and vice versa.
- Scope is shared controls/layout behavior, so all screens using these shared components inherit the correction without per-form overrides. Native browser select controls remain browser-rendered; this change targets application-rendered dropdowns and unit selectors.
- Validation pending: run `npm run build`, then manually verify the user profile menu, notifications, desktop menus, and single/multiple department selectors in forms. `development` only; `master` unchanged. No backend, database, migration, or dependency changes.

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

