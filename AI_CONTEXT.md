## 2026-10-27 — Correspondence create-form refinement (validation pending)

- Incoming «تاریخ دریافت» and outgoing «تاریخ ارسال» are required in server validation (`required_if`) and in the date-picker (`required`); explicit red asterisks were added because the custom picker labels did not visibly inherit the CSS required marker.
- The related-letter search input now stays above results and selected letters. Results and selected items share a fixed-height scroll area to avoid increasing the whole form row as items/search results grow.
- «رونوشت به» no longer displays «اختیاری»; the explanatory paragraph below it was removed and its meaning moved to a question-mark tooltip. Related-letter guidance is also a question-mark tooltip, with duplicate help removed.
- Changes are on `development`; no local build or tests have been run by the assistant. Validate with `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`.

## 2026-10-27 — Correspondence create-form UX and multi-link support (validation pending)

- The correspondence create form now gives Subject the full row; related correspondence and copy recipients sit side by side beneath it.
- Removed the redundant related-letter search helper sentence and renamed the field to «ارتباط با مکاتبات قبلی». The picker supports selecting multiple registered, visible letters and removing any selection.
- Added the `correspondence_related_letters` pivot table with backfill from legacy `reply_to_id`; the legacy field continues to point to the first related letter for compatibility. Server-side visibility checks protect confidential related letters.
- Replaced the copy-recipient checkbox grid with a single-select combo box that displays departments in hierarchy order and a removable selected-unit list. Label is now «رونوشت به».
- Migration and frontend/backend changes are on `development`; validation is pending. Run the migration, targeted correspondence tests, full `php artisan test`, and `npm run build`. Do not claim validation until the owner reports results. `master` unchanged.

## 2026-10-27 — Correspondence list scopes (validation pending)

- Added a server-side scope filter to the correspondence index: `mine` (default), `department`, and `all`.
- `mine` includes correspondence created/registered by the user, assigned to them, explicitly authorized for them, or on which they acted. `department` limits results to the user's member/managed departments, including created/recipient/current departments, copies, and department workflow actions.
- All scopes are layered on top of `CorrespondenceAccessService::visibleToQuery`; the `all` option means all correspondence the user is authorized to view, not an access-control bypass. Confidential-letter checks remain enforced server-side.
- Added feature coverage for all three scopes and adjusted the ordinary-letter visibility test to request `all` explicitly. Pull `development`, run the targeted Correspondence HTTP tests, then the full test suite and frontend build. Validation is pending; no tests/build were run by the assistant. `master` unchanged.

## 2026-10-27 — Correspondence dispatch, notifications, and Cartable integration (validation pending)

- Added privacy-safe database notifications for correspondence actions. Notification payloads contain a generic message, correspondence ID, and authorized detail URL; they intentionally omit letter subject/body and workflow notes.
- Final outgoing dispatch is now a distinct `dispatched` workflow state and `dispatched` business action/Core Audit event. Only the configured Secretariat (or system administrator) may set `sent_at`; the first transition to sent records dispatch once and notifies the creator. External tracking changes continue to audit changed field names only.
- The shared Cartable now includes actionable correspondence assigned to the current user or their current department, and the Vue page displays these items. The query applies `CorrespondenceAccessService::visibleToQuery`; copy-only recipients are excluded from actionable Cartable items.
- Added regression coverage for dispatch authorization/audit/notification privacy and for Cartable exclusion of copy-only access. **Validation pending:** pull `development`, run `php artisan test`, then `npm run build`. Do not start Forms & Requests until these pass and the V2 journeys receive manual acceptance. `master` unchanged.

## 2026-10-27 — Full validation confirmed for correspondence audit slice

- Project owner confirmed the full `php artisan test` suite passes and the frontend production build succeeds after the syntax correction in the external-tracking audit regression test.
- Treat the latest registration, copy-recipient, and outgoing external-tracking audit changes as user-validated. No tests or build were independently executed by the assistant.
- Next grouped review before Forms & Requests: inspect end-to-end incoming/outgoing/internal journeys, especially final dispatch audit, correspondence-specific notifications, and whether correspondence actions are correctly surfaced in the shared Cartable. Check the V2 acceptance gate in `ROADMAP.md`; do not assume these areas are complete from the audit tests alone.
- Branch: `development`; `master` unchanged.

## 2026-10-26 — Correspondence registration and tracking audit

- Owner confirmed `php artisan test --filter=CorrespondenceHttpTest` passes for registration audit: final registration creates both a `registered` action and `correspondence.action.registered` Core Audit event.
- Outgoing external tracking now runs under a DB transaction and row lock. Changed values produce `correspondence.external_tracking.updated` with changed field names only; tracking note contents and other values are deliberately excluded from audit payloads. No-op updates do not create audit noise.
- Added a privacy regression test covering stored tracking fields and ensuring the private note is absent from the audit record. This latest grouped change is pending user validation. Recommended validation: full `php artisan test` and `npm run build` once after pulling `development`.
- Branch remains `development`; do not modify `master`.

## 2026-10-26 — Correspondence copy audit

- `CorrespondenceWorkflowService::addCopies()` now routes newly attached copy recipients through the common action recorder, writing the business action and Core Audit event together in a transaction.
- Duplicate attachment attempts do not emit duplicate action/audit records.
- New test: `tests/Feature/Modules/Correspondence/CorrespondenceCopyAuditTest.php`.
- Next validation step: pull `development` and run `CorrespondenceCopyAuditTest` plus `CorrespondenceHttpTest`. Do not start a second test step before the owner reports the result.
- Next implementation review after successful validation: assess audit/notification/Cartable coverage for final registration and outgoing dispatch, then continue the V2 acceptance gate. `master` remains unchanged.

## 2026-10-21 — Correspondence authority correction

- V2 rule: registered ordinary letters are visible organization-wide; confidential letters are visible to their creator/registrar/explicit participants and the configured secretariat.
- All active users may create internal letters and outgoing drafts. Incoming-letter creation belongs to the department configured as `organization.department_roles.secretariat`.
- Final registration/numbering of incoming and outgoing letters belongs to that configured secretariat. A creator may register their own internal letter.
- Resolve secretariat through `OrganizationDepartmentRoleService`; do not replace it with a generic `correspondence.register` role check.
- Backend authorization is enforced in HTTP handlers and `CorrespondenceNumberingService`; frontend type/action visibility is UX only.
- Changes are on `development`; focused HTTP and numbering tests are pending. Keep `master` unchanged.


## 2026-10-21 — Baseline access for correspondence menu
- Active staff now receive organization-wide baseline `correspondence.view` and `correspondence.create` through `User::responsibilityDerivedScopes()`, so the correspondence menu and draft form are available without a bespoke role grant.
- Final registration/numbering is enforced against the configured secretariat; the creator may register their own internal draft. Confidential visibility is enforced by `CorrespondenceAccessService` for related users and the configured secretariat.
- Updated `CorrespondenceHttpTest` to cover index and draft-form access for an active user without explicit correspondence role permissions.
- Local validation pending: pull `development` and run `php artisan test --filter=CorrespondenceHttpTest`. Keep `master` unchanged.



## 2026-10-21 — Correspondence visibility test fixture fix
- The three new visibility tests initially failed because different permission codes used the same unique `permissions.name` value. The test helper now suffixes the permission code to the name; lookup remains keyed by permission code.
- Pull `development` and rerun `php artisan test --filter=CorrespondenceHttpTest`; frontend build remains a later separate validation step. No production policy behavior changed. Keep `master` unchanged.
## 2026-10-21 — Full test suite follow-up

- The user ran the full Laravel suite: 401 tests passed; two tests failed.
- `CorePermissionSeederTest` expected 53 permissions, but the current seeder correctly creates 56 after the three V2 correspondence permissions were added. Updated the expected count to 56.
- `ExampleTest` expected HTTP 200 for `/`, but the intended guest route redirects to the login page. Updated the test to assert the named login redirect; application routing was not changed.
- These are test expectation corrections only. Changes are on `development`; keep `master` unchanged.
- Local verification pending: run the full test suite after pulling the branch.

## Latest handoff — 2026-10-21 V2 correspondence HTTP foundation

- Added CorrespondenceController with index/create/store/register actions. Registration delegates to the existing CorrespondenceNumberingService, preserving the draft-only rule and configured approval requirement.
- Added resources/js/Pages/Correspondence/Index.vue and Create.vue for Persian RTL list/filter/pagination and draft creation for incoming, outgoing and internal correspondence.
- Added correspondence.view, correspondence.create, and correspondence.register permissions, Inertia ability exposure, and a navigation link.
- Added tests/Feature/Modules/Correspondence/CorrespondenceHttpTest.php for authorized listing, draft creation, and denied index access.
- Local validation is pending. The first focused test run exposed an incorrect `PermissionScope` import in the new test fixture; fixed to `App\\Core\\Permissions\\Enums\\PermissionScope` in commit `d7afe98ffcb270bf2bcb9c0a03a4c309feaa47a3`. Rerun the focused correspondence test and frontend production build before continuing. Composition modes (internal editor, file upload and combined) are implemented; next: correspondence detail/edit, referral/instruction, workflow/Cartable integration, notifications/audit.
- Branch remains development; keep master unchanged. Development checkout: D:\\KPMM\\Office-automation.

## Latest handoff — 2026-10-21 frontend notification build fix

- Fixed the TypeScript error in `resources/js/Layouts/AuthenticatedLayout.vue`: the mobile notification dropdown referenced undefined `markNotificationRead`; it now calls the existing `openNotification(item)` handler, matching desktop behavior.
- The existing handler closes the dropdown, marks unread notifications through `notifications.read`, then navigates to the notification URL (or notification index fallback).
- User reports `php artisan test --filter=NotificationTest` passed. Frontend build verification is pending.
- User's development checkout path is `D:\\KPMM\\Office-automation`; do not use the release checkout path for development validation. Keep `master` unchanged.

## Latest handoff — 2026-10-21 notification test correction

- The comment notification contract is current department manager, falling back to project manager only when no department manager is assigned, plus the current assignee; the comment author is excluded.
- `NotificationTest` had a stale fixture expecting a non-manager task creator to be notified. The regression fixture is aligned by making the creator the department manager; production behavior is unchanged.
- After pulling `development`, validate with `php artisan test --filter=NotificationTest`. Do not merge to `master` until the user validates.

# AI Context — Office Automation

> Read this file before making code changes. It is a compact handoff for any AI/engineer continuing the project.

## Project
- Repository: `mehdijalilimaraghi-del/office-automation`
- Active development branch: `development`
- Stable branch: `master`
- Never develop on `master`.
- The application is a Persian, RTL, Modular Monolith office-automation system for a single internal company.
- Initial deployment target: company LAN. Future target may include VPS/cloud.
- Target organization size: approximately 10–300 users.
- No accounting module.

## Technology
- Laravel 12.x
- PHP 8.5.x
- Vue 3 + TypeScript
- Inertia.js
- Tailwind CSS 4
- Vite
- shadcn-vue
- Microsoft SQL Server
- Persian RTL
- Jalali presentation where dates are user-facing
- Vazirmatn

## Authoritative project documents
Read these before implementation when the task touches their scope:
1. `ROADMAP.md` — product versions, scope and acceptance gates
2. `CURRENT_STATUS.md` — current checkpoint, blockers and immediate next work
3. `PROJECT_CONTEXT.md` — detailed persistent context and domain decisions
4. `ARCHITECTURE.md` — architecture and module boundaries
5. `PROJECT_SPEC.md` — product/system specification
6. `DEVELOPMENT.md` — engineering workflow

## Current version
**V1 — Foundation & Work Management: closed by project owner.** Future fixes may still be made if needed, with regression coverage.

**V2 — Office Automation: started and authorized.** Current focus is correspondence composition (internal editor, file upload or both), followed by correspondence detail/edit, referrals/instructions, workflow/Cartable integration, and request forms.

## V1 capabilities already implemented
- Authentication and username-based login
- Invitation-based onboarding
- Users, profiles, activation and avatar handling
- Roles and permissions
- Standalone `SYSTEM_ADMIN` bootstrap account
- Internal departments and unlimited hierarchy
- Department membership and managers
- Organizational positions and assignments
- Projects and project roles/members
- Tasks, assignment, referrals, comments, work logs, reminders, timeline
- Task review/approval and resubmission flow
- Task dependencies
- Documents/attachments foundation
- Workflow V1
- Unified Cartable V1
- Notifications V1
- Audit V1
- Organization Settings
- Custom Fields foundation/integration
- System-generated business codes
- External organizations, departments and contacts as a separate domain
- Persian RTL/Jalali UI foundations

## Critical domain decisions
- Role = reusable set of application permissions.
- A normal user has at most one application Role.
- Responsibilities such as Project Manager or Department Manager are contextual assignments, not generic global Roles.
- Internal users/departments and external organizations/contacts are separate domains and must not be merged.
- Workflow, Documents, Cartable, Notifications and Audit are shared capabilities and must not be duplicated in business modules.
- Backend authorization is mandatory; frontend checks are UX only.
- Business/system codes are generated by the application. Users should not maintain them manually.
- Custom-field technical keys are system-only data. The user enters a field label, not a technical key.
- Preserve Modular Monolith boundaries. Do not introduce cross-module database coupling.

## Current repository checkpoint
The two blockers previously recorded in the handoff were re-inspected on `development`:

- `app/Core/ExternalParties/Http/Controllers/ExternalPartyController.php`: the current file is syntactically complete and contains valid `custom_fields` validation for all create/update operations. The previously documented malformed edit is not present.
- `app/Models/Concerns/HasSystemCode.php`: the current implementation performs a query-builder update of only `code` after creation, avoiding the prior Eloquent `updateQuietly()` path associated with the SQL Server identity-column error.
- `tests/Feature/Core/SystemCodeGenerationTest.php` exists and covers generated system codes across Core, Projects and External Parties.

These are therefore not new implementation blockers on the current branch. The remaining requirement is local SQL Server validation.

## Working protocol — mandatory
- The AI has repository access and is responsible for writing/editing the code and tests in GitHub.
- The user does not manually edit project code.
- After every code change, give the exact PowerShell command the user should run locally to pull the change and validate it.
- Use `git pull origin development` at the beginning of the PowerShell validation command **only when code changes were made immediately before that validation**.
- Do not include `git pull origin development` when no code change was made.
- Prefer one validation step at a time.
- Do not ask unnecessary confirmation questions.
- When a normal implementation/debugging decision does not change the approved project direction, make the decision and implement it directly.
- Ask/wait for user approval only when proposing a change to the approved project path, architecture, scope, module/version boundary, or another decision that materially changes direction.
- Fix root causes. Do not weaken tests, bypass authorization, remove safeguards, or use temporary hacks just to make tests pass.
- Inspect the actual repository and relevant persistent documents before changing code.
- Add/update regression tests for meaningful behavior and defects.
- When frontend code changes, include frontend build validation at the appropriate stage.
- UI/UX must follow the latest available Google Material Design principles at implementation time, adapted correctly for Persian RTL, accessibility, readability, spacing, hierarchy and responsive behavior.
- Keep explanations concise; focus on what changed, why, and the exact next validation step.
- Update `ROADMAP.md`, `CURRENT_STATUS.md`, `PROJECT_CONTEXT.md` or other authoritative documents when implementation/status/architecture materially changes.

## Normal execution loop
**Inspect → Root Cause/Boundary Review → Smallest Coherent Change → Tests → Build → User Manual Acceptance → Documentation → Next Item**

## New conversation startup
Before making the first code change in a new conversation:
1. Read this file.
2. Read `CURRENT_STATUS.md`.
3. Read `ROADMAP.md`.
4. Inspect the repository state and the relevant implementation.
5. Continue from the documented checkpoint; do not redesign or restart the project.

## Immediate next work
1. Validate V2 correspondence tests against the configured SQL Server database.
2. Run the frontend production build.
3. Manually verify editor, file-only and combined composition modes, including download access to attached documents.
4. Fix any defects found at root cause and retain regression coverage.
5. Continue V2 correspondence detail/edit, referrals/instructions and Workflow/Cartable integration.


## Current task activity model — 2026-10-10
- Use `task_activities` as the single storage table for planned next activities and completed activity reports.
- `activity_type=planned,status=pending` appears under «فعالیت‌های بعدی»; completing it updates the same row to `activity_type=logged,status=completed` and records `completed_by/completed_at`.
- Manual completed activity reports are also `logged/completed` rows. `started_at` and `completed_at` live on `task_activities`.
- Migration `2026_10_10_120000_unify_task_activities_and_work_logs.php` moves historical `task_work_logs` into `task_activities` before dropping the old table. Validate migration and rollback behavior through the focused task tests.
- Task detail UI uses «ثبت فعالیت انجام‌شده» and a persistent guide explaining the difference between planned and completed activities.


## Deferred UI acceptance item — task activity guide (2026-10-10)
- Do not re-add or reposition the persistent task-activity guide during admin-account checks.
- During V1 manual acceptance with a normal user account, review the task detail page and, if the guide is still needed, place it in a dedicated full-width section below the two-column layout containing «فعالیت‌های بعدی» and the «ثبت فعالیت بعدی» section/button. Do not place the paragraph inside either column.
- The user explicitly deferred this UI decision until normal-user acceptance; leave it pending rather than making further changes now.


## Authorization correction — 2026-10-12
- The active `SYSTEM_ADMIN` role is a global system capability: `User::hasPermission()` returns true and `permissionScopes()` returns organization scope for it, while inactive users remain denied.
- User capabilities are additive: actual position/context responsibilities derive operational access, and an application Role can explicitly grant additional permissions. Role grants must not be silently removed by `CorePermissionSeeder`.
- The role permission UI exposes active operational permissions and retains per-permission scope selection. `SyncRolePermissionsAction` validates scopes but does not prohibit operational grants.
- Regression tests were added for system-admin access and operational role grants. Focused tests and frontend build are pending; remain in V1 until manual acceptance is complete.


## PowerShell validation output — 2026-10-12
- Keep validation commands friendly to Windows PowerShell: avoid Persian status/error strings that may render incorrectly in the user's console.
- Do not use `exit` to propagate a failing test status because it closes the user's PowerShell session. Capture and display `$LASTEXITCODE` instead, leaving the shell open.
- Current blocker: `tests/Feature/Core/CorePermissionSeederTest.php` had a PHP parse error caused by doubled backslashes in a fully qualified class name. The fix is committed; rerun the focused authorization tests before proceeding.


## Authorization test fixture correction — 2026-10-12
- `UserAuthorizationTest`'s ordinary role fixture must never use code `SYSTEM_ADMIN`, because that code intentionally triggers the global administrator bypass. Use a non-system test role; dedicated admin tests should create `SYSTEM_ADMIN` explicitly.
- Tests that need a permission already seeded/granted in setup should retrieve or `firstOrCreate` the permission by unique code rather than inserting a duplicate row (SQL Server enforces `permissions_code_unique`).
- Focused authorization rerun is pending after these fixes.


## 2026-10-21 — Correspondence visibility and detail page

- Correspondence navigation now appears when the user has `correspondence.view` or `correspondence.create`; the destination respects the available permission.
- Added `correspondence.show` and the `Correspondence/Show.vue` detail view. Keep attachment authorization delegated to the existing document download policy and do not expose storage paths.
- Added HTTP coverage for opening correspondence details. Local validation is pending; keep `master` unchanged.


## 2026-10-21 — V2 correspondence visibility baseline

- Implemented the first security slice on `development` only; `master` remains unchanged.
- Migration `2026_10_21_120000_add_classification_and_user_access_to_correspondences.php` adds `classification` (`ordinary` default) and `correspondence_user_access`. The `granted_by` column intentionally has no FK to avoid SQL Server multiple-cascade-path issues.
- `CorrespondenceAccessService` is the current source of truth for list/detail visibility. Registered ordinary letters are visible to active users with `correspondence.view`; drafts are scoped to creator/participant/secretariat; confidential letters are scoped to creator/registrar/participant/secretariat.
- `CorrespondenceController` uses this service for index, detail, and visible reply choices; linked reply metadata is filtered as well.
- `DocumentPolicy` delegates correspondence attachment access to the same service before applying generic document-level access, to prevent bypassing confidentiality.
- `CorrespondenceHttpTest` now covers registered ordinary visibility, denial for unrelated confidential viewers, and access for explicit participants.
- Validation is pending: pull the branch locally, run `php artisan migrate`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. Do not proceed to referral UI until those pass.

## Latest handoff — 2026-10-26 Core Audit integration for correspondence workflow

- Added Core Audit recording to CorrespondenceWorkflowService::record() using AuditServiceInterface. Each business action remains stored in correspondence_actions; the Core Audit record stores the action event, action row ID, and source/target department/user IDs as metadata.
- Extended the existing manager-approval HTTP regression test to assert the matching audit_logs row.
- Code is committed to development; master is unchanged. Local test execution is pending. Run the exact focused PowerShell command in CURRENT_STATUS.md after pulling.
- Next: validate this slice, then audit registration/copy/dispatch transitions and the Cartable/notification integration contract. Do not start Forms & Requests until the remaining V2 acceptance gate is reviewed.


## 2026-10-27 — Cartable TypeScript build fix

- Corrected the Cartable template to use `letter.current_department.name`, consistent with the `CorrespondenceItem` TypeScript interface. No application behavior or backend logic was changed.
- Committed to `development` only. Run `npm run build` after pulling and confirm the result; do not claim validation until reported.


## 2026-10-27 — Open correspondence UX and numbering requirements

- Open UI fix: correspondence index should render `registered_at` in Persian/Jalali date and Tehran local time, labeled `ثبت نهایی`, rather than exposing raw ISO UTC.
- Numbering design requirements: keep `شماره نامه` separate from the secretariat-assigned `شماره ثبت`; custom letter-number formats should allow two or three fields; each organizational department needs a required configurable short code (e.g. `IT`) to use in the letter-number format instead of its numeric database ID.
- Before implementation, inspect existing settings, department model/form, number generation, database constraints, and feature tests. Preserve compatibility with existing records. These are backlog requirements, not completed changes.

## 2026-10-27 — Correspondence number separation and department codes (validation pending)

- Added a dedicated letter_number field. New drafts receive a letter number immediately using the organization-configured template; the Secretariat assigns a separate registration_number at final registration (REG + configured separator + sequence). Existing registration numbers are preserved; legacy drafts without a letter number receive one at final registration.
- Added a separate counter table for letter numbers while retaining the existing counter for Secretariat registration numbers.
- Custom letter-number templates now accept two or three distinct components. The settings form exposes a two/three-part selector and persists only the selected components.
- Department creation/editing now requires a unique short alphanumeric code (hyphens/underscores allowed), persists it, and uses it in letter-number templates instead of the database ID. Existing department codes are preserved.
- Correspondence list/detail surfaces display letter number and registration number separately. The list formats registered_at as a Jalali date and Tehran local time and labels it «ثبت نهایی»; search includes both numbers.
- Added/adjusted regression coverage for two-part templates, distinct registration numbers, draft letter-number assignment, and department code persistence.
- Validation pending: pull development, run focused correspondence numbering/service and department tests, then full php artisan test and npm run build. No tests or build have been run by the assistant. master unchanged.

2026-10-27 — Correspondence copy recipient UX and ongoing workflow

- The creation form now places optional copy recipients at the bottom and uses a checkbox multi-select list; users can select multiple departments.
- Correspondence create date inputs now use the shared JalaliDatePicker component for consistent Persian font styling.
- Added POST `correspondence.copies.store`: an active user must be able to view the letter and be the manager of its current responsible department; the workflow must not be completed. One or more active departments may be added as copies. Existing `CorrespondenceWorkflowService::addCopies()` handles idempotent attachment and action/audit recording.
- Added focused HTTP coverage for manager authorization and multiple recipients. Local validation is pending: `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. No database migration is required; `master` remains unchanged.

2026-10-27 — Related correspondence field and required dates

- Form label changed to «در ارتباط با مکاتبات قبلی (اختیاری)» to reflect reply and continuation relations.
- Related correspondence is searched remotely via correspondence.related-search; query matches subject, letter number, registration number, external registration number, internal department name/code, and external organization/department name/code. Search is visibility-filtered and returns up to 20 results with subject, letter/registration numbers, date, and party details.
- sent_at is required for outgoing and received_at is required for incoming, with matching UI markers and backend validation.
- Focused tests added; local test/build validation pending. No database migration required. Do not modify master.

## 2026-10-27 — Related correspondence selector UI alignment (validation pending)

- Both related correspondence and copy-recipient controls now use `sm:col-span-2` for matching widths.
- Removed the separate «برای جستجو حداقل دو نویسه وارد کنید.» panel; the minimum length is shown in the search placeholder. Search result rows appear in a bordered scroll region only after two characters are entered.
- Selected related letters now display in a single removable list styled consistently with selected copy recipients; nested borders were removed. No backend behavior changed.
- Local validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. `master` unchanged.

## 2026-10-27 — Related correspondence dropdown overlay correction (validation pending)

- Restored the outer border/padding around the related-correspondence selector.
- Search matches now appear in an absolute-positioned dropdown with a white background and shadow, overlaying following content instead of changing page layout. Selected letters remain in a separate list below the search input.
- Local validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. `master` unchanged.
