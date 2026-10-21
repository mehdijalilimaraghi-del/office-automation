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
