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

Dashboard is implemented.

Current statistics:
- My Tasks
- Today's Tasks
- Overdue Tasks
- Active Projects
- Completed Tasks

Recent tasks:
- latest visible tasks
- project and assignee information

Dashboard respects TaskAccessService.

Dashboard tests have passed.

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
A project may contain multiple project sections. Each section belongs to one responsible internal department and carries its own description, documents, sort order, and `manager_can_create_tasks` flag. The project administrator may create tasks in any section. A department manager may create tasks only in sections assigned to their department where that flag is enabled. Section tasks are initially assigned to the department manager; an optional employee destination creates a second referral-history entry from manager to employee. New departments must not auto-create separate default projects. Fresh installations receive one shared organizational default project from the database seeder; existing per-department defaults are consolidated by a data-preserving migration.
