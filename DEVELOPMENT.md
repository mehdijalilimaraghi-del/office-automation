# Office Automation System  Development Guide

## 1. Development Phases

### Phase 0  Architecture

Completed.

Tasks:

- Inspect environment
- Inspect Laravel skeleton
- Define architecture
- Define module boundaries
- Define database entities
- Define localization strategy
- Define authentication/authorization strategy
- Create and maintain documentation

No major business feature is implemented in Phase 0.

---

### Phase 1  Bootstrap

Planned work:

- Laravel configuration
- Vue 3
- TypeScript
- Inertia.js
- Tailwind CSS
- shadcn-vue
- Vite
- Persian locale
- RTL
- Base layout
- Authentication
- SQL Server connection
- Git repository
- Build and tests

---

### Phase 2  Core

Planned work:

- Authentication
- Users
- User profile
- Roles
- Permissions
- Departments
- Settings

---

### Phase 3  Projects

Planned work:

- Project CRUD
- Project status
- Project manager
- Members
- Dates
- Project view

---

### Phase 4  Tasks

Planned work:

- Task CRUD
- Assignment
- Status
- Priority
- Due date
- Description
- Comments
- Attachments
- Activity/history
- Completion/report

---

### Phase 5  Operational Backbone

Completed in the current repository:

- Task lifecycle review/approval
- Task dependencies
- Document foundation
- Workflow V1
- Unified Cartable
- Notifications
- Audit V1 core

The next planned business module is Correspondence, after the shared backbone has been verified and the repository/docs checkpoint is reconciled.

---

### Phase 6  Office Automation

Current target:

- Correspondence
- Digital Archive
- Forms & Requests
- Meetings & Calendar

Correspondence is the next business-module implementation. It must consume the existing Core, Documents, Workflow, Cartable, Notifications and Audit capabilities rather than recreate them.

---

## 2. Git Workflow

Git is part of the project from the beginning.

Commits should be:

- Small
- Focused
- Meaningful
- Related to one logical change

Examples:

chore: initialize laravel project
chore: define project architecture
feat: add core authentication
feat: add users and roles
feat: add projects module
feat: add task management

Do not combine unrelated changes into one commit.

---

## 3. Change Workflow

Before every meaningful change:

1. Inspect the relevant existing files.
2. Identify the root cause or requirement.
3. Define the smallest required change.
4. Make the change.
5. Run the relevant test.
6. Inspect the result.
7. Update documentation if required.
8. Stop before moving to the next feature.

---

## 4. Troubleshooting

Troubleshooting should normally follow a one-change/one-test approach.

Do not change multiple unrelated variables at once.

Do not remove migrations, data, validation or security controls to hide an error.

Fix the root cause.

When environment behavior differs from expectations, inspect the environment before modifying the application.

---

## 5. Dependencies

A new Composer or NPM package requires a concrete reason.

Before adding a dependency, evaluate:

- Whether Laravel already provides the required capability
- Maintenance status
- Compatibility with the current PHP/Laravel version
- Security implications
- Long-term architectural impact

Avoid unnecessary packages.

---

## 6. Database Rules

- SQL Server is the target database.
- Schema changes must use migrations.
- Data must not be deleted to hide migration problems.
- Existing migrations must not be rewritten merely to make development easier once they have become part of a shared environment.
- Destructive database operations require explicit justification.
- Foreign keys and indexes should be designed intentionally.
- Date/time fields use standard database-compatible DateTime types.

---

## 7. Security Rules

Security-sensitive behavior must be enforced server-side.

Never rely solely on:

- Vue conditions
- Hidden buttons
- Client-side validation
- Route visibility

Authorization must be enforced on the backend.

File uploads require:

- Type validation
- Size validation
- Safe generated names
- Controlled storage
- Authorization checks

---

## 8. Localization Rules

All new user-facing text must support localization.

Do not introduce Persian strings directly into business logic when a translation key is appropriate.

Initial UI:

- Persian
- RTL

Code:

- English
- English identifiers
- English technical documentation

---

## 9. Frontend Rules

Vue components should remain focused.

Prefer reusable components for genuinely reusable UI.

Do not create abstractions solely because two components happen to look similar once.

TypeScript should be used for frontend application code.

Inertia should be used for normal application navigation and server-driven page data.

---

## 10. Documentation

The following documents are authoritative project documents:

- PROJECT_SPEC.md
- ARCHITECTURE.md
- DEVELOPMENT.md

When a significant requirement or architectural decision changes, update the relevant document in the same development phase.

---

## 11. Definition of Done

A change is not considered complete merely because the code was written.

A development step is complete when:

- The intended behavior is implemented.
- Relevant tests pass.
- No known regression was introduced.
- Authorization is correct.
- Validation is correct.
- Localization requirements are satisfied.
- RTL requirements are satisfied where applicable.
- Documentation is updated when necessary.
- Git state is clean enough to understand the change.

---

## 12. Current Environment

Current development environment:

- Windows
- PHP 8.5.x
- Composer 2.10.x
- Node.js 24.x
- NPM 11.x
- Laravel 12.x
- Microsoft SQL Server target

The actual installed versions should be verified rather than assumed when compatibility matters.

## 12. Enterprise Development Roadmap

The original bootstrap phases are substantially complete. From this point, development follows the enterprise roadmap in ROADMAP.md.

The next architectural sequence is:

1. Organizational positions and position assignments
3. Task dependencies
4. Document foundation
5. Workflow V1
6. Unified Cartable
7. Notifications
8. Audit expansion
9. Correspondence
10. Digital Archive
11. Forms & Requests
12. Meetings & Calendar
13. CRM
14. Procurement
15. Inventory & Assets
16. HR
17. Reporting/Search/SLA
18. Internal Chat
18. PBX and integrations
20. Production hardening

### Important Rule

Do not continue adding isolated Task/Project features that duplicate future Workflow, Document, Cartable or Notification concepts.

Before implementing a feature, determine whether it belongs to:
- Core
- Workflow
- Work Items/Tasks
- Documents
- Cartable
- Notifications
- Audit
- a business module

Shared infrastructure must be implemented once and reused by later modules.

For the detailed module scope and priorities, see ROADMAP.md.


## Current Architecture Sequence

1. Organizational positions and position assignments
2. Task lifecycle review/approval
3. Task dependencies
4. Document foundation
5. Workflow V1
6. Unified Cartable
7. Notifications
8. Audit expansion
9. Correspondence
10. Digital Archive
11. Forms & Requests
12. Meetings & Calendar
13. CRM
14. Procurement
15. Inventory & Assets
16. HR
17. Reporting/Search/SLA
18. Internal Chat
19. PBX/integrations
20. Production hardening


## User Onboarding Rules

- Public registration is disabled.
- New users should normally be created as inactive and activated through the invitation flow.
- The invitation is valid for 48 hours and the invited user sets their own password.
- Department membership is managed separately from general user profile editing through `users.manage_departments`.
- Use `office:bootstrap-admin` or the configured bootstrap environment variables for the initial `SYSTEM_ADMIN` account.
