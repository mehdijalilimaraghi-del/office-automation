## 2026-10-27 — Correspondence form grid rows

- Expanded the external organization section (sender for incoming / recipient for outgoing correspondence) to span the full row in the four-column form grid. Validation pending: run the frontend build after pulling `development`.
- Kept the first correspondence metadata row at five large-screen columns for fields from correspondence type through the applicable date/recipient fields.
- Started a separate four-column grid for subject and all subsequent form fields, preventing the first-row column count from distorting the remaining sections. Recipient department retains its two-column span where shown, keeping the selector usable without pushing the date field to another row.
- Validation pending: owner to run the focused correspondence test and frontend build after pulling `development`.

## 2026-10-27 — Correspondence recipient field layout and validation

- Confirmed server-side validation already requires `recipient_department_id` for both incoming and internal correspondence; the form label explicitly displays a red required marker.
- Expanded the correspondence metadata grid to six large-screen columns and kept the recipient selector at two columns with `min-w-0`, so the wider tree selector does not force the adjacent Jalali date field onto a new row.
- Extended the correspondence HTTP regression test to verify that incoming and internal submissions without a recipient department are rejected and no draft is persisted.
- Validation pending: pull `development` and run `php artisan test --filter=CorrespondenceHttpTest`; report the result before further changes. `master` remains unchanged.

- Follow-up fix: `DepartmentTreeSelect.vue` now teleports its dropdown to `body`, positions it against the trigger, and recalculates on viewport scroll/resize. This prevents parent containers (including project section dialogs using `overflow-hidden`) from clipping the unit tree; the list retains a bounded height and internal scrolling. Validation is pending until the owner runs `npm run build` and `php artisan test`.

## 2026-10-27 — Department tree selector rollout checkpoint (validation pending)

- The shared `DepartmentTreeSelect.vue` now renders department-specific icons where supplied, hierarchical indentation/connector lines, expandable branches, search, and single/multiple selection. Internal and external unit pickers use the shared UI instead of page-specific native selects.
- Migrated selectors across Tasks, Projects, Correspondence, organization settings, department parent selection, user memberships, and external-party unit management. Backend selector payloads include `parent_id` and icon data where applicable.
- Replaced native Gregorian date inputs found in correspondence tracking with `JalaliDatePicker.vue`; date-time inputs must use `JalaliDateTimePicker.vue`. Do not create page-specific calendars.
- All related changes are committed on `development`; `master` remains untouched. **Validation pending**: the project owner must pull, run PHP tests and `npm run build`, and report the result. The assistant has not run the local test suite or frontend build.

## استاندارد دائمی رابط کاربری: انتخاب واحد و تقویم شمسی

- تمام انتخاب‌گرهای واحد در صفحات Vue باید از کامپوننت مشترک `resources/js/Components/DepartmentTreeSelect.vue` استفاده کنند؛ ساختن فهرست یا درخت واحد اختصاصی در یک صفحه مجاز نیست.
- درخت واحد باید نام و آیکون واحد، تورفتگی سلسله‌مراتبی، باز/بسته‌کردن زیرواحدها، جستجو و نمایش واضح والد/فرزند را داشته باشد. در حالت چندانتخابی، مقدارها باید از طریق `v-model:model-values` همگام شوند؛ در انتخاب تکی از `v-model` استفاده شود.
- داده‌های ارسالی به این کامپوننت باید تا حد امکان شامل `id`، `name` و `parent_id` باشند. اگر گزینه‌های فیلتر محدود به واحدهای مرتبط هستند، مراقب باشید حذف والدها باعث تخت‌شدن درخت نشود.
- برای تاریخ شمسی فقط از `JalaliDatePicker.vue` و برای تاریخ‌وساعت از `JalaliDateTimePicker.vue` استفاده شود. از ساخت تقویم محلی/اختصاصی در صفحه‌ها یا استفاده مستقیم از input تاریخ با ظاهر متفاوت خودداری شود. هر بهبود ظاهری تقویم باید در کامپوننت مشترک اعمال شود.
- بعد از افزودن صفحه یا فرم جدید، کل کد را برای انتخاب واحد و تاریخ بررسی کنید و کامپوننت‌های مشترک را به‌کار ببرید. در بازبینی UI نیز همه استفاده‌های قدیمی باید شناسایی و مهاجرت داده شوند.
- این تغییر در `development` است؛ تست‌ها و بیلد باید در محیط توسعه محلی اجرا و نتیجه توسط مالک پروژه تأیید شود. `master` نباید تغییر کند.

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


## 2026-10-27 — شماره نامه وارده و قالب مستقل شماره ثبت (در انتظار اعتبارسنجی)

- شماره نامه وارده باید عیناً از روی اصل نامه وارد شود و خودکار تولید نمی‌شود؛ شماره ثبت دبیرخانه جداست و هنگام ثبت نهایی با قالب مستقل قابل تنظیم تولید می‌شود.
- تنظیمات شماره ثبت شامل قالب ساده/سال/کد واحد/سفارشی، دو یا سه جزء، جداکننده و طول بخش ترتیبی است. شماره‌های تاریخی تغییر نمی‌کنند.
- دبیرخانه فقط تا پیش از اقدام گردش می‌تواند پیش‌نویس را ویرایش یا حذف کند؛ بررسی مجوز در سرور و تراکنش انجام می‌شود. اعتبارسنجی محلی هنوز انجام نشده است.


### اصلاح بر اساس گزارش تست 2026-10-27

- خطای نحوی آرایه اعتبارسنجی در `CorrespondenceController::update` شناسایی و اصلاح شد؛ این خطا علت بیشتر خطاهای 500 در گزارش قبلی بود.
- قالب پیش‌فرض شماره ثبت به «شماره ترتیبی» تغییر کرد تا با قالب پیش‌فرض شماره نامه تداخل نداشته باشد. تست انتظار شماره ثبت نیز به‌روزرسانی شد.
- تست‌های محلی هنوز پس از این اصلاحات دوباره اجرا نشده‌اند؛ نتیجه قبلی دیگر معیار وضعیت فعلی نیست.

### پیگیری تست‌های 2026-10-27

- اجرای مجدد سه فایل تست: 41 تست موفق و یک تست ناموفق بود. علت تنها شکست، قدیمی‌بودن داده ورودی تست «الزام فایل اصل نامه» بود؛ تست شماره نامه وارده را ارسال نمی‌کرد و پیش از رسیدن به اعتبارسنجی `original_letters` با خطای `letter_number` متوقف می‌شد.
- داده تست با افزودن `letter_number` اصلاح شد تا تست صرفاً الزام فایل اصل نامه را بررسی کند. اجرای دوباره تست پس از این commit هنوز در محیط محلی تأیید نشده است.

## 2026-10-28 — Draft correspondence privacy and edit/delete audit

- Audit confirmed that edit/delete endpoints exist, but are authorized only for Secretariat users while a correspondence is still a draft and has no workflow action. Final-registered correspondence cannot currently be edited or deleted; direct endpoint requests are denied by the same server-side guard.
- Tightened draft visibility: drafts are now visible only to their creator and the Secretariat, regardless of department membership, current workflow relationships, or the all-scope filter. Direct detail access follows the same rule. Registered ordinary and registered confidential correspondence retain their existing visibility rules.
- Added HTTP regression coverage for creator-only draft visibility. UI edit/delete controls remain conditional on the backend-provided can_manage_before_action flag; if absent for a Secretariat user, verify that the user's configured Secretariat department is active and correctly assigned.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. No test/build result is claimed. master unchanged.

## 2026-10-28 — Correspondence edit/delete business rule clarified

- Final registration alone does not block editing/deletion. It only communicates the letter to the target department.
- Incoming correspondence can be edited/deleted by Secretariat; outgoing and internal correspondence can be edited/deleted by their creator, provided no workflow/action record, reply, or linked task/request exists.
- Edit/delete authorization remains enforced server-side and the UI uses the same capability flag. Draft visibility remains restricted to creator and Secretariat.
- Added/updated HTTP coverage for editing and deleting registered incoming correspondence by Secretariat, registered outgoing correspondence by creator, denial to another creator, and denial after a workflow action. Validation pending in local environment.
- master unchanged.

## 2026-10-28 — Fix correspondence edit/delete capability after registration bookkeeping

- Root cause found: `canManageBeforeAction()` treated any `correspondence_actions` row as a workflow action. The registration lifecycle itself writes `registered` and, for incoming mail, `secretariat_initial_referral`; adding a copy recipient writes `copy_added`. Therefore these non-user-workflow records could suppress edit/delete controls immediately, even though the business rule allows editing after registration until a real workflow action.
- Updated the guard to ignore only `registered`, `secretariat_initial_referral`, and `copy_added` when deciding whether a workflow action has occurred. Actual workflow actions, replies, and linked tasks still block edit/delete. Both UI capability flags and update/delete endpoints use this shared guard.
- Extended `CorrespondenceHttpTest` so the registered incoming Secretariat edit/delete test includes all three bookkeeping action types and still verifies that a real `manager_approve` action blocks deletion.
- Commits on `development`: `ca19d62e664af9c504c02ae4892f8d74794e0ea7` (service fix), `eb014380ff67df01792c466c65a4066bc91087b1` (regression test).
- Validation is pending in the local Windows/SQL Server environment. No tests or frontend build have been run by the assistant. `master` unchanged.

## 2026-10-28 — Fix SQL Server delete failure for correspondence bookkeeping actions

- After the editability guard was corrected to ignore registration bookkeeping actions, the regression test exposed a second issue: deleting an eligible correspondence attempted to delete its parent row while `correspondence_actions` still referenced it, causing SQL Server FK error `correspondence_actions_correspondence_id_foreign`.
- Updated `CorrespondenceController::destroy()` to delete the correspondence's action-history rows inside the same transaction before deleting the correspondence. This endpoint is already guarded by `canManageBeforeAction()`, so real workflow actions, replies, and linked tasks continue to block deletion; only letters eligible for pre-action deletion reach this cleanup.
- Commit on `development`: `1cc1d7da270da6c5d7c8820ce1b3fb6a9ed6691b`.
- Validation is pending; the targeted feature test has not yet been rerun after this fix. `master` unchanged.

## 2026-10-28 — Unified correspondence create/edit form (validation pending)

- The incoming letter-number label is now «شماره نامه وارده» with a question-mark tooltip explaining: «شماره نامه درج‌شده روی اصل نامه وارده را وارد کنید.» The field shows one required marker.
- Edit now renders the same Vue form used for creation, with existing correspondence values, linked letters, copy departments and original-letter filenames preloaded. Correspondence type remains fixed during editing; existing original files are preserved and additional files can be attached.
- Expanded the update endpoint to validate and persist editable metadata/body, linked correspondence, copy departments, and newly uploaded original/attachment files. Existing files are retained. Authorization still uses the shared pre-action guard and is rechecked inside the transaction.
- Local validation pending: php artisan test --filter=CorrespondenceHttpTest, then npm run build. No test/build result is claimed. master unchanged.

## 2026-10-28 — Required-field asterisk convention

- Fixed the incoming letter-number label by removing its manually rendered `<span>*</span>`; `resources/css/app.css` already adds the red asterisk with `label:has(+ :is(input, select, textarea):required)::after` when the label is followed by a native required control. Keeping both creates two stars.
- Permanent UI rule: for controls covered by the global `::after` selectors, mark the control `required` and do not add a literal/manual asterisk to the label. The global CSS is the single source of the visual marker. For custom controls not covered by the selectors, either add exactly one explicit marker or extend the CSS selector—never both. Required validation must still be enforced independently.
- The rule is documented beside the CSS selectors and in project context documents to prevent recurrence. Validation pending: after `git pull origin development`, run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`; no test/build result is claimed.


## 2026-10-28 — Favicon aligned with application logo

- Replaced the unrelated teal document/checkmark favicon with the same purple gradient mark used by `resources/js/Components/ApplicationLogo.vue`, keeping the browser tab icon consistent with the in-app logo.
- Validation pending: pull `development`, run `npm run build`, then hard-refresh the browser or clear its favicon cache if the old icon remains.


## 2026-10-28 — Incoming correspondence edit test fixture

- Fixed the registered incoming-correspondence edit/delete regression test fixture: it now includes a valid linked `correspondence_original` document, matching the business rule that an incoming letter must always retain its original scan/PDF. The production validation remains unchanged; the earlier failure was caused by an invalid fixture without an original attachment.
- Validation pending: run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build` after pulling `development`.


## 2026-10-28 — Restore incoming letter number required marker

- The required asterisk disappeared because the incoming letter number label sits inside a flex wrapper with a help tooltip, so the generic adjacent-label CSS selector did not match it.
- Added the reusable `required-field-label` class selector and applied it to the incoming letter number label. The input remains natively `required`; this restores the red visual marker without adding a duplicate manual asterisk.
- Validation pending: run `npm run build` and confirm the red asterisk appears once beside «شماره نامه وارده».

## 2026-10-28 — System administrator correspondence deletion

- System administrators can now delete any correspondence from the list, including registered letters and letters that already have workflow actions. This is separate from ordinary edit/delete eligibility; normal users retain the existing restrictions.
- The delete endpoint rechecks authorization inside the transaction, records who deleted the letter in Core Audit metadata, removes related action/link rows, and clears replies' pointer to the deleted correspondence so they remain intact.
- Added a feature test for a system administrator deleting a correspondence after a workflow action. Validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. No tests/build have been run by the assistant; `master` remains unchanged.

## 2026-10-28 — Incoming correspondence description

- Added a dedicated “توضیحات نامه” text area for incoming correspondence. It stores a concise description/summary independently of the required scanned original letter.
- Existing `body` storage is reused for this field; incoming descriptions are preserved on edit and displayed as plain text on the correspondence details page. Other correspondence types retain the rich-text body behavior.
- Added a feature test proving that the description is saved alongside the original-letter upload. Validation pending: `php artisan test --filter=CorrespondenceHttpTest` and `npm run build` after pulling `development`.

## 2026-10-28 — Correspondence form grid alignment

- Adjusted the create/edit form grid in `resources/js/Pages/Correspondence/Create.vue`: for outgoing and internal correspondence, “واحد ایجادکننده” spans two columns; outgoing “موضوع” spans three columns so it shares its row with the send date; internal “موضوع” spans two columns so it can share its row with the related-correspondence section. No backend behavior changed.
- Validation pending: pull `development` and run `npm run build`. `master` remains unchanged.

## 2026-10-28 — Internal correspondence subject placement correction

- Moved the internal-correspondence “موضوع” field into the first details grid beside “واحد دریافت‌کننده”, with both fields spanning two columns. The internal subject is no longer rendered in the following grid; outgoing and incoming subject placement is unchanged.
- Validation pending: pull `development` and run `npm run build`. `master` remains unchanged.

## 2026-10-28 — Correspondence details page layout

- Reorganized `resources/js/Pages/Correspondence/Show.vue` into a two-column desktop layout: correspondence metadata and letter attachments in the left column; letter body/description and workflow actions in the wider right column.
- Consolidated duplicated metadata into one “اطلاعات مکاتبه” section, including type, status, letter/registration numbers, creation/registration dates, departments, creator/registrar, sender/recipient, and relevant external organization fields.
- Added a separate two-column section below for “لیست ارجاعات” and “لیست رونوشت‌ها”. The referral list is derived from workflow actions with a destination or referral/request action type. Copy management remains available in the copies column when permitted.
- The outgoing external-tracking section and related-replies section remain available. Validation pending: pull `development`, run `npm run build`, and inspect incoming, outgoing, and internal correspondence details. No tests/build have been run by the assistant; `master` remains unchanged.

## 2026-10-28 — Correspondence detail refinements and outgoing file mode

- Removed the type/letter-number/registration-number subtitle below the correspondence subject; those details now appear in the consolidated metadata section only.
- Widened the correspondence detail page and explicitly set the desktop grid direction so metadata and attachments occupy the left column while the letter content/actions occupy the wider right column. Each column retains RTL text direction.
- Renamed incoming original-letter files to “فایل نامه” and placed that section directly below the letter description. Separate accompanying attachments remain in their own section.
- Simplified composition choices in the create/edit UI to “ویرایشگر داخلی” or “پیوست فایل”. For outgoing file mode, a required “توضیحات فایل نامه” is collected and stored as plain text in the existing body field; the uploaded letter file is labeled “فایل نامه” on the detail page. Editing detects plain-text file descriptions and preserves file mode.
- Made “سازمان مخاطب” required for outgoing correspondence in both client-side and server-side validation, including updates.
- Validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`, then inspect incoming/outgoing/internal details and create/edit outgoing correspondence in both modes. No tests/build have been run by the assistant; `master` remains unchanged.

## 2026-10-28 — Outgoing organization requirement test fixtures

- Updated the correspondence HTTP test fixtures to supply a valid active external organization wherever an outgoing correspondence is expected to pass validation. This preserves the production rule that «سازمان مخاطب» is required, while allowing the file-mode missing-attachment test and registered-outgoing edit test to reach the specific behavior they assert.
- Validation pending in the local environment: run `php artisan test --filter=CorrespondenceHttpTest`. No test result is claimed; `master` remains unchanged.

## 2026-10-28 — Follow-up correspondence test payloads

- Updated the file-mode attachment validation test to include the required plain-text file description, so the request reaches the missing-attachment assertion instead of failing earlier on the description rule.
- Updated the registered outgoing correspondence edit fixture/request to use editor mode with a body, so the test exercises edit permissions and metadata updates rather than triggering the file-mode description validation.
- Validation pending: run `php artisan test --filter=CorrespondenceHttpTest` locally. No test result is claimed; `master` remains unchanged.


## 2026-10-28 — Correspondence details layout and incoming registrar permissions

- Reduced the correspondence metadata column width by changing the desktop grid ratio from 1:1.65 to 0.7:1.65. Metadata is now a single vertical list with one label/value pair per row.
- For incoming correspondence, the creator department is set to the configured active Secretariat department rather than inferred from the registering user's other department memberships.
- Historical rule superseded on 2026-10-31: the blanket view-only restriction for a Secretariat registrar who also manages the current department was removed. Workflow permissions are now evaluated by the current department/action context so overlapping roles can be used without switching roles.
- Validation pending in the local environment: run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`, then verify a Secretariat registrar who also manages the recipient department can view but cannot act or add copies. No test/build result is claimed; `master` remains unchanged.


## 2026-10-31 — Correspondence workflow actions and role fusion (validation pending)

- Manager referral now distinguishes the target type: another active department or an active employee who belongs to the current department. User-level referrals preserve the department and assign the correspondence to the selected user.
- Removed the blanket rule that blocked a Secretariat member from acting on an incoming letter they registered. Authorization is evaluated in the current department/workflow context, so multiple roles can coexist instead of forcing a manual role switch.
- Added a dedicated outgoing-letter return path: Secretariat can return a registered outgoing letter while it is awaiting dispatch, with a required correction note; the letter returns to its originating department/creator. The creator or origin-department manager can resubmit it to the configured Secretariat, restoring the awaiting-dispatch state. Final-dispatched letters cannot use this return action.
- Removed the task/project/new-letter request options from the workflow action selector because the existing handlers only changed routing/state and did not create the corresponding business object. These options must return only when their real task/project/letter creation flows are integrated.
- Added focused regression tests for employee referral and the Secretariat return/resubmission lifecycle. Validation remains pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build and manually inspect all three correspondence types. master unchanged.


## 2026-10-31 — Same-department employee referral state regression

- Confirmed the manager-to-employee referral implementation correctly sets `workflow_state=assigned_to_employee` while retaining the current department and assigning `current_user_id` to the selected active department member.
- This state is required by the employee-result guard: only the specifically assigned employee may submit the documented result; another member of the same department must be denied. After submission, the state advances to `result_pending_manager`.
- The failing HTTP test had a stale expectation of `in_progress` after employee referral. Corrected the regression assertion to `assigned_to_employee`; no production workflow behavior or schema changed.
- Validation pending: owner to pull `development` and run the focused `CorrespondenceHttpTest` once. `master` remains unchanged.

## 2026-10-31 — Correspondence department assignment audience

- Department-level correspondence routing belongs to the current department manager until that manager explicitly assigns it to an employee. Do not surface actionable correspondence to every member of the department.
- Cartable includes correspondence assigned to the current user and manager-owned items; it must not use current-department membership as an actionable audience. Add `assigned_to_employee` to its supported states so the specifically assigned employee receives the item.
- Backend `submit_result` authorization and the Show-page action flag must both require `workflow_state=assigned_to_employee` and `current_user_id=actor.id`. Department membership alone is never enough.
- The shared department tree selector is teleported to body; keep its viewport positioning clamped and responsive to scroll/resize.
- Implementation committed on `development`; focused correspondence/Cartable tests and frontend build pending owner validation. `master` unchanged.


## 2026-10-31 — Correspondence personal ownership and action filters

- Added a second independent filter to the correspondence list: «همه مکاتبات»، «نیاز به اقدام»، and «اقدام قبلی ثبت‌شده توسط من». It composes with the existing «مکاتبات من / مکاتبات واحد من / همه مکاتبات مجاز» scope and preserves other search filters and pagination query parameters.
- «نیاز به اقدام» is based on the current assignment (current_user_id equals the signed-in user). «اقدام قبلی ثبت‌شده توسط من» requires both a historical referral addressed to that user (correspondence_actions.to_user_id) and an action authored by that user (actor_id), and excludes letters currently assigned back to that user so pending work takes precedence.
- Corrected the «مکاتبات من» ownership scope: an action authored by the user is no longer sufficient by itself. Historical referrals addressed to the user remain included; merely referring a letter to another user/department does not make it the referrer's personal correspondence.
- Added feature regression coverage for pending referrals, previously acted-on referrals, and letters merely referred onward by the current user. No database migration or workflow authorization changes.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master remains unchanged.


## 2026-10-31 — Correspondence list action-filter semantics

- The second correspondence filter defaults to «نیاز به اقدام» and offers «اقدام قبلی ثبت‌شده توسط من»، «ثبت‌شده توسط من»، and «همه مکاتبات». It composes with the first scope selector (personal, department, or all visible).
- Pending action is based on the latest action referral addressed to the user: the correspondence must still be assigned to that user, and the user must not have recorded a later action. This handles actions that leave current assignment unchanged and repeated referrals to the same user.
- Previously acted-on means the user recorded an action after the latest referral to them. A newer referral resets the correspondence to pending until another action is recorded.
- Created/registered by me includes both created_by and registered_by. All correspondence retains the existing personal-relevance scope.
- Regression coverage added for default pending, prior actions, assignment retained after action, creator/registrar, and all-related mode. Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master unchanged.


## 2026-10-31 — Draft visibility test and independent correspondence filters

- Tests that assert draft visibility through the correspondence index must pass both `scope=all` and `action_filter=all`. The default action filter is `needs_action`, which intentionally excludes drafts that are not currently assigned to the viewer.
- Keep authorization/visibility assertions separate from action-filter assertions; do not change production draft visibility rules to satisfy a test that omitted the independent action filter.
- Test fixture alignment committed on `development`; focused `CorrespondenceHttpTest` rerun pending. `master` unchanged.


## 2026-10-31 — Multi-department manager pending filter

- «نیاز به اقدام» must support both direct user referrals and department-level assignments to any department currently managed by the user.
- For department routing, use current_department_id matched against the user's managedDepartments, and confirm the latest routing action targets that current department. Include manager-actionable states (in_progress, result_pending_manager, awaiting_manager_approval); exclude assigned_to_employee so department membership/management does not override explicit employee assignment.
- The «مکاتبات من» scope includes current correspondence in departments the user manages. Do not broaden this to ordinary department members.
- Regression test added for managers of multiple departments, null to_user_id department referral, and employee assignment exclusion. Focused test pending; no frontend change. master unchanged.


## 2026-10-31 — Correspondence index closure fix (validation pending)

- `CorrespondenceController::index()` now captures `$managedDepartmentIds` in the `scope=mine` query closure, fixing the undefined-variable error raised when the correspondence list was requested.
- This is a closure-scope correction only; personal scope and manager pending-action semantics are unchanged. The focused `CorrespondenceHttpTest` run is pending on the project owner's SQL Server environment. Branch: `development`; `master` unchanged.

## 2026-10-31 — Nested correspondence scope closure correction

- Corrected the inner `where(function ($mine) ...)` closure in `CorrespondenceController::index()` to capture `$managedDepartmentIds` as well. The earlier fix captured the variable only in the surrounding `when(scope=mine)` closure; PHP closures do not implicitly inherit variables from an enclosing closure, so the nested query still raised `Undefined variable $managedDepartmentIds` at the `orWhereIn` condition.
- No query semantics or authorization rules changed. Validation pending: pull `development` and run `php artisan test --filter=CorrespondenceHttpTest`. PHP/documentation-only change; no frontend build required. `master` unchanged.
