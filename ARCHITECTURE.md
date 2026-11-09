## 2026-11-09 — Page-number coordinate reconciliation and organization postal code

- The page-number mismatch was caused by different coordinate systems: screen previews used raw A4 percentages, while Chromium's print counters are rendered in page-margin boxes. A counter placed inside the body region was moved to the nearest top/bottom print margin only in actual print. Both screen previews now emulate the same margin-box segment, vertical boundary, horizontal anchor/padding and alignment. The printed counter also honors the configured font family, size, weight and format-specific bidi direction instead of hard-coded 9pt Tahoma/LTR preview behavior.
- Page counters are constrained to the reserved top/bottom print margins. If configured in the body area, they snap to the nearest margin boundary. The designer now explains this behavior.
- Added optional organization postal code across settings form, validation, settings persistence, template sample data, repeatable header/footer/legacy-repeat token lists, and print token resolution as `{{postal_code}}`. Older document identity snapshots fall back to the current organization postal code when the snapshot has no non-empty value.
- Regression assertions cover postal-code persistence and print props. PHP tests, frontend build, and Chromium print acceptance are pending owner execution.
- Development only; master, release branch, and server remain unchanged.

﻿# Office Automation System  Architecture

## 1. Architecture Style

The application uses a Modular Monolith architecture.

It is a single Laravel application and a single deployable system.

Microservices are explicitly out of scope.

Modules have clear ownership boundaries and controlled dependencies.

The initial implementation uses a directory-based modular architecture and does not introduce a third-party modularization package unless a future architectural decision explicitly requires one.

---

## 2. High-Level Structure

The target logical structure is:

app/
 Core/
    Authentication/
    Users/
    Authorization/
    Departments/
    Settings/
    Shared/

 Modules/
    Projects/
    Tasks/

 Shared/
     Infrastructure/
     Support/

This structure is an architectural target.

It must not be created prematurely before the relevant implementation phase.

---

## 3. Dashboard\n\nDashboard is an application-facing module that aggregates read-only information from Core, Projects and Tasks. It does not own their business data or modify their records.\n\nDashboard is responsible for:\n\n- My Tasks\n- Today's Tasks\n- Overdue Tasks\n- Active Projects\n- Completed Tasks\n- Recent task activity summary\n\nDashboard access respects the authorization and task visibility rules of the underlying modules.\n\n---\n\n## Custom Fields

Custom Fields are a Core capability for organization-specific metadata that should not become first-class columns on every business table.

The framework uses:
- CustomField: definition scoped to an entity model, with stable key, label, type, required/active state, ordering and select options.
- CustomFieldValue: value attached to a concrete entity through an entity type + entity id pair.

Supported initial field types:
- text
- textarea
- number
- date
- boolean
- select

Stable domain attributes such as employee code, national ID, name and birth date remain first-class profile fields. Custom Fields are intended for genuinely variable attributes such as personnel grade, badge number or organization-specific classifications.

The value service owns type normalization and validation. Business modules consume the service rather than implementing their own custom-field storage.

## 4. Core

Core owns cross-cutting system capabilities and identity.

Responsibilities include:

- Authentication
- Users
- User profile
- Roles
- Permissions
- Departments
- Organizational positions and position assignments
- Settings

### Organization model

Department Membership answers **which organizational units a user belongs to**. Position and Position Assignment answer **which organizational title the user holds within a unit**. These concepts remain separate so a user can belong to multiple departments and hold one or more formal positions without overloading membership data. The current Department Manager reference remains the fast-path current manager used by existing task and approval rules; position-based actor resolution can be added to Workflow without changing membership semantics.

Core must not contain project- or task-specific business rules.

---

## 4. Projects Module

Projects owns project-related business concepts.

Expected concepts include:

- Project
- Project status
- Project manager
- Project membership
- Project lifecycle
- Optional department association
- One shared organization-level default project with department-owned project sections

Projects must expose stable application-level contracts when other modules need project information.

Internal implementation details should not be accessed directly by other modules.

---

## 5. Tasks Module

Tasks owns task-related business concepts.

Expected concepts include:

- Task
- Task assignment
- Task status
- Priority
- Due date
- Comments
- Attachments
- Activity/history
- Completion/report

Tasks may depend on concepts exposed by Core and Projects through controlled application contracts.

Tasks must not directly manipulate internal implementation details of another module.

---

## 6. Dependency Rules

General rules:

1. A module owns its business rules and data.
2. Other modules must not directly modify another module's data.
3. Cross-module communication should use explicit application contracts, interfaces, services, or events when appropriate.
4. Infrastructure details must not leak into unrelated business logic.
5. Circular module dependencies must be avoided.
6. Shared code must remain genuinely generic.
7. A shared folder must not become a dumping ground for business logic.

The exact dependency graph will be refined when modules are implemented.

---

## 7. Layers

Where useful, a module may contain:

- Domain
- Application
- Infrastructure
- Presentation

Not every module must contain every layer.

Layers should be introduced according to actual complexity rather than as empty architectural ceremony.

### Domain

Contains business concepts and rules that are independent of UI and infrastructure.

### Application

Coordinates use cases and application workflows.

### Infrastructure

Contains framework/database/storage/external-service implementations.

### Presentation

Contains controllers, requests, resources and UI-facing integration.

---

## 8. Database Ownership

Each module owns the database structures associated with its business domain.

Initial conceptual ownership:

Core:
- users
- roles
- permissions
- role/permission relationships
- departments
- settings

Projects:
- projects
- project memberships

Tasks:
- tasks
- assignments
- comments
- attachments metadata
- activities
- reports

The exact schema and relationships will be designed before implementation.

Database naming remains English.

---

## 9. Authentication and Authorization

Authentication uses Laravel's web/session authentication model unless a later architectural requirement changes this decision.

Authorization is permission-based.

Roles are collections of permissions.

Business actions must be authorized on the backend.

Frontend permission checks are for UX only and must never replace backend authorization.

---

## 10. Localization Architecture

User-facing strings must not be hard-coded into business logic or Vue components where localization is required.

Persian is the initial locale.

English remains the programming/documentation language.

The application must support a future locale switch without redesigning the UI architecture.

RTL is the default presentation direction for the initial release.

---

## 11. Date, Time and Timezone

Database storage uses standard Gregorian DateTime-compatible values.

The application must distinguish:

- Stored timestamp
- Application timezone
- User-facing formatted date

Jalali presentation belongs to the presentation/localization layer and must not alter database storage semantics.

Timezone must be configurable.

---

## 12. File Storage

File handling must use Laravel's filesystem abstraction.

Business logic should not depend directly on a local filesystem path.

Attachments must have controlled access.

Uploaded files must be validated and stored using safe generated filenames.

The architecture must permit a future switch from local storage to S3-compatible object storage.

---

## 13. Infrastructure Evolution

Redis is not required during the initial bootstrap.

It may later be introduced for:

- Cache
- Queues
- Rate limiting
- Temporary state
- Other justified use cases

Laravel Reverb/WebSocket is also deferred until real-time functionality requires it.

---

## 14. Database Platform

Microsoft SQL Server is the target database.

SQLite may exist in the Laravel skeleton but is not considered the production database architecture.

The application must be tested against SQL Server before database-dependent feature development is considered complete.

---

## 15. Frontend Architecture

Vue 3 + TypeScript + Inertia.js is the frontend architecture.

Components should remain focused.

Avoid large components containing:

- Data fetching
- Business rules
- Complex state management
- Multiple unrelated UI responsibilities

Reusable UI components belong in an appropriate shared UI layer.

Business-specific components remain within their module.

---

## 16. API / Transport

The initial application uses Inertia.js rather than creating a separate SPA/API architecture.

A separate public API is not required unless a future requirement justifies it.

---

## 17. Testing Architecture

Tests should exist at appropriate levels:

- Unit tests for isolated business logic
- Feature tests for application behavior
- Authorization tests for permissions
- Integration tests where infrastructure boundaries require them

Tests must validate actual behavior.

Tests must not be weakened or bypassed merely to obtain a passing result.

---

## 18. Architectural Change Policy

Significant architectural changes require:

1. Identification of the problem.
2. Explanation of alternatives.
3. Selection of the preferred approach.
4. Documentation update.
5. Implementation.
6. Appropriate tests.

No major architectural change should be introduced silently.

## Internal Organization Structure

The initial system represents a single internal company. The internal organization is not modeled as an `organizations` table.

Users and departments have a many-to-many relationship through `department_memberships`. A user may belong to zero, one, or multiple departments. At most one membership per user may be marked `is_primary`; this is an application/domain rule.

Departments support unlimited hierarchy through `parent_id`. Each department may have one manager through `manager_id`, and the department manager must also be a member of that department. A user may manage multiple departments.

The legacy `users.department_id` field is removed after migration of existing assignments to `department_memberships`.

External organizations (customers, suppliers, partners, and their contacts) are a separate future domain and must not be merged with the internal organization structure.

External organizational units own their external manager/addressee configuration. An ExternalDepartment may select one active ExternalContact from that same unit and stores the unit-level organizational title prefix, position, personal salutation and professional title. These fields must not be moved to internal Departments or required on every ExternalContact. Legacy contact-level fields may remain for historical compatibility only.


---

## 18. Operational Backbone Architecture

The original Project/Task-first design remains valid, but the system is now explicitly treated as an enterprise operational platform rather than a collection of isolated CRUD modules.

The stable architectural backbone is:

**Identity & Organization → Cartable → Workflow → Work Items → Documents → Audit/Notifications**

Business modules such as Correspondence, CRM, HR, Procurement and Inventory build on this backbone.

### 18.1 Work Item vs Workflow

A Task is a unit of execution. A Workflow is the process that controls how work moves between actors and states.

Do not encode workflow semantics by continually expanding the Task status enum.

Task execution state and review state are separate concepts. The target lifecycle supports:

- execution: todo, in_progress, submitted
- review: not_required, pending, approved, rejected

Typical flow:

Employee → Submit → Manager Review → Approve → Completed

or:

Employee → Submit → Manager Review → Return with reason → Employee continues

The exact persistence model will be finalized during Workflow V1 design.

### 18.2 Assignment vs Review/Approval

Assignment answers who performs the work.

Review/Approval answers who evaluates the submitted work.

They must remain separate domain concepts.

The first Workflow V1 must support actor resolution such as:

- current task assignee
- task creator
- department manager
- project manager
- specific user
- specific role

Do not hard-code department-manager checks into individual business modules when the same rule can be represented as a workflow actor resolver.

### 18.3 Task Dependencies

Dependencies must support more than a single next-task relation.

The target model must allow at least:

- sequential dependency
- completion-gated next work
- multiple predecessors
- future branching/conditional transitions

A dependency should reference work items/process steps rather than copying task data.

### 18.4 Documents

Documents are a first-class cross-module domain, not merely task attachments.

Target concepts:

- Document
- Document Version
- Document Link
- Document Access
- optional document metadata/type/category

A document may be linked to:

- Project
- Task
- Workflow instance/step
- Correspondence
- future contracts, HR records, CRM records, etc.

When work moves from one task to another, the same document reference should be passed forward rather than duplicating the file.

Storage remains behind Laravel Filesystem abstraction so local storage can later move to S3-compatible storage.

### 18.5 Cartable / Inbox

A unified Cartable is a first-class application concept.

It will eventually aggregate actionable items such as:

- My Tasks
- Tasks awaiting my review
- My referrals
- Documents requiring action
- Incoming correspondence
- future approvals/requests

Modules own their records, but Cartable provides the user's operational entry point through module contracts/read models. It must not become a second owner of business data.

### 18.6 Events and Module Boundaries

Modules must communicate through explicit contracts and domain/application events where appropriate.

Examples:

- TaskSubmitted
- TaskApproved
- TaskReturned
- WorkflowStepCompleted
- DocumentLinked
- CorrespondenceReferred

Events should notify other modules without creating direct database coupling.

### 18.7 Workflow V1 Scope

Workflow V1 is intentionally not a visual workflow designer.

Initial scope:

- workflow definitions
- workflow instances
- ordered steps
- actor resolution
- task creation/execution
- submit
- approve
- reject/return
- transition history
- sequential and dependency-aware progression
- audit integration

A visual designer, conditional branching UI and advanced rule engine are deferred until the domain model proves stable.

### 18.8 Architectural Boundary

The platform should provide shared operational capabilities, not attempt to replace every specialized ERP.

Core/backbone owns:

- identity
- organization
- authorization
- cartable
- workflow
- documents
- notifications
- audit
- shared search/reporting infrastructure

Future business modules own their domain data and rules.

This boundary is mandatory for long-term maintainability.


## User & Organization Onboarding

- Public self-registration is disabled; this is an internal organizational system.
- `SYSTEM_ADMIN` is a normal user with the system role and does not require department membership or an organizational position.
- User profile data is separated from the authentication record in `user_profiles`, including employee code, national ID, birth date and avatar path.
- Initial department membership may be supplied during user creation. Subsequent department membership changes use the dedicated `users.manage_departments` permission and endpoint rather than general user editing.
- New users are created inactive by default and receive an invitation link. The user activates the account by choosing their own password.
- Direct password creation remains possible only when an administrator explicitly creates an active user.
