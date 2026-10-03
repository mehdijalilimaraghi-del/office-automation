# Project Context

This document is the persistent working context for the Office Automation project. It records architectural decisions, current implementation status, conventions, and the recommended next path so future development can continue without re-discovering the project.

## 1. Project Goal

Build a Persian-language company office-automation system as a **Modular Monolith** from the beginning.

Version 1 focuses on:
- Projects
- Task management

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

Future modules may include:
- Contacts / External Parties
- Documents / Attachments
- Notifications
- Internal Chat
- Asterisk integration
- Settings
- Audit / Activity
- Other business modules

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

## 7. Default Department Project

Every department automatically receives a generic system/default project.

Purpose:
- day-to-day department work
- a fallback project for department-level work

Important:
- Project remains optional on Task.
- A task is not required to have a project.
- Real projects may involve multiple departments.
- Do not enforce project.department_id == task.department_id as a universal rule.

Default project characteristics:
- system default
- active
- named similar to «پروژه عمومی {department}»
- code similar to DEPT-{id}-DEFAULT
- manager follows department manager
- manager is a member

Default projects:
- can be viewed
- cannot be deleted
- cannot be edited as normal projects
- cannot have normal role/member management changes

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

## 20. Current Development Priority

Do not continue polishing the UI before authorization is operational.

Recommended order:

### Step 1 — Authorization / Core Access
Complete:
- Roles
- Permissions
- Permission scopes
- User-role assignment
- Effective permission evaluation
- Default/system roles
- Initial permission grants
- Authorization tests

Fix the current Tasks 403 through the proper role/permission mechanism.

### Step 2 — Complete Core
Review and complete:
- users
- departments
- roles
- permissions
- settings
- profile/access management

### Step 3 — Projects Feature Completion
Review:
- CRUD
- manager
- members
- permissions
- scopes
- project view
- edge cases
- tests

### Step 4 — Tasks Feature Completion
Review:
- CRUD
- assignment
- referral
- status
- priority
- comments
- work logs
- reminders
- history
- filtering
- pagination
- My Tasks
- validation
- authorization

### Step 5 — Attachments
Introduce attachments cleanly, keeping the architecture ready for a future Documents module.

### Step 6 — Persian/Jalali Localization
Review:
- Jalali display
- date inputs
- localized labels/messages
- RTL consistency

### Step 7 — UI Design System
After functionality is stable:
- reusable components
- consistent spacing
- states
- tables
- forms
- responsive behavior
- accessibility

### Step 8 — Audit and Notifications
Add:
- general activity/audit history
- notifications
- reminder delivery

### Step 9 — Security / Integration Testing
Test real role/user combinations and permission scopes end-to-end.

### Step 10 — Deployment / Operations
Prepare:
- production configuration
- backup
- restore
- logging
- queue
- cache
- deployment procedure
- LAN production checklist

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
