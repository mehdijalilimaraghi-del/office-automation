## 2026-11-07 — Fix Vue template preview build syntax

- Fixed the missing closing quote in the preview-mode grid class binding in Correspondence/Template.vue. The malformed attribute caused Vue's template compiler to report an unterminated JavaScript expression at line 93.
- No runtime behavior or data contract changed; this is a template syntax correction. Owner-run production build is pending.

## 2026-11-07 — Organization-backed template designer preview

- Corrected the designer's preview mode so the A4 page expands to a single-column layout rather than leaving the editor's sidebar column reserved.
- Preview resolves organization identity/contact tokens from saved Organization Settings, displays the active organization stamp when available, and uses clearly recognizable sample correspondence values/body text for fields that require a concrete letter (recipient, subject, numbering, creator and body).
- Sample preview content is presentation-only and is not written into the saved template. Missing organization settings use readable sample fallbacks. Letterhead remains sourced from the saved organization letterhead.
- Added feature coverage for the preview props and their organization-settings/sample-data sources. Owner-run PHP test and frontend build are pending; development only, master/server unchanged.

## 2026-11-04 — Template preview uses semantic document flow

- The A4 editor preview now lays out metadata, addressee/subject, the automatic body placeholder, issuer/signature and page-number indicator in document flow. Non-repeating roles no longer use fixed Y coordinates in the preview.
- Repeating elements remain page-positioned. The preview shows sequence and approximate occupied space over the actual letterhead background; the final body length and issuer position are determined by the letter's real HTML and print pagination.
- The current `development` feature test fixture no longer expects the removed `height` properties. The reported local output still contains them in its expected array, so that run is from a stale local revision. Pull before rerunning.
- Validation remains pending: focused tests, `vue-tsc`, production build and multi-page print acceptance. Development only; master/server untouched.

## 2026-11-04 — Fix semantic template persistence test

- Updated the visual-template feature fixture to match the new role-based schema: flow elements use width and spacing rather than a fixed height. The previous failure was an outdated test expectation; request validation correctly excludes the removed `height` property from persisted data.
- No production behavior change. Local focused test rerun remains pending owner confirmation. Development only; master/server unchanged.

## 2026-11-04 — Role-based Persian letter template and automatic body flow

- Reworked the A4 template editor around semantic roles: letter number/date, addressee/subject, page number, issuer/signature, and repeating text. Variable choices are filtered by the selected role.
- The organization letterhead is no longer a configurable element; it is loaded automatically as a page background in the editor and print preview.
- The letter body remains automatic, is not a template element, and is rendered from the stored HTML in normal multi-page document flow. RTL direction/alignment and whitespace/rich HTML formatting are preserved in the print body.
- Metadata and addressee/subject render once before the body; issuer/signature elements follow the body; repeating elements use fixed page coordinates; page numbering is controlled by the page-number role. Flow elements expose width and spacing, not arbitrary page Y coordinates.
- Existing stored template elements are normalized for compatibility; legacy letterhead image elements are removed from the element list because the image is now automatic. No migration or PDF dependency was added.
- Validation and physical multi-page print acceptance remain pending owner execution. Development only; master/server unchanged.

## 2026-11-04 — Template designer scope update

This entry supersedes earlier first-slice notes that described stamp/signature elements as future work: the visual A4 editor now supports text, organization-stamp and manager-signature element kinds, and print preview binds the latter two to correspondence-specific snapshot assets. Creator and recipient/letter metadata tokens are also available. Configurable body/recipient/footer flow zones, repeating headers/footers, snapping/guides/zoom, and local validation remain open.

## 2026-11-04 — Template designer image elements follow-up

- Added selectable template element kinds: text, organization stamp, and issuing-unit manager signature. Stamp/signature elements use the existing protected, snapshotted image assets; their position and dimensions are configured in the A4 editor. Text elements support creator identity tokens as well.
- Image-only elements may have empty text content; the server normalizes empty values safely. Print preview suppresses legacy footer duplicates when a corresponding stamp/signature is placed through the template.
- Remaining limitation: body and recipient remain normal document-flow regions; repeating page headers/footers and pagination-aware drag zones are still future work. Validation remains pending.

## 2026-11-04 — Visual letter template designer (first implementation slice)

- Added a dedicated settings-managed page at `correspondence.template.edit` / `correspondence.template.update`, separate from Organization Settings. Access requires `settings.manage`.
- The A4 editor supports adding/removing text boxes, selecting boxes, pointer drag to move, resize handle, content editing, visibility, font size/weight, alignment, and insertion of approved variable tokens. Template JSON is stored in `correspondence.print.template` through SystemSettingsService; input is bounded and validated server-side, including page bounds and element count.
- Print preview loads the saved template and renders visible text boxes on the first A4 page. Supported text tokens include organization name/short name/address/telephone/email/website, letter number/registration number/date and recipient name. Unknown tokens remain literal. Existing print output remains the fallback when no template has been saved.
- Removed legacy print-visibility fields from Organization Settings UI, request validation and update persistence. The stamp upload/activation remains in Organization Settings as an organization asset; its location/size is to be controlled by the template designer in a later slice.
- Current editor slice deliberately keeps the letter body in normal multi-page flow; stamp/signature image elements, recipient/body/footer zones, snapping/guides, zoom, and richer pagination-aware layout are not yet implemented. Do not describe the template designer as complete.
- Regression tests added for template persistence and settings permission. Migration/tests/type-check/build/manual print verification remain pending owner execution. Development only; master/server untouched.

## 2026-11-04 — External unit owns manager and addressee data (implementation follow-up)

- The four addressee components are owned by `ExternalDepartment` only: organizational title prefix, organizational position, personal salutation, and professional title. This applies only to external organizational units; internal Core Department manager selection and behavior are unchanged.
- External contact create/edit UI and request validation now collect only the person's identity/contact details. Legacy contact-level addressing/default columns are retained for historical compatibility and migration fallback, but are no longer exposed as current input or list metadata.
- An external unit selects one active manager from contacts belonging to that same unit. Service-level guards prevent deactivating or moving an assigned manager until the unit manager is changed; unit update validates manager ownership.
- Outgoing finalization snapshots the unit's addressing fields and selected manager's name/ID, without requiring an individual contact selection. Legacy contact values remain a fallback for older data.
- Regression coverage added for manager assignment integrity and outgoing recipient snapshot. Local migration, focused tests, TypeScript/build and manual acceptance are still pending; do not claim validation until owner reports results. Development only; master and server untouched.

## 2026-11-04 — Fix correspondence controller parse error — validated

- Owner confirmed PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` all pass after pulling the fix. This validates the syntax correction and focused regression suites only; full suite, frontend build and end-to-end acceptance remain separate gates.
- Root cause of the widespread HTTP 500/test failures: the `createDraft()` attribute array in `CorrespondenceController::store()` had a malformed key (`'reply_to_id => ...`) introduced while wiring multi-letter relations. PHP could not parse the controller, so unrelated routes/tests also failed when Laravel attempted to load routes/controllers.
- Corrected the key to `'reply_to_id' => ...`; retained the existing first-related-letter fallback and source-project correspondence fallback. No workflow, validation, authorization, or database behavior was changed.
- Existing `CorrespondenceHttpTest` and `ExternalPartyControllerTest` cover the affected route-loading and create/update behavior. Validation completed by the project owner: PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` passed.
- Development only. `master`, release branch, migrations, and server are untouched.

## 2026-11-04 — Direct correspondence registration follow-up

- Fixed the create-page contract: `Correspondence/Create` now receives the per-type `approvalSettings` used by its submit label and explanatory text. Previously this prop was omitted on the create action (although it was supplied by the edit action), so a type configured for direct registration could still display «ذخیره پیش‌نویس».
- Made the server's immediate-registration decision explicitly use the same `CorrespondenceCompositionSettings::requiresApproval(type)` policy instead of inferring the decision from the created record's workflow state. When approval is disabled for the selected type, the create request invokes numbering/final registration immediately; approval-enabled types remain drafts assigned for approval. Reply/request-generated correspondence remains on its dedicated workflow.
- Added an HTTP regression test asserting the create page receives the direct setting and a direct internal letter is persisted as registered in the initial create request, not as a draft.
- Validation pending on the project owner's machine. Development only; master and server untouched.

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

### Correspondence approval test fixtures

When a test enables `correspondence.approval.internal=approval` and creates an internal correspondence through the HTTP endpoint, its creator must belong to a primary active department whose `manager_id` references an active user. The approval workflow intentionally rejects the request if no active department manager can be resolved. Draft-persistence tests must build this fixture rather than bypassing the manager requirement.

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




## 2026-11-04 — Phase 3 output architecture decision gate

The active product version is V2 — Office Automation, which is authorized and underway according to `ROADMAP.md`. Any older wording in this guide describing V1 as the current checkpoint / V2 as not yet started is historical and must not override the roadmap or `CURRENT_STATUS.md`.

Before implementing the phase-3 shared letter output, inspect and reuse existing Core organization settings for organization details, existing Department manager ownership, and the Documents storage abstraction. The repository currently has no PDF-rendering dependency and no correspondence print/PDF route or print-specific page. The owner must choose whether phase 3 uses browser-native Print / Save as PDF over one shared HTML/CSS A4 template, or a server-side PDF renderer for downloadable generated PDFs. Do not silently introduce a PDF package or external binary. Keep stamp and per-department manager signature as distinct assets, use controlled access, and preserve finalization-time snapshots for historical letters.


## 2026-11-04 — Phase 3 implementation slice 1

The owner selected browser-native Print / Save as PDF from a shared HTML/CSS A4 template. Do not add a server-side PDF dependency without a new explicit requirement. Store the organization stamp in Core organization settings and the manager signature on each Department (not on User). A manager change must deactivate the old unit signature until replaced. Both are private, separately managed assets. Snapshot creator/unit details and the exact stamp/signature files at outgoing-letter finalization; only include the manager signature when workflow approval authorizes it. The new registered-letter print preview provides a per-operation letterhead toggle and independent visibility settings. Local PHP tests, migration, TypeScript/build and physical print pagination remain pending; do not report them as passed until owner confirmation.


## 2026-11-04 — Letterhead and settings navigation

Organization Settings now has three tabs. A separate organization letterhead image can be uploaded and previewed; it is snapshotted per registered letter and placed through a dedicated image element in the A4 template editor. The template persistence regression compares decoded JSON after normalizing key order. Upload/preview and snapshot coverage has been added. Local validation remains pending.
