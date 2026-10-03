# Office Automation System  Project Specification

## 1. Project Overview

Office Automation is a web-based internal company automation system.

The initial release focuses on project and task management. The system is designed as a Modular Monolith so additional business modules can be introduced later without rewriting the application core.

Initial deployment target:
- Company LAN
- Windows development environment
- Microsoft SQL Server

Future deployment targets may include:
- VPS
- Dedicated server
- Cloud
- S3-compatible object storage

---

## 2. Technology Stack

### Backend
- Laravel 12
- PHP 8.3+ required
- Current development PHP: 8.5.x

### Frontend
- Vue 3
- TypeScript
- Inertia.js
- Tailwind CSS
- shadcn-vue
- Vite

### Database
- Microsoft SQL Server

### Optional/Future Infrastructure
- Redis
- Laravel Queue
- Laravel Reverb / WebSocket
- S3-compatible object storage

### Development
- Git
- PHPUnit / Laravel testing tools

---

## 3. Language and Localization

The initial user interface language is Persian.

The application must support RTL from the beginning.

All user-facing text must be localized, including:
- Authentication
- Navigation
- Forms
- Buttons
- Validation messages
- Errors
- Notifications
- Empty states
- Projects
- Tasks
- Users
- Permissions
- Confirmations
- Administrative pages

Internal programming identifiers remain English.

Localization architecture must allow additional languages to be added later.

Dates and timestamps are stored using Gregorian/standard database-compatible DateTime values.

The UI must be designed so Jalali/Shamsi presentation can be introduced without changing stored database values.

Timezone must be configurable.

---

## 4. Initial Roles

The initial roles are:

- Administrator
- Manager
- Employee

Roles are not hard-coded into authorization logic.

Permissions must be dynamic and extensible.

Examples:

projects.view
projects.create
projects.edit
projects.delete

tasks.view
tasks.create
tasks.edit
tasks.delete
tasks.assign
tasks.complete

users.view
users.create
users.edit
users.delete

---

## 5. Initial Modules

### Core

Responsible for system-wide capabilities:

- Authentication
- Users
- User Profile
- Roles
- Permissions
- Departments
- Settings

### Projects

Responsible for:

- Project CRUD
- Project name
- Description
- Status
- Project manager
- Members
- Start date
- End date
- Project view
- Optional department association
- Department-owned system default projects that cannot be manually updated or deleted

### Tasks

Responsible for:

- Task CRUD
- Project relation
- Assignment
- Status
- Priority
- Due date
- Description
- Comments
- Attachments
- Activity/history
- Completion/report

### Dashboard

Initial dashboard capabilities:

- My Tasks
- Today's Tasks
- Overdue Tasks
- Active Projects
- Completed Tasks
- Basic statistics

---

## 6. Future Modules

The architecture must allow future modules such as:

- Workflow
- Documents
- Correspondence
- Internal Chat
- Notifications
- HR
- Attendance
- Leave
- CRM
- Customers
- Sales
- Purchasing
- Inventory
- Assets
- Finance
- Reports

These modules are not part of the initial implementation.

---

## 7. Security Requirements

The system must implement:

- Authentication
- Authorization
- Backend permission checks
- CSRF protection
- Request validation
- Secure password hashing
- Mass-assignment protection
- Secure file upload
- File type validation
- File size validation
- Safe generated filenames
- Path traversal protection
- File access authorization

Attachments must be stored behind an abstraction that allows future migration to S3-compatible storage.

---

## 8. Development Principles

The project must remain:

- Modular
- Maintainable
- Testable
- Secure
- Extensible
- Simple enough to maintain

Avoid:

- God classes
- Huge controllers
- Huge Vue components
- Duplicate logic
- Unnecessary dependencies
- Premature abstraction
- Overengineering

Controllers should remain thin.

Services, Actions, Domain services, and Application services should only be introduced when they provide meaningful separation or business value.

---

## 9. Development Method

Development is incremental.

No large feature set should be implemented in one step.

Each phase must:

1. Inspect the existing state.
2. Define the intended change.
3. Implement the smallest meaningful change.
4. Run appropriate tests.
5. Verify the result.
6. Update documentation when architecture changes.
7. Stop before starting the next phase.

Troubleshooting should normally use one test or one change at a time.

---

## 10. Current Phase

Current phase:

Phase 0  Architecture

Phase 0 must not implement business features.

Phase 0 responsibilities:

- Environment inspection
- Starter project inspection
- Modular Monolith design
- Module boundaries
- Database entity planning
- Localization architecture
- RTL strategy
- Authentication/authorization architecture
- Documentation

After Phase 0 is completed, development stops for review/approval before Phase 1.

## Internal Organization Model

The system represents one internal company. Users may belong to multiple internal departments through `department_memberships`, with an optional primary department. Departments support unlimited parent/child hierarchy and have an optional manager. The manager must be a member of the same department and may manage multiple departments.

The previous single `users.department_id` relationship is replaced by the membership model. External organizations and contacts are separate concepts and are not part of the internal department structure.

