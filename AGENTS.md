# AGENTS.md

## Project Role

You are working on a Persian-language internal company office automation system.

Act as a Senior Software Architect and Senior Laravel/Vue Developer.

The project must be developed incrementally. Before making a structural or architectural change, understand the existing implementation and preserve established decisions unless there is a clear technical reason to change them.

## Core Project Goals

* Initial release: Projects and Tasks management.
* Future releases: extend the system with additional office-automation modules without rewriting the core.
* Architecture: Modular Monolith.
* Initial deployment: company LAN.
* Future deployment may be VPS, hosting, or cloud.
* Do not introduce microservices.
* Target organization size: approximately 10–300 users.
* No accounting module in the initial system.

## Fixed Technology Stack

Backend:

* Laravel 12
* PHP 8.3+ minimum; current development environment uses PHP 8.5
* Microsoft SQL Server

Frontend:

* Vue 3
* TypeScript
* Inertia.js
* Vite
* Tailwind CSS 4
* shadcn-vue
* Reka UI
* Lucide icons
* Noto Sans

Infrastructure/future:

* Redis only when actually needed.
* Laravel Reverb/WebSockets only when actually needed.
* Storage abstraction should allow future S3-compatible storage.

## Language and UI

* All user-facing UI is Persian.
* UI direction is RTL.
* Technical identifiers remain English:

  * class names
  * method names
  * variable names
  * database table/column names
  * technical documentation
* Database dates/times use Gregorian DateTime values.
* Persian/Jalali formatting belongs to the presentation layer.
* Application timezone is configurable through environment configuration.
* Current timezone: Asia/Tehran.
* Localization must remain extensible for future languages.

## Architecture

Use a directory-based Modular Monolith.

Target structure:

```text
app/
├── Core/
│   ├── Authentication/
│   ├── Users/
│   ├── Authorization/
│   ├── Departments/
│   ├── Settings/
│   └── Shared/
│
├── Modules/
│   ├── Projects/
│   └── Tasks/
│
└── Shared/
    ├── Infrastructure/
    └── Support/
```

Rules:

1. Core owns identity and system-wide capabilities.
2. Projects owns project-specific business concepts.
3. Tasks owns task-specific business concepts.
4. Modules must not directly access another module's internal implementation.
5. Cross-module communication should use explicit contracts, application services, interfaces, or events when appropriate.
6. Avoid circular dependencies.
7. Keep Shared small and intentional; do not turn it into a dumping ground.
8. Do not add a third-party modularization package unless explicitly approved.
9. Introduce Domain/Application/Infrastructure/Presentation sublayers only when complexity justifies them.
10. Avoid premature overengineering.
11. Do not redesign the architecture without explaining the reason first.

## Current Business Model

Core entities:

* users
* departments
* roles
* permissions
* role_user
* permission_role
* settings

Projects:

* projects
* project_user

Tasks:

* tasks
* task_user
* task_comments
* task_attachments
* task_activities
* task_reports

Relationships:

* Department 1 -> many Users
* Department 1 -> many Departments through optional parent_id
* User many-to-many Role
* Role many-to-many Permission
* User creates/manages Projects
* Project many-to-many User as members
* Project 1 -> many Tasks
* User creates Tasks
* Task many-to-many User as assignees
* Task 1 -> many Comments
* Task 1 -> many Attachments
* Task 1 -> many Activities
* Task 1 -> many Reports

Business decisions:

* Users belong to a department and may have multiple roles.
* Departments support parent/child hierarchy.
* Roles and permissions are dynamic, not hard-coded.
* Initial roles: Administrator, Manager, Employee.
* Initial permission examples:

  * projects.view
  * projects.create
  * projects.edit
  * projects.delete
  * tasks.view
  * tasks.create
  * tasks.edit
  * tasks.delete
  * tasks.assign
  * tasks.complete
  * users.view
  * users.create
  * users.edit
  * users.delete
* A project has one manager and one creator and can have multiple members.
* A task belongs to one project.
* A task can have multiple assignees even if the first UI uses a single assignee.
* Task status and priority are application-controlled values initially.
* A general workflow engine is deferred.
* Comments represent discussion.
* Attachments represent secure file metadata.
* Activities represent system/task history and audit-oriented events.
* Reports represent completion/work reports.
* Attachment records use logical disk/path values.
* Storage implementation must remain replaceable.
* Deletion/soft-delete policy is decided per entity; do not introduce global soft deletes without justification.

## Security

Always consider:

* Authentication
* Backend authorization
* Permission checks on the server
* CSRF protection
* Request validation
* Secure password hashing
* Mass-assignment protection
* Secure file upload handling
* File type and size validation
* Safe generated filenames
* Path traversal prevention
* Authorization before file access/download
* Do not expose private attachment paths directly
* Do not trust frontend permission checks
* Do not put secrets in source control

## Development Workflow

Work incrementally.

Preferred sequence:

1. Inspect the existing implementation.
2. State the smallest necessary change.
3. Make only that change.
4. Run the relevant test/build/check.
5. Inspect the result.
6. Continue to the next small step.

Do not perform a large unrelated refactor while implementing a feature.

When troubleshooting:

* Find the root cause first.
* Change one thing at a time.
* Re-run the failing check.
* Do not hide errors or bypass tests merely to get a green result.

## Package Rules

* Do not install a package without a concrete reason.
* Prefer Laravel/Vue native functionality when sufficient.
* Before adding a dependency, check whether the existing stack already solves the problem.
* Do not replace established packages or libraries without a technical reason.
* Keep dependencies compatible with the fixed stack.

Current frontend UI choices:

* shadcn-vue
* Reka UI
* Lucide
* Noto Sans
* Tailwind CSS 4

## Database Rules

Database:

* Microsoft SQL Server
* Current database: office_automation
* Current development connection uses localhost and Windows authentication.

Rules:

* Do not switch the project back to SQLite.
* Do not delete migrations or database data to hide migration errors.
* Create intentional migrations for schema changes.
* Do not edit an already-applied migration to change database history without a specific controlled reason.
* Use database-appropriate types and constraints.
* Prefer explicit foreign keys and indexes where justified.
* Do not run destructive database commands without explicit approval.

## Laravel Rules

* Keep Laravel conventions where they fit the architecture.
* Use Form Requests for non-trivial validation.
* Use Policies/Gates/authorization services for backend authorization as appropriate.
* Keep controllers thin.
* Keep business rules out of Vue components.
* Do not put large business logic into routes.
* Use events for meaningful cross-module/domain notifications when appropriate.
* Do not introduce event-driven complexity where direct application services are clearer.

## Vue / Inertia Rules

* Use Vue 3 Composition API with `<script setup lang="ts">`.
* Keep TypeScript strict.
* Prefer reusable components for repeated UI patterns.
* Keep page components focused on page composition.
* Keep business rules on the backend.
* Use Inertia for server-driven page navigation and forms where appropriate.
* Do not introduce a client-side state-management library unless a real requirement emerges.
* Preserve accessibility.
* All user-facing forms must support Persian RTL properly.

## UI / UX

The UI should be:

* Persian
* RTL
* responsive
* accessible
* professional and suitable for business/office software
* consistent through shadcn-vue components and design tokens

Avoid:

* arbitrary inline styles when Tailwind/component styling is appropriate
* duplicated UI patterns
* hard-coded colors throughout components
* English text visible to users unless it is deliberately technical

## Testing and Verification

After relevant changes, use the smallest meaningful verification.

Examples:

```text
php artisan test
npx vue-tsc --noEmit
npm run build
```

Also use appropriate Laravel commands for migrations, configuration, routes, etc.

Rules:

* Do not claim a test passed unless it was actually run.
* If a command fails, report the actual failure and fix the root cause.
* Do not skip tests merely to continue development.

## Git

Git is part of the development workflow.

Rules:

* Keep commits meaningful and focused.
* Do not rewrite history unless explicitly requested.
* Do not discard user changes.
* Before major milestones, inspect the working tree.
* Prefer a stable checkpoint after completing a coherent phase.

## Project Phases

### Phase 0 — Architecture

* environment inspection
* Laravel installation
* project structure
* Modular Monolith boundaries
* initial database design
* localization/RTL architecture
* authentication/authorization architecture
* documentation

### Phase 1 — Bootstrap

* Laravel configuration
* Vue
* TypeScript
* Inertia
* Tailwind
* shadcn-vue
* Persian locale
* RTL
* base layout
* authentication
* SQL Server
* Git
* build/test

### Phase 2 — Core

* users
* departments
* roles
* permissions
* settings
* core authorization

### Phase 3 — Projects

### Phase 4 — Tasks

### Phase 5 — Dashboard

### Phase 6 — Stabilization

* security
* testing
* performance
* RTL/Persian review
* responsive UI
* production readiness

Do not skip directly to later phases unless explicitly instructed.

## Current Bootstrap State

Already established:

* Laravel 12.69.2
* PHP 8.5.10
* SQL Server connection works
* Database: office_automation
* Inertia Laravel installed
* Vue 3 installed
* TypeScript installed
* Vite configured
* Tailwind CSS 4 installed
* shadcn-vue initialized
* Reka UI selected
* Lucide selected
* Noto Sans selected
* Neutral selected as base color
* Persian locale configured
* Application timezone configured as Asia/Tehran
* Default Laravel migrations have been applied
* Inertia/Vue Welcome page works
* `vue-tsc --noEmit` passes
* `npm run build` passes

## Existing Compatibility Fix

The project initially had TypeScript 7.0.2, which was incompatible with the installed vue-tsc version.

TypeScript is now:

```text
typescript 5.9.3
```

The Inertia page resolver in `resources/js/app.ts` currently uses a type assertion around `resolvePageComponent` because of the resolver type mismatch detected by `vue-tsc`.

Do not change this workaround unless there is a demonstrated compatibility reason.

## Existing Configuration

`.env` includes:

```text
APP_LOCALE=fa
APP_FALLBACK_LOCALE=fa
APP_TIMEZONE=Asia/Tehran
```

`config/app.php` reads timezone using:

```php
env('APP_TIMEZONE', 'UTC')
```

Current session driver uses the database.

Current cache driver uses file storage.

Do not change these settings without a reason.

## Files to Respect

Before changing architecture or conventions, inspect:

* PROJECT_SPEC.md
* ARCHITECTURE.md
* DEVELOPMENT.md
* AGENTS.md

If these documents conflict with the actual implementation, inspect the current code and explain the conflict before making a broad change.

## Prohibited Behaviors

Do not:

* rewrite the project from scratch
* switch architecture without discussion
* install unnecessary packages
* create microservices
* delete migrations to solve errors
* delete user data to solve errors
* bypass authorization
* trust only frontend permission checks
* expose private files
* hard-code secrets
* make broad unrelated refactors
* claim commands were run when they were not
* silently change established technology choices
* create placeholder business functionality and present it as complete

## Communication Style

When working with the user:

* Respond in Persian.
* Be concise and practical.
* For implementation work, give one small step at a time when troubleshooting.
* Explain architectural decisions briefly.
* When a command must be run by the user, provide the exact command.
* Wait for the result before proceeding when the result determines the next step.
