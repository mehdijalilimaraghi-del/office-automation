# Office Automation System  Project Specification

## 1. Project Overview

Office Automation is a web-based internal company office-automation platform.

The product is designed as a production-oriented Modular Monolith. V1 delivers the Core, internal organization, Projects, Tasks and the shared operational backbone required by those capabilities. Later versions add Correspondence and other business modules on top of the stable V1 foundation.

The authoritative version roadmap is ROADMAP.md.

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

## 4. Roles and Authorization

Roles are dynamic collections of permissions.

Current V1 rule:
- SYSTEM_ADMIN is the protected system role/bootstrap administrator.
- A normal user may have at most one application Role.
- Contextual responsibilities such as Project Manager or Department Manager are assigned through their owning business object/organization context, not as generic global Roles.
- Backend authorization is authoritative; frontend checks are UX only.

Permissions are dynamic and extensible.

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
- One shared system default project; project sections are assigned to responsible departments and default projects cannot be manually updated or deleted

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

## 6. Versioned Product Expansion

V1 already contains the operational backbone required by the implemented work-management system:
- Workflow V1
- Documents/attachments foundation
- Unified Cartable V1
- Notifications V1
- Audit V1

Post-V1 expansion is versioned in ROADMAP.md:
- V2: Correspondence, Digital Archive, Forms & Requests
- V3: Collaboration & Planning
- V4: Customer & Commercial Operations
- V5: Procurement, Inventory & Assets
- V6: HR & Internal Collaboration
- V7: Intelligence, Automation & Integrations
- V8: Production & Enterprise Readiness

No later-version scope should be pulled into V1 without an explicit product decision.

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

**Current version: V1 — Foundation & Work Management**

V1 implementation scope is complete. The repository is currently in the **V1 technical/debugging and manual UI/functional acceptance checkpoint**.

The current acceptance blockers are recorded in `CURRENT_STATUS.md` and `ROADMAP.md`.

No V2 feature development starts until the V1 acceptance gate is closed.


## Internal Organization Model

The system represents one internal company. Users may belong to multiple internal departments through `department_memberships`, with an optional primary department. Departments support unlimited parent/child hierarchy and have an optional manager. The manager must be a member of the same department and may manage multiple departments.

The previous single `users.department_id` relationship is replaced by the membership model. External organizations and contacts are separate concepts and are not part of the internal department structure.


## 11. Long-Term Product Architecture

The system is not limited to Project/Task management. The long-term product is an operational office-automation platform built around shared capabilities:

- Identity and internal organization
- Authorization
- Cartable
- Workflow
- Work items/tasks
- Documents
- Notifications
- Audit
- Shared search/reporting

Future business modules must consume these capabilities instead of implementing parallel workflow, document, approval, or identity systems.

### Workflow Requirement

The platform must support processes in which:

1. work is assigned to an actor
2. the actor performs the work
3. the actor submits the result
4. a manager or designated reviewer evaluates it
5. the result is approved or returned with a reason
6. returned work can be resubmitted
7. subsequent work can depend on completion/approval of previous work

### Document Requirement

Documents must eventually be independent first-class records with versions and links to business records. A document may belong to a Project, Task, Workflow step, Correspondence record, or future business module. Passing a document to a subsequent task must reuse the same document/version reference rather than duplicating the file.

### Module Expansion

The supported long-term module roadmap is maintained in ROADMAP.md.
