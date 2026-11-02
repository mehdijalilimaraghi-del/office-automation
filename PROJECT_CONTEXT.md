## 2026-10-31 — Regression fix: needs-action after direct referral

- Restored the direct-assignment rule that excludes a correspondence from `needs_action` when the assigned user has already recorded an action after the latest referral to them. Current assignments with no referral-history row remain actionable for legacy-data compatibility.
- Department-manager pending logic remains based on the current department manager and actionable workflow states, and excludes `assigned_to_employee`; this keeps manager routing independent from potentially incomplete historical referral rows.
- Regression source: `correspondence action filter distinguishes pending and previously acted referrals` had exposed a direct assignment with a later `REQUEST_TASK` being counted as pending. Validation pending: rerun `php artisan test --filter=CorrespondenceHttpTest`; `master` unchanged.
## 2026-10-31 — Correspondence action and department filters

- Reworked «نیاز به اقدام» to use the current assignment as the source of truth: direct current_user_id assignment, or an actionable current department whose manager_id is the user. The manager branch includes in_progress, result_pending_manager, and awaiting_manager_approval; assigned_to_employee is intentionally excluded. This avoids depending on complete historical referral/action rows and does not grant action rights to ordinary department members.
- Added a department filter to the correspondence index. In «مکاتبات من» / «مکاتبات واحد من», the default «من» leaves the existing scope unchanged and selectable departments are limited to the user's memberships plus managed units. In «همه مکاتبات مجاز», the default is «همه واحدها» and the options (including inactive units for historical correspondence filtering) come from the Core Departments module's DepartmentServiceInterface::list().
- A selected unit narrows only correspondence-to-department associations (created, recipient, current, copy, or workflow action). It is always applied after CorrespondenceAccessService::visibleToQuery; confidential visibility and all existing scopes remain enforced. Invalid or out-of-scope unit IDs are normalized to the scope default.
- Reused the shared DepartmentTreeSelect.vue and added support for non-department sentinel options («من» / «همه واحدها») without changing existing selector behavior.
- Added regression tests for manager-owned current assignments without referral history, employee assignment exclusion, result return to manager, department option scoping, and confidential-letter visibility under unit filtering. Validation pending: run the focused correspondence tests and npm run build; master unchanged.
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

- Owner confirmed `php artisan test --filter=CorrespondenceHttpTest` passes for final-registration audit. Registration writes the module business-history action and matching Core Audit event; the note contains only the registration number.
- Outgoing external tracking updates now use a transaction and row lock, audit only changed field names, and avoid persisting tracking note contents in audit old/new values or metadata. Unchanged updates do not create an audit event.
- Added a regression test for tracking persistence and audit privacy. The new grouped change has not yet been validated by the project owner. After pulling `development`, run the full Laravel suite and frontend build once. `master` remains unchanged.

## 2026-10-26 — Correspondence audit follow-up

- New copy recipients are recorded in both the module-specific `correspondence_actions` history and Core Audit. The writes are transactional, and attaching the same department again does not create duplicate audit/history entries.
- Added focused regression test: `tests/Feature/Modules/Correspondence/CorrespondenceCopyAuditTest.php`.
- This slice does not yet claim end-to-end completion of correspondence notifications, Cartable integration, registration audit, or dispatch audit; those remain to be checked against the V2 acceptance gate.
- Validation is pending on the project owner's environment; do not claim tests pass until their result is reported.

# Project Context

This document is the persistent working context for the Office Automation project. It records architectural decisions, current implementation status, conventions, and the recommended next path so future development can continue without re-discovering the project.

## 1. Project Goal

Build a Persian-language company office-automation system as a **Modular Monolith** from the beginning.

The product is versioned. ROADMAP.md is the authoritative product/version source.

Current version:
- V1 — Foundation & Work Management

V1 includes Core, internal organization, Projects, Tasks, Custom Fields and the shared operational backbone required by them.

Current checkpoint:
- V1 technical/debugging and manual UI/functional acceptance.

Next version:
- V2 — Office Automation

The architecture must allow future modules without rewriting the core.

Initial deployment:
- Company LAN

Future possibility:
- VPS / hosting / cloud

Target organization size:
- Approximately 10–300 users

No accounting module is planned.

## 2. Technology Stack

- Laravel 12.69.2
- PHP 8.5.10
- Vue 3.5.42
- TypeScript 7.0.2
- Inertia.js
- Tailwind CSS 4.3.3
- Vite 7.3.6
- SQL Server
- Windows development environment
- Node.js 24.18
- NPM 11.16
- Composer 2.10.3

Frontend language:
- Persian

Direction:
- RTL

Font:
- Vazirmatn via @fontsource/vazirmatn

## 3. Git Rules

Active development branch:
- development

Stable branch:
- master

Do not develop directly on master.

Before working with an updated repository, the preferred sync command is:

    git pull origin development

After meaningful changes:
- run relevant tests
- run frontend build when frontend changes are involved
- keep commits focused and meaningful

## 4. Architecture

Architecture style:
- Modular Monolith

Core modules/concepts:
- Users
- Departments
- Roles
- Permissions
- Authorization
- Projects
- Tasks
- Dashboard

Implemented/current capabilities also include:
- Custom Fields
- External organization / department / contact management
- Documents / attachments foundation
- Workflow V1
- Unified Cartable V1
- Notifications V1
- Audit V1
- Organization Settings

Future modules and versions are defined in ROADMAP.md.

Modules must avoid direct hard dependencies on unrelated modules.

Prefer:
- contracts
- services
- actions
- events
- feature flags where appropriate

## 5. Internal Organization

There are two distinct organization concepts and they must not be merged.

### Internal Organization
The company's own organizational structure.

There is currently one internal company and no organizations table is required for it.

Users:
- may belong to multiple departments
- may have one primary department

Departments:
- unlimited hierarchy using parent_id
- each department has one manager
- a user may manage multiple departments
- manager must be a member of that department
- authorization is permission-based, not hard-coded to manager status

Rules:
- assigning a manager automatically adds membership if needed
- removing a manager does not remove membership
- a manager cannot remove their own membership while still manager
- a user cannot be deleted while managing departments
- department deletion is blocked if it has children or a manager
- task.department_id is cleared when a department is deleted

### External Organization
Customers, suppliers, partners and other third parties belong to a future Contacts / External Parties concept.

Do not merge external organizations with internal departments.

## 6. Department Membership

Implemented through department_memberships.

Fields include:
- department_id
- user_id
- is_primary
- joined_at

Constraints:
- unique department/user pair
- maximum one primary department per user
- manager must be a member

Legacy users.department_id has been migrated into memberships.

## 7. Shared Project and Project Sections

The current model uses one shared organization-level default project rather than automatically creating one project per department. Departments are represented within projects through project sections.

Each project section:
- belongs to one project
- identifies its responsible internal department
- stores a name, description, sort order, and `manager_can_create_tasks` setting
- may have section-specific documents
- contains the tasks that belong to that department's workstream

Project administration:
- the project administrator can create tasks in any section
- a department manager can create tasks only for their own section when `manager_can_create_tasks` is enabled
- a section task is initially assigned to the department manager
- optional direct delegation records both the initial manager assignment and the subsequent manager-to-employee referral

The shared default project is created by `SharedProjectSeeder` on fresh installations. Existing per-department default projects are consolidated into this shared project by migration; a section is created for each legacy department project, tasks and linked documents are associated with those sections, and legacy project records are retained as inactive historical records.

Older notes in this file describing automatic per-department projects and their deletion lifecycle are historical and superseded by this section.
 
## 8. Task Domain

Task is conceptually independent of Project.

A task may have:
- optional project
- optional department
- optional assignee
- title
- description
- status
- priority
- dates
- creator
- assignment/referral history
- comments
- work logs
- reminders
- status history
- attachments (future)
- activity history

Current V1 has one current assignee, but the architecture should allow multiple assignees later.

Future task capabilities:
- dependencies
- subtasks
- recurring tasks
- advanced search/filter
- pagination
- My Tasks
- Board
- Calendar
- custom fields
- Gantt

## 9. Persian Terminology

Use these UI terms consistently:

- Task = «تسک»
- Comment = «نظر»
- Work Log = «گزارش فعالیت»
- Reminder = «یادآور»
- Referral = «ارجاع»
- Assignment = «تخصیص»
- Department = «واحد» when appropriate in user-facing context

Avoid «کامنت» in the UI.

## 10. Task Assignment and Referral

Referral does not create a new task.

The same task is transferred to another user.

tasks.assignee_id stores the current assignee.

task_assignments stores history:
- task_id
- from_user_id
- to_user_id
- assigned_by
- type
- note
- started_at
- ended_at

Initial assignment creates a history row.

Reassignment:
1. closes the current assignment
2. updates task.assignee_id
3. creates a new assignment record

The new assignee must be able to see relevant previous history, including:
- creator
- previous assignees
- timestamps
- referrer
- referral note/reason
- comments
- files
- status/activity history

Permission:
- tasks.refer

Referral authorization is intentionally:
- active user
- tasks.refer
- current assignee

It is not scope-based.

## 11. Task Access and Scopes

Supported scopes:
- self
- department
- department_tree
- project
- organization

Persian labels:
- «فقط موارد مربوط به خود»
- «واحد»
- «واحد و زیرواحدها»
- «پروژه»
- «کل سازمان»

TaskAccessService is responsible for:
- canAccess()
- applyScope()

Semantics:
- self: assignee or creator
- department: task.department_id belongs to one of user's departments
- department_tree: user's departments plus descendants
- project: assignee/creator or project manager/member
- organization: all tasks

Multiple scopes are combined as a union.

Inactive users are denied access.

## 12. Permissions

Task permissions currently include:
- tasks.view
- tasks.create
- tasks.update
- tasks.delete
- tasks.assign
- tasks.refer
- tasks.change_status
- tasks.comment
- tasks.log_work
- tasks.create_reminder

There are also permissions for:
- users
- departments
- roles
- projects
- permissions

Authorization must always be enforced server-side.

Frontend visibility is not security.

Default task scope is currently project unless explicitly configured otherwise.

## 13. Important Authorization Issue

A real application issue was identified:

A logged-in user can reach the dashboard but receives HTTP 403 when opening Tasks.

Root cause identified:
- the Tasks route is protected by permission:tasks.view
- the permission record existing in the database does not automatically grant it to a role/user
- therefore the user's effective permissions may not contain tasks.view

This is an **application authorization/configuration issue**, not a GitHub access issue.

The correct architectural fix is not to hard-code a specific email/user.

The next authorization work should:
1. inspect role/permission models and assignment flow
2. define principled system/default roles and permission grants
3. ensure roles can be assigned to users
4. ensure effective permissions are correctly evaluated
5. add end-to-end authorization tests
6. verify the real user can access Tasks after the proper role/permission assignment

Do not bypass middleware or weaken authorization just to remove the 403.

## 14. Task Policies and Routes

Task routes include:
- index
- store
- show
- update
- assign
- refer
- change status
- comments
- work logs
- reminders
- reminder status
- delete

TaskPolicy delegates access decisions to TaskAccessService where appropriate.

Reminder routes use scoped bindings so a reminder must belong to the specified task.

## 15. Task Activity Features

Implemented:
- comments / «نظر»
- work logs / «گزارش فعالیت»
- reminders / «یادآور»
- reminder lifecycle
- status history
- assignment history

Reminder lifecycle:
- pending -> completed
- pending -> cancelled

Completed reminders receive completed_at.

Reminder access is intentionally independent from task scope:
- active user
- tasks.view
- ownership/creator relationship as defined by the policy

## 16. Projects

Projects support:
- CRUD
- manager
- members
- active state
- dates
- department association
- system/default projects

Project membership is represented through users/membership relations.

When checking whether a user belongs to a project, prefer the project's users() membership query where appropriate.

## 17. Dashboard

The dashboard is implemented at `/dashboard` and uses the shared `TaskAccessService` scope for all task data.

Current dashboard behavior:
- Personal KPIs and the personal-task list are limited to open tasks assigned to the signed-in user.
- Managerial team metrics and team task list are a separate section, shown only when the user has an organization-wide task-view scope or manages departments/projects.
- Department managers see work belonging to their managed departments (including project sections); project managers see work in their managed projects. Organization-wide task scope sees the wider task set, excluding tasks assigned to the manager from the team panel so personal work stays separate.
- The seven-day activity chart counts visible tasks created and completed per day using persisted timestamps.
- Project and section progress is based on tasks visible under the same backend task scope.
- The root URL redirects guests to login and authenticated users to the dashboard, rather than showing an extra standalone welcome page.

Dashboard feature tests cover root routing and the personal/team payload contract. Local focused test, production build and manual role-based UI validation remain pending after the latest redesign.

## 18. Frontend/UI

Current frontend uses:
- Vue 3
- TypeScript
- Inertia
- Tailwind
- RTL
- Vazirmatn

Authenticated layout:
- Dashboard
- Tasks shown according to tasks_view ability
- Projects shown according to projects_view ability
- Persian labels
- responsive navigation

Task Index UI has been polished:
- page header
- count badge
- create task card
- search card
- task list
- RTL table
- status badges
- priority badges
- responsive spacing

Task Show UI has also been polished:
- task header
- ID/status/priority/active badges
- summary
- status change
- timeline/actions
- referral
- comment
- work log
- reminder
- edit/delete section
- RTL/responsive layout

UI polish is **not the immediate priority** now.

## 19. Testing Status

Important task tests that have passed include:
- TaskAssignmentTest
- TaskReferralHttpTest
- TaskReminderLifecycleTest
- TaskTimelineTest
- TaskActivityTest
- TaskActivityHttpTest
- TaskAccessScopeTest
- TaskPolicyTest
- status history tests

Core tests include:
- CorePermissionSeederTest
- DepartmentControllerTest
- UserControllerTest
- UserRelationshipsTest
- CoreRouteAuthorizationTest

Dashboard tests have passed.

Frontend build has passed with:
    npm run build

A full-suite run should still be performed after significant authorization changes.

## 19.1 Task UX Completion Checkpoint

Recent Task UX work completed:
- Task Show uses the shared Jalali date-time picker for work-log and reminder date-time fields.
- Task form validation errors are surfaced for status, referral, comment body, work-log fields, reminder time, and edit fields where applicable.
- Task Index now shows loading feedback while applying filters and when navigating pagination.
- Filter/pagination controls are disabled or visually indicate loading during navigation to avoid duplicate interaction.

The latest frontend changes require a frontend build verification before the next UX checkpoint.

## 20. Current Development Priority

The original bootstrap roadmap has been substantially completed. The repository now has working authorization/bootstrap roles, internal department/user management, projects, core task workflows, external-party management, optional external follow-up context on tasks, and a passing full test suite (284 tests at the latest checkpoint).

From this point, development follows a **completion-first roadmap** rather than adding unrelated features.

### Step 1 — Core Completion Audit
Before adding more advanced task features, verify the existing core surfaces as a complete business workflow:
- users
- departments and hierarchy
- roles
- permissions and scopes
- user-role assignment
- profile/contact information
- internal authorization edge cases
- external organizations
- external departments
- external contacts
- external contact information
- deletion/reference edge cases
- feature tests

Do not introduce a second organization/user model for external parties.

### Step 2 — Projects Completion Audit
Review and test:
- CRUD
- manager
- members
- default department projects
- permissions
- access boundaries
- deletion/update restrictions
- list/show behavior
- pagination
- edge cases

### V1 Completion Status

V1 business scope is now implementation-complete.

Completed:
- Core identity, users, profiles, departments, roles, permissions and authorization/scopes
- User onboarding and invitation activation
- Custom Fields foundation
- Projects CRUD, lifecycle, managers, members, roles, default projects and pagination
- Tasks CRUD, assignment, referral/history, status/priority, comments, work logs, reminders, filtering/search, pagination and access scopes
- Task review lifecycle: submit, review, approve, return and resubmission
- Task dependencies
- Controlled task attachments backed by the Documents storage abstraction
- Workflow V1
- Unified Cartable V1
- Notifications V1
- Audit V1 core
- Persian RTL task/project/core UI and Jalali date presentation where applicable
- Regression coverage for the implemented V1 behavior

The repository's full test suite passed before the final attachment integration; the dedicated TaskDocumentTest also passes after that integration. A final full-suite run and frontend build are the remaining technical validation steps before manual UI/functional acceptance.

### Post-V1 / Deferred

The following are intentionally outside V1 acceptance and remain deferred:
- Board and Calendar task views
- Gantt, subtasks and recurring tasks
- Visual workflow designer
- Correspondence and other business modules
- Production hardening, deployment and operations work

These are not blockers for V1 functional acceptance.

**Current immediate sequence:** final full-suite validation → frontend build validation → manual visual/functional acceptance of V1.

### Projects Audit Checkpoint — 2026-10-03
### Projects Completion Audit — 2026-10-03

The Projects completion audit reviewed CRUD, manager/member/role boundaries, default projects, nested route binding, pagination, project/task references, department lifecycle, and manager lifecycle.

Confirmed:
- project listing is server-side paginated
- project creation requires an active manager and automatically adds that manager as a project member
- project update/delete and role/member management are restricted to the project manager plus the corresponding permission
- system/default department projects cannot be updated, deleted, or normally managed
- project role operations are constrained to the owning project
- nested role/member routes use scoped bindings
- deleting a project preserves its tasks because tasks.project_id uses null-on-delete
- project deletion and default-project restrictions are covered by existing tests
- project managers are now protected from being deactivated or deleted until project management is reassigned

The project audit found one cross-domain lifecycle edge case: a user who manages a project could previously be made inactive or deleted, leaving the project without an active manager. This was corrected in the Core user lifecycle actions and covered by dedicated tests.

After the full test suite confirms these changes, continue to **Tasks V1 Completion**.

The first Projects completion audit pass reviewed CRUD, pagination, manager/member/role boundaries, nested route binding, default department projects, and project/task references.

Confirmed:
- project index uses server-side pagination
- project manager is automatically added as a member on creation
- inactive users cannot be added as project members
- project roles are scoped to their owning project
- nested project role/member routes use scoped bindings
- system/default department projects cannot be updated, deleted, or have normal role/member management applied
- deleting a project does not delete its tasks; task.project_id is nullable with nullOnDelete
- project schema tests now cover department_id and is_system_default

No architectural change was required in this audit pass; the main work was closing test coverage around the current business rules.

### Core Audit Checkpoint — 2026-10-03
The first Core audit pass identified and corrected:
- internal user contact fields now persist on create/update
- an authenticated user cannot change their own active/inactive status through the edit flow
- external organization deletion is blocked while departments or contacts reference it
- external department deletion is blocked while child departments or contacts reference it
- existing system-role/permission protections remain enforced

After the local full test suite confirms these corrections, continue with the Projects Completion Audit.


## 21. Development Discipline

For every meaningful change:
1. inspect existing implementation
2. identify the root cause/requirement
3. make the smallest coherent architectural change
4. run relevant tests
5. inspect the result
6. update documentation when the decision/status changes
7. keep Git history understandable

Do not:
- bypass authorization
- hide errors with destructive database changes
- add unnecessary dependencies
- introduce business logic only in Vue
- create duplicate concepts when an existing domain model is appropriate

## 22. Current Working Principle

When continuing this project in a new conversation, first inspect the repository state and this document, then continue from the current implementation.

Do not assume old planned phases are still accurate if the repository already contains the implementation. The repository and this document together represent the current working context.


## 23. Authorization Bootstrap Implementation

Implemented:
- system role `SYSTEM_ADMIN`
- system role receives all active permissions
- system administrator permissions use organization scope
- system role is protected from normal permission edits/deletion
- optional `OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL` can assign the system role during database seeding
- `office:bootstrap-admin {email}` provides an explicit and safer operational bootstrap path for an existing active user
- role assignment is already supported through the Users UI
- tests cover system role seeding, bootstrap assignment, inactive-user rejection, and effective permission changes

For the current local user, use the explicit bootstrap command with the intended administrator email rather than hard-coding that email into application code.


## 24. Internal and External Organization Model

The system must explicitly support two separate organizational worlds:

### Internal Organization
- The company's own organization.
- Internal departments/units with hierarchy, managers, memberships, roles, permissions, and authenticated internal users.
- Internal users have application accounts and can log in.
- Internal users may have contact information such as mobile, landline, extension, and email.

### External Organizations
- Customers, suppliers, contractors, partners, authorities, and other third parties.
- External organizations are separate from the internal organization and must not be represented by internal departments/users.
- Each external organization may contain its own departments/units and external people/contacts.
- External people do not have application accounts by default and do not receive internal roles or permissions.
- External people exist primarily so work can be tracked and followed up with the correct person/department/organization.
- External people may have name, position, mobile, landline, extension, email, and other contact details.

### Architectural Rules
- Do not merge internal and external organizations into one generic department/user model.
- Do not make external contacts subclasses of the authenticated User model.
- A future external-account capability may be added separately if external people ever need system login.
- Tasks and projects may reference an external organization, external department, and/or external person when the business flow requires external follow-up.
- Internal responsibility and external follow-up are separate concepts: an internal user can be responsible for a task while an external person is the party being contacted/followed up with.
- Contact data should be modeled so multiple phone/contact methods can be supported without coupling external people to authentication.

### Required Core Roadmap Update
Before continuing advanced Task features, complete the core model and management surfaces for:
1. Internal organization and departments review.
2. Internal user management review.
3. External organization management.
4. External department/unit management.
5. External person/contact management.
6. Contact information and follow-up references.
7. Authorization and tests for internal management; external records remain business data rather than authenticated users.

## 24. Project Index Pagination

Implemented:
- Project index now uses server-side pagination with 15 projects per page.
- Pagination preserves the current query string.
- Project index UI shows the visible range and total project count.
- Previous/next and numbered pagination links are available.
- HTTP coverage verifies the first page contains 15 of 16 projects and reports two pages.

Next project review focus:
- verify project edge cases and authorization behavior
- review project list/show data boundaries
- then proceed to remaining Task V1 completion items


### Projects Completion Audit — Repository Verification — 2026-10-03

The development branch was re-audited from the repository state rather than relying on older checkpoint notes.

Verified as correct:
- ProjectService listing is server-side paginated (15 items) and preserves the query string.
- ProjectMember is backed by the same `project_user` table used by `Project::users()`; there is no duplicate membership table in the current implementation.
- Project manager membership is created automatically and cannot be removed through the member workflow.
- Project roles are scoped to their owning project through nested route model binding.
- Inactive project roles cannot be assigned to members.
- Project roles with members cannot be deleted.
- System/default projects are protected by backend authorization from update, delete, member management, and role management.
- Regular project manager changes are intentionally not supported by Project Update; manager lifecycle is controlled through the existing manager assignment rules.
- User deletion/deactivation is already blocked while the user manages projects, preventing a project from being left without an active manager.
- Project viewing is permission-based and not membership-scoped; this matches the current authorization design, while mutation operations are manager-scoped.
- `status` and `is_active` represent separate lifecycle dimensions and were retained; no evidence justified collapsing them.

Real gaps found and corrected:
- The repository's project schema was missing `department_id` and `is_system_default` even though the current Department/default-project implementation and tests already depended on them. A forward migration now adds both fields; `department_id` is nullable for ordinary projects.
- Project create/update now persist `department_id`, and the UI exposes active departments.
- The project/task relationship did not match the documented rule that Project is optional for Task. The task foreign key was non-null and cascade-deleted tasks with their project. A forward migration now makes `tasks.project_id` nullable and uses `nullOnDelete`, preserving tasks when a project is deleted.
- Department deletion now explicitly removes the Department-owned system default Project as part of the same transaction, while normal Projects survive with a null department reference.
- Project authorization controls were not fully reflected in the UI: the project list always showed Create and the project show page always showed Edit. The UI now receives backend authorization state and hides those actions when unavailable.
- Project Create/Edit used native Gregorian date inputs despite the established Jalali presentation layer. Both flows now use the shared Jalali date picker while continuing to submit Gregorian ISO values.

Additional regression coverage was added for:
- project department persistence
- standalone task creation
- preserving tasks when deleting a project
- deleting a Department removes its system default Project while preserving attached Tasks
- deleting a Department preserves normal Projects and clears their department reference

The audit found no architectural justification for replacing the existing `project_user` membership model, introducing a second membership relation, changing global project view authorization, or collapsing `status` and `is_active`.

Repository note:
- The execution environment did not contain a local checkout, so the requested literal `git pull origin development` could not be executed locally. GitHub repository state on the `development` branch was used for inspection and writes.
- Local PHPUnit and Vite execution could not be performed because the repository could not be cloned into the execution container due unavailable outbound DNS/network access. The code and tests were updated, but runtime test/build results must be verified in the project environment.

Next step remains Tasks V1 Completion after the corrected project/task schema and lifecycle changes pass the full local suite.


### Projects Audit Reconciliation — 2026-10-03

Repository history was rechecked after the audit changes. The development branch already contained the 2026-09-29 migrations that add project department/default-project fields and make task project/assignee context nullable. The audit therefore keeps those existing migrations as the source of truth; duplicate temporary migrations introduced during the review were removed.

The final lifecycle decision is:
- A normal Project may optionally reference a Department.
- Deleting a Department must not delete normal Projects; the Project department reference is now null-on-delete.
- The Department-owned system default Project is explicitly deleted as part of Department deletion.
- Tasks attached to the deleted default Project are preserved because tasks.project_id is null-on-delete, and their department_id is cleared by the existing Department deletion workflow.
- Project manager and creator references are nullable to support system/default projects without authenticated ownership.

This keeps the "default project is not manually deletable" rule while giving Department deletion an explicit, controlled lifecycle rather than relying on an implicit cascade.

The Projects audit therefore requires no duplicate membership model, no new Project access scope, and no collapse of status/is_active.


### Tasks V1 Completion — 2026-10-03

Tasks V1 was re-audited on the development branch after the Projects audit.

Verified:
- Task CRUD, assignment, referral/history, status/priority, comments, work logs, reminders, timeline/activity, filtering/search, pagination, optional project/department, external follow-up, and scoped authorization are implemented.
- Task access scopes combine self, department, department tree, project, and organization scope according to the existing permission model.
- Reminder lifecycle keeps its intentional recipient/creator semantics and is not coupled to generic Task scope checks.
- Timeline timestamps are deterministic through the existing explicit timestamp handling.
- Creating a task directly in completed status now records completed_at consistently with the normal status-change workflow.

Task UX Completion has started with the first concrete correction:
- native Gregorian datetime inputs in the Task Show work-log and reminder forms were replaced with a shared Jalali date-time picker while retaining Gregorian ISO storage/API values.

Next Task UX review:
- review create/edit/show flows
- improve empty/loading/error states where gaps are real
- verify remaining Jalali/RTL/Vazirmatn consistency


Task UX progress:
- Task Show forms now surface backend validation errors for status, reminder time, work-log completion time, and editable task fields instead of failing silently at field level.


## 25. Enterprise Architecture Direction — 2026-10-04

The project direction was reviewed against the intended long-term goal: one company-wide operational automation platform with multiple business modules.

The current Project/Task architecture is not being discarded. It is being promoted into a shared operational backbone.

### Target Backbone

Identity & Organization → Cartable → Workflow → Work Items → Documents → Notifications/Audit

Projects and Tasks remain domain modules, while Workflow, Documents and Cartable become shared capabilities for later modules.

### Critical Domain Decisions

1. Task is a unit of execution; Workflow controls process progression.
2. Assignment and Approval are separate concepts.
3. Task execution state and review state should not be collapsed into one large status enum.
4. Department manager, project manager and other reviewers should be resolved as workflow actors rather than hard-coded per module.
5. Task dependencies must support multiple predecessors and future conditional progression.
6. Documents are first-class records with versions and links, not only task attachments.
7. A document passed from one task to another should be linked/reused, not duplicated.
8. Cartable is a unified operational entry point but does not own business records.
9. Cross-module communication should use contracts/events rather than direct database coupling.
10. Workflow V1 must not start with a visual workflow designer; the domain model comes first.

### Immediate Architectural Sequence

Before adding more isolated business features:

1. Task lifecycle review/approval
2. Task dependencies
3. Document foundation
4. Workflow V1
5. Unified Cartable
6. Notifications
7. Audit expansion
8. Correspondence

The complete module roadmap is maintained in ROADMAP.md.

### Long-Term Module Families

The planned expansion includes:

- Core / Organization
- Projects
- Tasks / Work Items
- Workflow
- Documents / Digital Archive
- Cartable
- Correspondence
- Forms & Requests
- Meetings & Calendar
- Notifications
- Audit
- CRM
- Sales
- Procurement
- Inventory & Assets
- HR
- Reporting / Search / SLA
- Internal Chat
- PBX / Telephony
- External integrations

Accounting is intentionally outside the current product boundary.



---

# Persistent Current Checkpoint — 2026-10-06

## Product Goal

Build a Persian-language, RTL, production-oriented company office-automation platform as a Modular Monolith. The long-term product is broader than Projects/Tasks and is organized into explicit product versions. Shared operational capabilities must be reusable by later modules rather than duplicated.

## Version State

**Current version: V1 — Foundation & Work Management**

V1 implementation scope is complete.

**Current checkpoint:** V1 technical/debugging and manual UI/functional acceptance.

**Next official version:** V2 — Office Automation.

The full V1–V8 version boundaries are maintained in ROADMAP.md.

## Current Runtime Blockers

1. `ExternalPartyController.php` has a PHP parse error around the recent `custom_fields` validation changes and currently breaks `/login` through route discovery/Ziggy.
2. `HasSystemCode.php` uses `updateQuietly()` from the `created` event; on SQL Server this attempts to update the identity `id` column when creating a Position. Fix the code generation at root cause.

## Immediate Working Order

1. Inspect and fix the two runtime defects.
2. Add/adjust regression tests for both.
3. Run one validation step at a time in the user's local environment.
4. Run frontend build after frontend-affecting changes.
5. Perform manual UI/functional acceptance.
6. Update ROADMAP.md and CURRENT_STATUS.md after meaningful changes.
7. Do not start V2 until V1 acceptance closes.

## Working Rules for Future Conversations

- Repository: `mehdijalilimaraghi-del/office-automation`
- Development branch: `development`
- Stable branch: `master`
- Never develop directly on `master`.
- Inspect repository and persistent project documents before changing code.
- Make implementation/debugging changes directly when user approval is not required.
- Assistant edits/creates code and tests in GitHub; user runs tests locally.
- After every code change, provide the exact PowerShell validation command.
- Add `git pull origin development` at the beginning of the validation command only when code changes were made immediately before that validation.
- Prefer one validation step at a time.
- Use standard, production-quality Laravel/PHP/Vue/TypeScript patterns.
- Do not weaken tests or hide errors with destructive changes.
- UI/UX should follow modern Google Material Design principles with Persian RTL constraints.
- Act as a senior developer/architect and implement non-blocking decisions directly.
- Record meaningful completed work, decisions and current blockers in persistent documentation.

## Documentation Authority

1. `ROADMAP.md` — product version and scope authority
2. `CURRENT_STATUS.md` — current implementation/checkpoint authority
3. `PROJECT_CONTEXT.md` — persistent detailed context
4. `ARCHITECTURE.md` — architecture boundaries
5. `PROJECT_SPEC.md` — system specification
6. `DEVELOPMENT.md` — engineering workflow


## Cross-department project structure (2026-10-10)
A project may contain multiple project sections. Each section belongs to one responsible internal department and carries its own description, documents, sort order, and `manager_can_create_tasks` flag. The project administrator may create tasks in any section. A department manager may create tasks only in sections assigned to their department where that flag is enabled. Section tasks are initially assigned to the department manager; an optional employee destination creates a second referral-history entry from manager to employee. New departments must not auto-create separate default projects; instead they get a section in the shared default project. Fresh installations receive one shared organizational default project and sections for existing departments from the seeder; existing per-department defaults are consolidated by a data-preserving migration.


## 2026-10-12 — Authorization composition
Authorization has two additive sources:
1. **Contextual responsibility:** permissions/scopes derived from actual organization, project, membership, manager, assignment, or task relationships.
2. **Application role:** explicit permission grants configured by an administrator, with the selected permission scope constraining the grant.

The effective access is the union of the two sources, not an intersection. For example, a user who is not a project manager may still receive `projects.create` through their application role; a task/project operational permission may also be granted explicitly where its scope is intentionally configured. Backend authorization remains authoritative.

The active `SYSTEM_ADMIN` account is a special system capability and receives every permission with organization scope independent of business responsibilities and the completeness of seeded permission assignments. Inactive users still have no permissions. Permission seeding must preserve operational grants assigned to ordinary roles.

## 2026-10-26 — Current V2 checkpoint update

- Current active version is V2 — Office Automation; V1 is closed. Older statements earlier in this file identifying V1 as current are historical and superseded by ROADMAP.md and CURRENT_STATUS.md.
- Correspondence workflow now records both module-specific action history and Core Audit events for workflow actions. Audit metadata includes action ID and routing actor/target identifiers without duplicating letter body or confidential content.
- The manager-approval HTTP regression test checks that the Core Audit event is persisted.
- Next priority after validation: audit remaining correspondence lifecycle transitions (draft creation/final registration/copy recipients/external dispatch tracking) and verify actionable Cartable + notification integration before starting Forms & Requests.
- Keep work on development; do not change master. Follow the one-step-at-a-time local validation instruction in CURRENT_STATUS.md.


## 2026-10-27 — Cartable TypeScript build fix

- Resolved the frontend build error in `resources/js/Pages/Cartable/Index.vue` by changing the template reference from `currentDepartment` to the declared `current_department` field.
- This is a naming-only fix on `development`; `master` remains unchanged. Production build validation is pending.


## 2026-10-27 — Correspondence numbering requirements (pending)

- The letter's own `شماره نامه` and the secretariat's `شماره ثبت` must be separate concepts and fields.
- The custom letter-number template in correspondence settings must support two or three fields.
- Each department/unit must have a required configurable short code, such as `IT`; the template should use this code rather than the database department ID.
- The correspondence list must show `registered_at` as a localized Persian/Jalali date and Tehran time with the label `ثبت نهایی`, not a raw ISO UTC string.
- All items above are pending. Inspect existing schema and behavior before implementing; retain historical data and add regression tests. `development` only; `master` unchanged.

## 2026-10-27 — Correspondence number separation and department codes (validation pending)

- Added a dedicated letter_number field. New drafts receive a letter number immediately using the organization-configured template; the Secretariat assigns a separate registration_number at final registration (REG + configured separator + sequence). Existing registration numbers are preserved; legacy drafts without a letter number receive one at final registration.
- Added a separate counter table for letter numbers while retaining the existing counter for Secretariat registration numbers.
- Custom letter-number templates now accept two or three distinct components. The settings form exposes a two/three-part selector and persists only the selected components.
- Department creation/editing now requires a unique short alphanumeric code (hyphens/underscores allowed), persists it, and uses it in letter-number templates instead of the database ID. Existing department codes are preserved.
- Correspondence list/detail surfaces display letter number and registration number separately. The list formats registered_at as a Jalali date and Tehran local time and labels it «ثبت نهایی»; search includes both numbers.
- Added/adjusted regression coverage for two-part templates, distinct registration numbers, draft letter-number assignment, and department code persistence.
- Validation pending: pull development, run focused correspondence numbering/service and department tests, then full php artisan test and npm run build. No tests or build have been run by the assistant. master unchanged.

## 2026-10-27 — Correspondence copy recipient UX and workflow additions

- Optional copy recipients are now at the bottom of the creation form and are selected through checkboxes supporting multiple departments.
- The correspondence creation date fields use the shared JalaliDatePicker component to align date input typography with the rest of the Persian RTL UI.
- A department manager can add additional copy recipients while the correspondence is in their department's active workflow, provided the workflow has not completed. Backend authorization is authoritative; the existing workflow service records each newly attached copy in correspondence action history and Core Audit, while duplicate recipients are ignored.
- Added HTTP regression tests for multiple-copy success and non-manager denial. Validation is pending in the user's local environment; no migration is required for this UI/controller change. Development only, master unchanged.

## 2026-10-27 — Related correspondence search and date validation

- The optional correspondence relation field now uses the broader label «در ارتباط با مکاتبات قبلی» and supports server-side search instead of loading a fixed list of the latest 100 letters.
- Search covers letter number, registration number, subject, external tracking registration number, internal department names/codes, and external organization/department names/codes. Result rows show subject, letter number, registration number, letter date, and related party names/codes; results are visibility-filtered and capped at 20.
- Incoming receipt date and outgoing sent date are required in both UI and backend validation.
- HTTP regression tests were added. Local validation pending; no migration required. Changes are on development only; master unchanged.

## 2026-10-27 — Related correspondence selector UI alignment (validation pending)

- Related correspondence and copy-recipient controls both span two columns at the `sm` breakpoint, aligning their widths.
- Minimum search length is communicated inside the related-search input placeholder rather than a separate helper panel. Results appear only after two characters are entered.
- Selected related letters are shown in one removable list, visually aligned with the copy-recipient selection list; redundant nested bordered containers were removed. Backend behavior is unchanged.
- Local validation pending; run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build` after pulling `development`. `master` unchanged.

## 2026-10-27 — Related correspondence dropdown overlay correction (validation pending)

- Restored the outer border and padding of «ارتباط با مکاتبات قبلی» to visually match «رونوشت به».
- Search results use an anchored absolute dropdown with a white background and shadow, so opening results overlays the form rather than pushing selected items and subsequent fields down. Selected letters stay in a separate list below the search field.
- Local validation pending; run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build` after pulling `development`. `master` unchanged.


## 2026-10-27 — Correspondence number policy and secretariat pre-action management

Incoming letter numbers are captured from the original document, never auto-generated. Secretariat registration numbers have a separate configurable template and counter. The secretariat can edit/delete draft correspondence only before any action exists; server-side transactional checks enforce this. Regression tests and local build/test validation are pending.


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

## 2026-10-31 — Correspondence department routing audience

- When correspondence is referred to a department, only that department's manager receives the actionable Cartable item. Ordinary-letter organization-wide read visibility is a separate policy and must not imply workflow-action rights.
- After the manager explicitly refers the letter to an employee, only the assigned employee may submit the result; enforce this in both the backend workflow service and frontend action availability. The manager retains their manager workflow capability.
- Cartable must not include all current-department members as actionable recipients. Include manager-owned correspondence and explicitly assigned employee states.
- Manager's action label is «ارجاع به»; destination options are «واحد دیگر» and «کارمند همین واحد».
- Shared DepartmentTreeSelect positioning was hardened for viewport edges and nested scrolling. Validation is pending; branch `development`, `master` unchanged.


## 2026-10-31 — Correspondence personal ownership and action filters

- Added a second independent filter to the correspondence list: «همه مکاتبات»، «نیاز به اقدام»، and «اقدام قبلی ثبت‌شده توسط من». It composes with the existing «مکاتبات من / مکاتبات واحد من / همه مکاتبات مجاز» scope and preserves other search filters and pagination query parameters.
- «نیاز به اقدام» is based on the current assignment (current_user_id equals the signed-in user). «اقدام قبلی ثبت‌شده توسط من» requires both a historical referral addressed to that user (correspondence_actions.to_user_id) and an action authored by that user (actor_id), and excludes letters currently assigned back to that user so pending work takes precedence.
- Corrected the «مکاتبات من» ownership scope: an action authored by the user is no longer sufficient by itself. Historical referrals addressed to the user remain included; merely referring a letter to another user/department does not make it the referrer's personal correspondence.
- Added feature regression coverage for pending referrals, previously acted-on referrals, and letters merely referred onward by the current user. No database migration or workflow authorization changes.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master remains unchanged.


## 2026-10-31 — Correspondence list action-filter semantics

- The correspondence list now has an independent second filter, defaulting to «نیاز به اقدام», alongside the existing personal/department/all-visible scope selector.
- «نیاز به اقدام» means the latest referral addressed to the current user remains the current assignment and no action by that user exists after that referral. «اقدام قبلی ثبت‌شده توسط من» means an action by the user exists after their latest referral; a newer referral resets the item to pending.
- «ثبت‌شده توسط من» includes correspondence created by the user or finally registered by the user. «همه مکاتبات» preserves the current personal-relevance set rather than broadening access beyond the existing visibility policy.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master unchanged.


## 2026-10-31 — Draft visibility test and independent correspondence filters

- The draft visibility regression test now explicitly selects `action_filter=all` in addition to `scope=all`, because the default second filter is `needs_action`. This preserves the intended test of creator/secretariat draft visibility without conflating it with the action filter.
- No production authorization or visibility logic changed. Focused correspondence test rerun pending; `master` unchanged.


## 2026-10-31 — Multi-department manager pending correspondence correction

- The default «نیاز به اقدام» filter now recognizes department-level referrals for every department managed by the signed-in user, not only referrals whose to_user_id is set to that user.
- The department path relies on current department manager ownership and the latest routing action to that department. It includes active manager states, excludes explicit employee assignment, and excludes a manager's own later action after the latest department routing.
- The personal scope also includes active correspondence in managed departments. Ordinary membership alone does not qualify.
- Added a focused regression test for a user managing multiple departments. Validation pending on the project owner's environment; no frontend change; master unchanged.


## 2026-10-31 — Correspondence index closure fix (validation pending)

- The `scope=mine` closure in `CorrespondenceController::index()` now captures `$managedDepartmentIds` because its query references that collection to include active correspondence in departments managed by the user.
- The missing capture caused `Undefined variable $managedDepartmentIds` and HTTP 500 responses across correspondence index tests. No workflow authorization or filter behavior was otherwise changed.
- Validation pending: `php artisan test --filter=CorrespondenceHttpTest`. PHP-only change; no frontend build required. `master` unchanged.

## 2026-10-31 — Nested correspondence scope closure correction

- Corrected the inner `where(function ($mine) ...)` closure in `CorrespondenceController::index()` to capture `$managedDepartmentIds` as well. The earlier fix captured the variable only in the surrounding `when(scope=mine)` closure; PHP closures do not implicitly inherit variables from an enclosing closure, so the nested query still raised `Undefined variable $managedDepartmentIds` at the `orWhereIn` condition.
- No query semantics or authorization rules changed. Validation pending: pull `development` and run `php artisan test --filter=CorrespondenceHttpTest`. PHP/documentation-only change; no frontend build required. `master` unchanged.


## 2026-10-31 — Correspondence list filter interaction

- The action filter is shown only for the `mine` scope. For `department` and `all`, it is normalized to `all` server-side and does not narrow the result set.
- Renamed the action option to «اقدام قبلی ثبت‌شده» (removed redundant «توسط من» wording).
- All select filters now apply automatically; text search applies after a 350 ms debounce. Removed the manual «اعمال فیلتر» button.
- Added a regression assertion that `scope=department` ignores `action_filter=needs_action` and returns the full department-scope set. Validation pending: `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`. `master` unchanged.

## 2026-10-31 — Project-requested correspondence in manager action list

- Included `project_requested` in the actionable workflow states for the manager of the current department. A letter routed to a project department must appear in that department manager's `needs_action` list until it is assigned to an employee or transitions to another state.
- Added a Correspondence HTTP regression test for a registered incoming letter in `project_requested`, with `current_user_id = NULL`, confirming it appears in the current manager's default list. Existing access filtering and the `assigned_to_employee` exclusion are unchanged.
- Validation pending: run `php artisan test --filter=CorrespondenceHttpTest`; `master` unchanged.


## 2026-11-01 — Task-to-correspondence entry point and project workflow correction

- Added an «ایجاد نامه جدید» entry point on the task details page for the current task assignee when the user also has correspondence-create permission. The correspondence composition form receives the task context, validates the task reference, authorizes the current assignee, and stores the new correspondence `task_id` link.
- Removed the legacy `project_requested` workflow state from the manager `needs_action` filter. A state label alone must not represent a completed project-request process; the project request flow must be implemented with explicit actions and project creation/linkage. Updated the regression test to assert that the legacy state is not actionable.
- This is an incremental first slice only. The project-specific approval flow, project creation with a selected response department/section/task, and source-letter linkage are not yet implemented.
- Validation has not been run in this editing environment. Pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then run `npm run build` before accepting this slice. `master` unchanged.


## 2026-11-01 — Project request, response ownership and task-correspondence links

- The authoritative functional specification and implementation-stage matrix are in `CORRESPONDENCE_PROJECT_WORKFLOW.md`.
- Project Management employee actions are requests only; all four require feedback. The PM manager makes a separate final decision. Manager rejection of an employee's requested action returns it to the same PM employee; approval of reject/return routes the letter to the origin; approval of referral routes to the selected department; approval of project request enables project creation.
- Each created project selects its response department at creation time. Organization settings may provide an editable default only. Create the response section and response task only for the selected department inside the new project; do not auto-create an origin-department section/task.
- The response task links to the new project and source incoming correspondence. A response letter created from the task must link to the task, project and source correspondence. Task/correspondence navigation must work in both directions.
- Actual implementation checkpoint (2026-11-01): task detail already offers «ایجاد نامه جدید» to the current assignee with correspondence-create permission, and backend stores the task relation. The project-specific request/approval flow, per-project response department, response section/task creation, source-letter/project propagation and bidirectional detail links remain incomplete. Do not describe them as implemented.
- Stage-by-stage status and acceptance criteria are maintained in the reference document. `master` remains unchanged.


## 2026-11-01 — Project Management request workflow implementation checkpoint

- Initial stage-1 implementation exists on `development`: four employee request actions, mandatory feedback, separate manager approval/rejection, manager cartable visibility, and UI controls.
- Employee requests do not route or create a project by themselves. Manager rejection returns to the same employee; manager approval performs the requested referral/return or moves to `project_request_approved`.
- Two focused HTTP regression tests have been added; local test and frontend build validation are pending. Stage 2 project creation and response Section/Task provisioning are not implemented yet.


## 2026-11-01 — Owner validation confirmation

- The project owner confirmed the Project Management request/review focused tests passed and frontend build succeeded. Stage 1 is accepted.
- Stage 2 remains unimplemented: create a project from an approved source incoming letter, choose the response department in the creation form (organization default is only a prefill and can be changed), and link the new project to the source letter. Stage 3 response Section/task and Stage 4 bidirectional links remain pending.


## 2026-11-01 — Initial approved-project creation slice

- Stage 1 is accepted per owner confirmation (focused tests passed; frontend build succeeded).
- New approved-project correspondence action creates a project with one response-department Section and one response task, links the source correspondence to both, and records a project_created action. The UI exposes explicit response-department selection.
- This slice is not validated locally. Organization-level default response department is now configurable in Organization Settings and pre-fills the correspondence project form; user can change it per project. Bidirectional project/task/correspondence detail links remain pending; response letters created from a linked task now inherit the project and source-letter reply link (when visible to the creator).


## 2026-11-01 — تأیید build و تست مکاتبات برای مرحله ۴

- مالک پروژه اعلام کرد پس از دریافت آخرین تغییرات مرحله ۴، دستور `php artisan test --filter=CorrespondenceHttpTest` پاس شده و `npm run build` نیز موفق بوده است.
- این تأیید، صحت تست‌های موجود مکاتبات و build فرانت‌اند را پوشش می‌دهد؛ اما به معنی اجرای تست اختصاصی کنترل محرمانگی در صفحات جزئیات پروژه/تسک، تست مسیر ایجاد پروژه یا اعتبارسنجی migration روی SQL Server نیست.
- مرحله ۴ از نظر پیاده‌سازی نمایش ارتباط نامه مبدأ در جزئیات پروژه و مکاتبات مرتبط در جزئیات تسک، و انتقال ارتباط پروژه/نامه مبدأ به نامه پاسخ، پیاده‌سازی شده است؛ **اعتبارسنجی اختصاصی روابط و محرمانگی هنوز در انتظار است**.
- مراحل ۲ و ۳ نیز همچنان به تست مسیر ایجاد پروژه، انتخاب واحد پاسخ‌گو، ساخت Section/Task و اعتبارسنجی SQL Server نیاز دارند. مرحله ۵ (سناریوی انتها‌به‌انتها و پذیرش دستی) باز است.
- شاخه `development`؛ `master` بدون تغییر.


## 2026-11-01 — General incoming correspondence workflow implementation

- Authoritative behavior is documented in `CORRESPONDENCE_GENERAL_WORKFLOW.md`; keep it separate from `CORRESPONDENCE_PROJECT_WORKFLOW.md`.
- General employee requests (none/letter/workflow/project), mandatory feedback, manager review, manager rejection to the same employee, and manager referral of project requests to the configured active Project Management department have an initial implementation on `development`.
- Manager task instructions reuse the existing Tasks form in the organization's general project and the matching department Section. Correspondence context is passed through the form; the backend validates it and stores the task-to-letter relation.
- Manager-side execution of letter requests and real workflow-request execution remain incomplete. New focused tests were added but not run; task-link tests, full feature tests, frontend build and SQL Server validation remain pending.
- `master` unchanged.


## 2026-11-01 — General correspondence workflow expanded

- Letter request supports manager-led letter creation or assignment of draft preparation to the same employee; draft is linked to the source correspondence and returned to manager review.
- Workflow request uses the existing `WorkflowDefinition` and `WorkflowService` to create a real instance and its task steps, linked by `correspondences.workflow_instance_id`.
- Manager task instruction reuses the current Tasks create form in the organization's general project and the current department Section; it persists `task_id` on the source letter.
- Focused Feature tests were added for request/rejection, letter draft delegation and real workflow start, but not executed. Build, task-link integration tests, full regression and SQL Server validation remain pending.


- Workflow instance task links are bidirectional: correspondence detail lists authorized workflow tasks, and task detail resolves the source correspondence through the workflow instance with correspondence visibility filtering.


- Workflow completion propagates `workflow_completed` to linked correspondence. Confidential letter visibility recognizes users assigned to or creating its direct task or workflow-instance tasks; Feature coverage was added but not run.


- Final registration of a requested reply closes the source request as `letter_registered`; completed workflow/reply states are blocked from further workflow actions.


- Feature tests now cover correspondence-linked general task creation, general employee requests/rejection, delegated letter draft/final registration, workflow start/completion, and confidential workflow-task access. Tests have not been run; build and SQL Server validation remain pending.


- Department managers can see the shared general project and their department Section in the existing task form, including with Project-scoped `tasks.create`. Integration test added, not run.


- The source-letter manager can view the delegated employee draft only through the reply relationship while the source is awaiting manager review; unrelated draft privacy remains enforced. Test added, not run.


## 2026-11-01 — Final general correspondence implementation checkpoint

- Documented actual state mapping and final behavior in `CORRESPONDENCE_GENERAL_WORKFLOW.md`.
- Reply drafts remain in the source department, source manager can view them via reply relation, and the source request closes only after the exact linked reply is finally registered.
- Workflow choices exclude definitions with no active steps or project-only/unsupported actors; correspondence workflow runs without a project.
- General project/department Section options are available to department managers in the existing task form with Project-scoped create permission. Task/workflow links are bidirectional and access-filtered.
- Code/docs are on `development`; tests/build not run; `master` unchanged.

## 2026-11-01 — Fix incoming correspondence service test fixture

- `CorrespondenceService::createDraft()` intentionally requires an incoming letter's original `letter_number`; the failing test created an incoming draft without that required field. Updated the test fixture to provide `IN-1405-001` and assert that exact source number is persisted. Production validation is unchanged.
- User-reported suite result before this fix: 1 failed, 454 passed (1,921 assertions). The fix has been committed to `development`; rerun the targeted test and full suite to verify. No claim that tests were run by the assistant. `master` unchanged.

## 2026-11-01 — Fix manager access to shared task project form

- Root cause: `TaskController@index` only supplies `formOptions` when `TaskPolicy::create($user, null)` succeeds. Project-scoped department managers were denied at the null-project check even though `TaskFormOptionsService` deliberately exposes the shared default project and their enabled department sections.
- Updated `TaskPolicy::create`: project-scoped managers can open the task form only when they manage an active department with `manager_can_create_tasks=true` in the active shared system-default project. For a specified project ID, creation is permitted only for that shared default project under the same department-section condition; all other project scope checks remain unchanged. Correspondence store still validates the exact selected section against the correspondence's current department.
- Added regression coverage via the existing `manager creates general task from correspondence and links it` feature test. User-reported pre-fix result: 1 failed, 459 passed (2,002 assertions). Assistant has not executed tests/build; verification remains pending. `master` unchanged.


## به‌روزرسانی قرارداد گردش مکاتبات — 2026-11-01

- درخواست کارمند برای «دستورکار» یک نوع مستقل از درخواست «گردش کار» است. درخواست دستورکار به‌تنهایی Task نمی‌سازد؛ مدیر آن را بررسی می‌کند و با اقدام manager_create_task، مسیر فرم فعلی Tasks در پروژه عمومی سازمان و Section همان واحد فعال می‌شود.
- general_review_pending_manager باید در فیلتر نیازمند اقدام مدیر واحد محاسبه شود.
- ارجاع مدیر به کارمند تا پیش از اقدام کارمند قابل پس گرفتن است. پس گرفتن باید دلیل، actor، مقصد قبلی و ارجاع مبنا را در تاریخچه ثبت کند و مالکیت را به مدیر برگرداند. ارجاع مجدد مستقیم در وضعیت assigned_to_employee مجاز نیست.
- درخواست پروژه در واحد مدیریت پروژه‌ها: مدیر می‌تواند کارمند را در همان واحد مسئول بررسی کند؛ انتقال به واحد دیگر فقط پس از ثبت درخواست ارجاع توسط کارمند پروژه‌ها و تأیید مدیر انجام می‌شود، نه با ارجاع عمومی مدیر.
- تست‌های این قرارداد اضافه شده‌اند؛ اجرای تست، build و اعتبارسنجی SQL Server هنوز تأیید نشده است.
- «مکاتبات من» برای مدیر واحد شامل نامه‌هایی است که خود مدیر یا اعضای واحد تحت مدیریت او ثبت کرده‌اند؛ این قاعده برای پیگیری سابقه ثبت است و نباید با مالکیت اقدام فعلی اشتباه شود. «نیاز به اقدام» همچنان بر اساس مسئول فعلی نامه تعیین می‌شود.
- اقدام پذیرش درخواست دستورکار باید پیش از تغییر state، وجود پروژه عمومی سازمان و Section مجاز واحد و مجوزهای مشاهده/ایجاد Task مدیر را کنترل کند؛ اگر مقصد آماده نباشد، درخواست در انتظار تصمیم می‌ماند و به state غیرقابل‌اقدام منتقل نمی‌شود.
- مدیر مدیریت پروژه‌ها در ارجاع عمومی فقط می‌تواند به کارمند همان واحد ارجاع دهد؛ مقصد بین‌واحدی تابع درخواست کارمند پروژه‌ها و تأیید مدیر است.

## 2026-11-02 — Incoming approval and project creation action ownership

- The incoming approval setting is now honored at draft creation. When set to approval-required, the draft goes to the configured Secretariat department manager in `awaiting_manager_approval`; direct registration is not allowed until that manager approves. After approval, the draft is assigned back to its creator for final Secretariat registration.
- Manager visibility for assigned approval drafts and the default UI action (`manager_approve`) were corrected. The numbering service checks approval for all correspondence types, including incoming.
- The `needs_action` filter now treats `project_request_approved` as an actionable state for the current department manager, fixing the missing post-approval project-creation item.
- Added/updated Feature tests. Test suite, build and SQL Server validation have **not** been run here and remain pending. Branch `development`; `master` unchanged.
