# Office Automation Roadmap

## Product Direction

Office Automation is a Persian, RTL, modular enterprise office-automation platform for a single internal company.

The long-term product goal is to automate operational processes through independent business modules that share a stable backbone:

**Identity & Organization → Cartable → Workflow → Work Items → Documents → Notifications/Audit**

Projects and Tasks are the first business capabilities, not the architectural center of every future module.

---

# Versioning Strategy

The roadmap is now version-oriented.

Each major version must have:

1. A clear product goal.
2. A defined feature boundary.
3. Explicit dependencies on previous versions.
4. Backend authorization and feature tests.
5. Frontend build validation.
6. Manual visual/functional acceptance.
7. Documentation/status reconciliation before the next version starts.

A later version must not silently expand the scope of an earlier version.

Shared capabilities should be strengthened in the version where they are required, but their ownership must remain in Core/shared operational infrastructure rather than being duplicated inside business modules.

---

# V1 — Foundation & Work Management

## Goal

Deliver a usable internal company platform with a stable Core, organizational structure, projects, tasks, and the operational backbone required by those features.

## Scope

### Core / Identity
- Authentication
- Username-based login
- User profiles and contact information
- Invitation-based onboarding
- User lifecycle and activation
- Roles
- Permissions
- Authorization and scopes
- Custom Fields foundation
- System bootstrap administrator
- System administrator outside organizational membership
- Organization settings
- Configurable system-level settings foundation

### Internal Organization
- Departments
- Unlimited department hierarchy
- Department memberships
- Department managers
- Organizational positions
- Position assignments
- Primary position
- Department membership prerequisite for position assignment
- User ↔ department management

### Projects
- Project CRUD
- Project lifecycle
- Project manager
- Project members
- Project roles
- Optional department association
- Department-owned/default projects
- Project access and authorization
- Pagination
- Project/task lifecycle boundaries

### Tasks / Work Items
- CRUD
- Assignment
- Referral/history
- Status and priority
- Due dates
- Project/department context
- External follow-up context
- Comments
- Work logs
- Reminders
- Activity/timeline
- Filtering/search
- Pagination
- Access scopes
- Task lifecycle rules
- Submit / review / approve / return / resubmit
- Task dependencies

### Operational Backbone
- Controlled documents and task attachments
- Document versions/links/access foundation
- Workflow V1
- Unified Cartable V1
- In-app notifications
- Audit V1 core
- Persian RTL/Jalali presentation foundations

## Explicitly excluded from V1
- Correspondence
- Digital Archive as a full archive product
- Forms & Requests engine
- Meetings & Calendar
- Gantt
- Subtasks
- Recurring tasks
- Visual workflow designer
- Internal Chat
- CRM
- Procurement
- Inventory
- HR
- PBX
- Production deployment/hardening

## V1 Status

**Implementation status: complete.**

**Current acceptance status: manual UI/functional acceptance in progress.**

Recent UI/core acceptance work includes:
- Login UI refinement
- Password visibility control
- Independent username support
- Jalali birth-date presentation
- Standalone System Bootstrap Admin
- Organization Settings
- Core Settings permission
- RTL/frontend type consistency

Before V1 is formally closed, run:
- full PHPUnit/Laravel test suite after the latest Core/Settings changes
- frontend production build
- final manual visual/functional acceptance

No V2 implementation should begin until the V1 acceptance gate is closed.

---

# V2 — Office Automation

## Goal

Turn the V1 operational backbone into a real office-automation workflow platform.

## Scope

### Correspondence
- Incoming letters
- Outgoing letters
- Internal letters
- Registration and numbering
- Draft / registered lifecycle
- Referrals
- Instructions
- Replies
- Attachments
- Correspondence archive references
- Correspondence ↔ Task
- Correspondence ↔ Workflow
- Approval/signature flow
- Correspondence actions in Cartable
- Correspondence notifications and audit

### Digital Archive
- Central document archive
- Document categories
- Metadata
- Version management
- Searchable archive references
- Retention metadata
- Access control
- Scan/upload integration
- Archive links to business records

### Forms & Requests
Generic request engine built on Workflow + Cartable + Documents:
- Administrative requests
- Leave requests
- Purchase requests
- Equipment requests
- Approval requests
- Request lifecycle/history
- Request attachments
- Approval routing
- Notifications

### Backbone hardening for V2
- More complete document handoff
- Better workflow actor resolution
- Correspondence/document/task integration contracts
- Expanded audit coverage
- Queue-backed notification delivery where justified

## V2 Acceptance Gate

- Correspondence end-to-end workflow works.
- Archive records are searchable and access-controlled.
- Forms can use reusable workflow definitions.
- Cartable can surface actionable correspondence/requests.
- Existing V1 features remain regression-safe.

---

# V3 — Collaboration & Planning

## Goal

Give users a complete daily workspace for planning, scheduling and collaboration around work.

## Scope

### Meetings & Calendar
- Meetings
- Invitations
- Participants
- Agenda
- Minutes
- Follow-up tasks
- Calendar views
- Reminders
- Calendar integration contracts

### My Work / Personal Workspace
- My work
- My tasks
- My approvals
- My referrals
- My documents
- Recent activity
- Calendar summary
- Personal operational dashboard

### Advanced Task Planning
These were intentionally deferred from V1:
- Board view
- Calendar task view
- Gantt view
- Subtasks
- Recurring tasks
- Better dependency visualization

### Search
- Unified search foundation
- Tasks
- Projects
- Documents
- Correspondence
- Requests
- Employees

---

# V4 — Customer & Commercial Operations

## Goal

Add customer-facing operational processes without coupling them to Core users/departments.

## Scope

### CRM
- Customers
- Customer organizations
- Contacts
- Leads
- Activities
- Follow-ups
- Customer documents
- Customer tasks
- Communication history

### Sales
- Opportunities
- Sales pipeline
- Quotations
- Orders
- Sales tasks
- Customer approvals
- Sales documents
- CRM ↔ Task/Workflow integration

## Architectural rule

External organizations and external contacts remain separate from authenticated internal Users and internal Departments.

---

# V5 — Procurement, Inventory & Assets

## Goal

Automate material purchasing and resource ownership/maintenance.

## Scope

### Procurement
- Purchase requests
- Supplier management
- RFQ
- Supplier quotations
- Purchase orders
- Approval workflow
- Procurement documents
- Procurement tasks

### Inventory
- Warehouses
- Items
- Stock movements
- Inventory balances
- Stock requests
- Inventory documents

### Assets
- Asset registry
- Asset assignment
- Maintenance
- Asset history
- Asset documents
- Approval workflows

## Architectural rule

Procurement and Inventory must use the existing Workflow, Documents, Cartable and Audit backbone rather than creating parallel approval/document systems.

---

# V6 — HR & Internal Collaboration

## Goal

Complete employee-oriented automation and internal communication.

## Scope

### HR
- Employee records
- Employment information
- Organizational positions integration
- Employment documents
- Leave
- Attendance
- Personnel requests
- HR workflows
- Employee history

### Internal Chat
- Private chat
- Group chat
- Department channels
- File sharing
- Task/document references
- Realtime messaging
- Notifications

---

# V7 — Intelligence, Automation & Integrations

## Goal

Move from workflow digitization to cross-module intelligence and automation.

## Scope

### Reporting & Analytics
- Operational reports
- Task performance
- Workflow bottlenecks
- Department performance
- SLA reports
- Correspondence reports
- CRM reports
- Cross-module dashboards

### SLA & Escalation
- SLA policies
- Due-date rules
- Escalation
- Manager notifications
- Overdue escalation
- Workflow timeout

### Automation Rules
Event-driven automation such as:
- Task completed → create next task
- Document approved → advance workflow
- Letter registered → create task
- Request approved → notify requester
- SLA exceeded → escalate

### Integrations
Driver-based integrations for:
- Email
- SMS
- Storage
- Printer/scanner
- Calendar
- External APIs
- SSO / identity providers
- PBX / Asterisk

### Telephony / PBX
- Extensions
- Call logs
- Call history
- Asterisk integration
- Recordings
- Queues
- IVR
- Call-to-customer linking
- Call-to-task linking

---

# V8 — Production & Enterprise Readiness

## Goal

Prepare the platform for serious production deployment and long-term operation.

## Scope

### Security
- Authorization review
- File access review
- Session/security hardening
- Rate limiting review
- Audit/security review
- Dependency/security scanning

### Performance
- SQL Server query optimization
- Index review
- Caching
- Queues
- Large-file handling
- Large-list pagination
- Background processing

### Deployment & Operations
- Production configuration
- LAN deployment
- VPS/cloud deployment
- Backup/restore
- Logging
- Monitoring
- Queue workers
- Scheduler
- Storage strategy
- Deployment procedure
- Upgrade procedure

### Reliability
- Critical integration tests
- Critical end-to-end tests
- Failure/retry handling
- Queue retry policies
- Disaster recovery
- Data retention policies

---

# Cross-Version Architectural Rules

These rules apply to every version:

1. Core remains responsible for identity, internal organization, roles, permissions and shared settings.
2. Business modules own their own data and rules.
3. Internal users and departments must never be merged with external organizations and contacts.
4. Workflow, Documents, Cartable, Notifications and Audit are shared capabilities and must not be duplicated per module.
5. Cross-module communication uses explicit contracts/services/events where appropriate.
6. Backend authorization is mandatory; frontend permissions are UX only.
7. Modules must not directly manipulate another module's internal data.
8. No major architectural change may be introduced silently.
9. New dependencies require an explicit architectural reason.
10. Every version must preserve regression coverage for previous versions.

---

# Release Acceptance Model

A version is not considered complete merely because its CRUD/UI exists.

A version closes only when all of the following are true:

- Domain and business rules are implemented.
- Backend authorization is enforced.
- Required feature/regression tests pass.
- Frontend production build passes.
- RTL/Persian/Jalali presentation is reviewed where applicable.
- Main user journeys are manually accepted.
- Documentation matches the repository.
- Deferred items are explicitly recorded rather than silently left unfinished.

---

# Current Position — 2026-10-06

**Current version: V1 — Foundation & Work Management**

**V1 implementation status:** scope implemented.

**V1 acceptance status:** technical validation passed; manual UI/functional acceptance in progress.

Recent V1 manual acceptance found a user-password validation localization defect: passwords shorter than the enforced 8-character minimum displayed the Laravel translation key `validation.min.string`. This was fixed by adding the Persian validation dictionary at `lang/fa/validation.php`.

Recent V1 acceptance also found redundant user identity inputs. The standalone user `name` field was removed from the UI; first name and last name are now the user-facing identity inputs, while `users.name` is derived by the backend.

Technical validation completed:
1. Dedicated system-code regression test — passed.
2. Full PHPUnit/Laravel suite — passed.
3. Frontend production build — passed.

Before V1 closes:
1. Manually confirm the Persian password validation message after pulling the latest development change.
2. Continue final manual UI/functional acceptance.
3. Fix any newly discovered runtime defects at their root cause.
4. Reconcile documentation and close the V1 acceptance gate.

No V2 implementation should begin until the V1 acceptance gate is closed.

# Implementation Order

The implementation order is now version-based:

1. **V1 — Foundation & Work Management**
2. **V2 — Office Automation**
3. **V3 — Collaboration & Planning**
4. **V4 — Customer & Commercial Operations**
5. **V5 — Procurement, Inventory & Assets**
6. **V6 — HR & Internal Collaboration**
7. **V7 — Intelligence, Automation & Integrations**
8. **V8 — Production & Enterprise Readiness**

Within each version, implementation remains incremental:

**Inspect → Design/Boundary Review → Smallest Coherent Change → Test → Build → Manual Acceptance → Documentation → Next Item**

Do not start a new module merely because it appears later in this document. Work proceeds from the current version's acceptance gate.

---

# Modules Intentionally Outside the Product Boundary

The platform is not intended to replace every specialized ERP.

Unless a concrete business requirement changes the decision, these remain outside Core/product scope:

- Full accounting / GL
- Payroll accounting
- Manufacturing / MRP
- Complex ERP warehouse accounting
- Banking
- Full legal practice management

Such systems should integrate with Office Automation rather than distort the Core/Workflow/Documents architecture.


### 2026-10-06 — User identity regression fix
A PHP syntax error introduced in `UpdateUserAction.php` during the display-name derivation change was corrected by simplifying the expression. Focused `UserControllerTest` validation is pending.


### 2026-10-06 — User update compatibility regression
The full `UserControllerTest` exposed a compatibility regression: legacy update callers providing `name` without first/last name no longer updated `users.name`. `UpdateUserAction` now retains the documented fallback while continuing to derive the display name from first/last name for the current UI.

**Status:** fixed; full `UserControllerTest` validation pending.


### 2026-10-06 — Required-field UI consistency
V1 form review found that required controls were not consistently marked visually. A shared frontend indicator now displays a red `*` beside labels associated with required controls, required attributes were completed where missing, and external-party required fields now have visible labels.

**Status:** implemented; frontend build validation pending.


### 2026-10-06 — External parties navigation
The external-parties page was aligned with the standard authenticated layout so the main navigation is preserved. A «بازگشت» action was added for returning to the previous page, with dashboard fallback.

**Status:** implemented; frontend build validation pending.


### 2026-10-06 — External parties hierarchy UI
External departments now use hierarchical ordering and indentation in both the department list and the parent-department selector. The external-parties back action was also moved into a script handler to satisfy Vue/TypeScript template typing.

**Status:** implemented; frontend build validation pending.

### 2026-10-06 — Task default-project visibility
Fixed the gap between department default projects and task creation: system-default projects remain real database records and are now available to department members in task creation, with matching authorization and access rules.
**Status:** implemented; validation pending.

### 2026-10-06 — Project view scope
Project visibility now respects the configured `projects.view` permission scope, including unit-level visibility for projects belonging to the user's departments and their default projects.
**Status:** implemented; validation pending.


### 2026-10-06 — Permission scope audit
A review of role/permission enforcement found that permission existence is generally enforced, but scope is not yet consistent across all module actions. The task creation project selector was specifically corrected to honor `tasks.create` scopes, including organization scope for the system administrator.

The audit identified remaining scope gaps in several task actions and project mutation actions. These require normalization before permission handling can be considered fully consistent across V1.

**Status:** audit in progress; task project-option defect fixed; broader scope normalization pending.


### 2026-10-06 — Permission scope normalization
Project mutation authorization and task workflow actions now enforce permission scopes in addition to contextual rules. This covers project update/delete/member/role management and task create/refer/submit/review/reminder completion or cancellation.
**Status:** implemented; full backend validation pending.


### Permission-scope test fixture normalization (2026-10-06)
- tests/Feature/Modules/Tasks/TaskHttpTest.php now creates task permission fixtures with the same PermissionScope::defaultForPermission() semantics used by production role-permission synchronization.
- This prevents the database default organization scope from masking project-scoped authorization in HTTP tests.
- TaskHttpTest passes after this normalization.


### V1 Permission Scope Normalization — 2026-10-06
- Completed the permission-scope capability audit for the remaining V1 areas. Scope is now explicitly meaningful only for Tasks and Projects; Users, Departments, Roles, External Parties, Cartable, Notifications and other organization-level permissions are not presented as scope-aware permissions.
- Backend validation/action-layer enforcement prevents invalid scope combinations from being persisted. V1 remains in technical validation/manual acceptance; V2 is unchanged.


### UI terminology and task page normalization — 2026-10-06
- V1 UI terminology for `Task/Tasks` is standardized to Persian **وظیفه/وظایف** in the task list/create experience.
- The Tasks page was redesigned so the **وظایف** list is the primary content; creation is opened through **ایجاد وظیفه** in a modal instead of occupying the top of the page.


## V1 — Task Detail UX Refinement (2026-10-07)
- صفحه جزئیات وظیفه به workspace عملیاتی بازطراحی شد؛ ایجاد/ثبت فعالیت، نظر و یادآوری به اقدام‌های سریع منتقل شدند و ویرایش/ارجاع در بالای صفحه قرار گرفتند.
- «افراد درگیر» به‌عنوان رابطه چندنفره مستقل برای وظیفه اضافه شد.
- اطلاعات پروژه، پیوست‌ها و پیگیری خارجی در ستون کناری و گزارش فعالیت، خط زمانی، نظرات و یادآوری‌ها در بخش اصلی سازمان‌دهی شدند.


### 2026-10-07 — V1 Task Activities
- مسئول اصلی وظیفه یک نفر باقی می‌ماند و افراد مرتبط داخلی به‌صورت رابطه جداگانه قابل ثبت هستند.
- فعالیت بعدی به‌جای ستون ثابت روی `tasks` به‌صورت جدول مستقل `task_activities` پیاده‌سازی شد تا فعالیت‌های برنامه‌ریزی‌شده، فعالیت‌های جانبی و سابقه انجام‌شده قابل نگهداری باشند.
- ایجاد وظیفه اکنون می‌تواند اولین فعالیت را دریافت کند و صفحه جزئیات امکان ثبت فعالیت جدید و تکمیل فعالیت بعدی را فراهم می‌کند.
