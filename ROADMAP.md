## 2026-11-09 — Outgoing approval UI, rich-text alignment and print pagination follow-up

- Outgoing drafts awaiting department-manager approval now default the manager action selector to the existing `manager_approve` operation and expose an explicit «تأیید نامه و ارسال به دبیرخانه» option. The backend approval operation remains authoritative: it verifies the assigned department manager and approval policy, then hands the draft to the configured Secretariat manager for final registration.
- The outgoing approval regression now checks the manager's actionable queue, manager visibility of eligible department employees, successful approval/handoff, and the Secretariat manager's subsequent `needs_action` queue.
- Correspondence HTML sanitization now retains only a safe `text-align` value (left/right/center/justify) on allowed content tags, while continuing to strip unrelated style attributes. The direct-registration feature test asserts that justified paragraph alignment survives persistence.
- Print pagination no longer relies on `counter(page)` inside a fixed page-content element, which Chromium rendered as 0/0. It now uses CSS page-margin counters with named page formats for number-of, slash, short and «صفحه» styles. The print content area reserves a 12mm bottom page margin; the page number is currently rendered at bottom-center rather than the designer's arbitrary x/y coordinate. Browser-version compatibility and physical print layout still require owner acceptance.
- Changes are committed on `development` only. PHP tests, frontend type-check/build and browser print acceptance are pending owner execution. No changes to `master`, release branch, or server.

## 2026-11-07 — Fix Vue template preview build syntax

- Fixed the missing closing quote in the preview-mode grid class binding in Correspondence/Template.vue. The malformed attribute caused Vue's template compiler to report an unterminated JavaScript expression at line 93.
- No runtime behavior or data contract changed; this is a template syntax correction. Owner-run production build is pending.

## 2026-11-07 — Organization-backed template designer preview

- Corrected the designer's preview mode so the A4 page expands to a single-column layout rather than leaving the editor's sidebar column reserved.
- Preview resolves organization identity/contact tokens from saved Organization Settings, displays the active organization stamp when available, and uses clearly recognizable sample correspondence values/body text for fields that require a concrete letter (recipient, subject, numbering, creator and body).
- Sample preview content is presentation-only and is not written into the saved template. Missing organization settings use readable sample fallbacks. Letterhead remains sourced from the saved organization letterhead.
- Added feature coverage for the preview props and their organization-settings/sample-data sources. Owner-run PHP test and frontend build are pending; development only, master/server unchanged.

## 2026-11-07 — Template controller parse-error fix

- The first focused owner-run test exposed a PHP ParseError in `CorrespondenceTemplateController::edit` caused by an unbalanced closing parenthesis in the nested saved-template normalization expression introduced with the designer updates.
- Replaced the nested expression with explicit backward-compatible steps: initialize defaults, filter the legacy letterhead entry, normalize saved elements, and then append the body element when missing.
- This was a syntax-level root cause; both the authorized and unauthorized route tests were blocked before controller authorization/render logic could execute. Re-run `OrganizationSettingsTest` to validate both paths.
- No schema, migration, master branch or server changes.

## 2026-11-07 — Letter template designer usability and output corrections

- Fixed horizontal drag direction in the RTL A4 canvas: pointer movement now updates the right-offset coordinate in the opposite numeric direction, matching the visual movement.
- The coordinate plane now spans the full A4 page in both editor and print output. Print uses a zero-margin A4 page box and keeps the document body inset separately; top coordinate 0 is the actual physical page top. Dashed safe-area guides are visual only.
- Clarified positioning semantics: metadata, heading, repeating text and page number use absolute page coordinates; the body and issuer/signature use document flow and their own spacing-before setting. Irrelevant spacing/top controls are hidden for roles where they do not apply.
- Added a configurable automatic body element with spacing-before, width, alignment, size, weight and font selection. The letter's actual rich-text body remains sourced from the correspondence record and continues across pages.
- Added four page-number formats: «1 از 5»، «1/5»، «ص1 از 5» and «صفحه 1 از 5» using print page counters.
- Added per-element font selection, including «فونت پیش‌فرض تنظیمات» which resolves to the organization correspondence font. Existing saved templates are normalized with backward-compatible defaults and receive a body configuration when absent.
- Expanded settings feature coverage for body/font/page-number template persistence and restricted the template to one body element. No migration, new dependency, architecture change, master update or deployment.
- Local PHP test, TypeScript, production build and Chromium multi-page print acceptance are pending owner execution.

## 2026-11-04 — Template preview uses semantic document flow

- The visual designer now previews metadata, addressee/subject, automatic body placeholder, issuer/signature and page-number indicator as a vertical document-flow sequence rather than fixed Y positions.
- Repeating elements remain independently positioned. Preview indicates the content sequence and approximate occupied space; exact pagination and the issuer's final page depend on actual letter content.
- The latest `development` test fixture excludes obsolete `height` fields. The owner's reported failure still expects them, indicating a stale local test revision; pull before rerunning.
- Local validation and physical print acceptance remain pending. Development only; master/server untouched.

## 2026-11-04 — Role-based Persian letter template and automatic body flow

- Reworked the A4 template editor around semantic roles: letter number/date, addressee/subject, page number, issuer/signature, and repeating text. Variable choices are filtered by the selected role.
- The organization letterhead is no longer a configurable element; it is loaded automatically as a page background in the editor and print preview.
- The letter body remains automatic, is not a template element, and is rendered from the stored HTML in normal multi-page document flow. RTL direction/alignment and whitespace/rich HTML formatting are preserved in the print body.
- Metadata and addressee/subject render once before the body; issuer/signature elements follow the body; repeating elements use fixed page coordinates; page numbering is controlled by the page-number role. Flow elements expose width and spacing, not arbitrary page Y coordinates.
- Existing stored template elements are normalized for compatibility; legacy letterhead image elements are removed from the element list because the image is now automatic. No migration or PDF dependency was added.
- Validation and physical multi-page print acceptance remain pending owner execution. Development only; master/server unchanged.

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
- One shared organizational default project (not one project per department)
- Project sections associated with responsible departments
- Section-specific documents and manager-controlled task creation
- Task creation from a section with manager-first assignment and optional employee referral/history
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
- Work logs and planned/completed activity records share one `task_activities` table, distinguished by activity type and status
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

**Project-owner status: V1 is closed.** Future corrections may be made when needed, without reopening the version scope; regression coverage should be preserved.

**V2 is authorized and has started.** Remaining V1 validation notes are historical unless a future defect is found. Preserve V1 regression coverage while implementing V2.

---

# V2 — Office Automation

## Goal

Deliver the core administrative correspondence and reusable request processes on top of the V1 Workflow, Cartable, Documents, Notifications and Audit backbone.

## Scope

### Correspondence
- Incoming, outgoing and internal letters
- Registration and numbering
- Draft / registered lifecycle
- Referrals and instructions
- Replies
- Attachments
- Correspondence ↔ Task
- Correspondence ↔ Workflow
- Approval/signature flow
- Correspondence actions in Cartable
- Correspondence notifications and audit

### Forms & Requests
Generic request engine built on Workflow + Cartable + Documents:
- Administrative requests
- Purchase requests
- Equipment requests
- Approval requests
- Request lifecycle/history
- Request attachments
- Reusable approval routing
- Notifications
- Leave requests are included only as a generic request type in V2; HR-specific leave balances and policies belong to the HR version.

### Backbone hardening for V2
- More complete document handoff
- Better workflow actor resolution
- Correspondence/document/task integration contracts
- Expanded audit coverage
- Queue-backed notification delivery where justified

## Explicitly deferred from V2
- Full Digital Archive product and advanced archive search
- Meetings & Calendar and advanced task planning
- PBX / organizational telephony
- CRM and customer-specific call history
- PBX ↔ CRM integration
- Internal Chat
- HR-specific workflows and records

## V2 Acceptance Gate
- Incoming, outgoing and internal correspondence journeys work end to end.
- Correspondence can be referred, instructed, replied to, attached to tasks and routed through Workflow.
- Forms use reusable workflow definitions and appear as actionable items in Cartable.
- Authorization, notifications and audit are enforced for correspondence and requests.
- Existing V1 features remain regression-safe.
- Documentation and manual UI/functional acceptance are complete.

---

# V3 — Digital Archive

## Goal

Deliver a first-class, searchable, access-controlled digital archive that builds on the shared Documents foundation and links to business records.

## Scope
- Central document archive
- Document categories
- Metadata
- Version management
- Searchable archive references
- Retention metadata
- Access control
- Scan/upload integration
- Archive links to correspondence, tasks, workflows and other business records
- Reuse existing document/version references instead of duplicating files

## V3 Acceptance Gate
- Archive records are searchable and access-controlled.
- Metadata and version history are preserved.
- Archive documents can be linked to business records without duplicate file storage.
- Correspondence and V1 workflows remain regression-safe.

---

# V4 — Organizational Telephony (PBX)

## Goal

Deliver a usable standalone organizational telephone system before CRM, without making PBX depend on customer records.

## Scope
- PBX driver interface and Asterisk driver
- Extension management and registration status
- Trunks and gateways
- IVR
- Queues
- Call routing and call state handling
- Call logs and history
- Recording management and access control
- Conference and voicemail where supported by the selected Asterisk integration
- Phone book for internal extensions
- Click-to-call where supported
- PBX dashboard and operational reports
- Events/contracts for incoming, outgoing, answered and ended calls, plus recording and extension status events

## Architectural Boundary
- PBX owns telephony configuration, call state, call logs and recording references.
- PBX must work without CRM being installed or enabled.
- CRM lookups, customer profile opening, customer-linked call history and call-to-customer links are deferred to the explicit integration version.
- Cross-module communication must use stable contracts/events; PBX must not directly query CRM tables.

## V4 Acceptance Gate
- Core inbound/outbound call flows work against the supported Asterisk environment.
- Extension, trunk, queue and IVR behavior is covered by integration tests where practical.
- Call logs and recordings respect backend authorization.
- PBX can be disabled without loading its routes, jobs, events or UI.
- PBX functions without CRM.

---

# V5 — CRM & Commercial Operations

## Goal

Add customer-facing operations while keeping external customers and contacts separate from internal users and departments.

## Scope

### CRM
- Customers and customer organizations
- Contacts
- Leads
- Activities and follow-ups
- Customer documents
- Customer tasks
- Communication history
- CRM ↔ Task/Workflow integration

### Sales
- Opportunities
- Sales pipeline
- Quotations
- Orders
- Sales tasks
- Customer approvals
- Sales documents

## Architectural Rule
External organizations and external contacts remain separate from authenticated internal Users and internal Departments. CRM must consume shared Workflow, Documents, Cartable, Notifications and Audit capabilities rather than duplicating them.

## V5 Acceptance Gate
- Customer, contact and activity records enforce scoped authorization.
- Customer tasks and approvals use shared Tasks/Workflow capabilities.
- CRM remains usable when PBX is disabled.
- Existing V1–V4 behavior remains regression-safe.

---

# V6 — Collaboration & Planning

## Goal

Provide a complete daily workspace for planning, scheduling and collaboration around work.

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
- Board view
- Calendar task view
- Gantt view
- Subtasks
- Recurring tasks
- Better dependency visualization

### Search
- Unified search foundation across tasks, projects, documents, correspondence, requests and employees

---

# V7 — Internal Collaboration, HR, Procurement & Resources

## Goal

Expand employee-oriented workflows and internal collaboration, then automate procurement and resource ownership using the shared platform backbone.

## Scope

### HR
- Employee records
- Employment information
- Organizational positions integration
- Employment documents
- Leave balances and policies
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

### Procurement
- Supplier management
- RFQ
- Supplier quotations
- Purchase orders
- Approval workflow
- Procurement documents
- Procurement tasks
- Purchase requests should reuse the V2 request engine where applicable

### Inventory & Assets
- Warehouses
- Items
- Stock movements
- Inventory balances
- Stock requests
- Asset registry
- Asset assignment
- Maintenance
- Asset history
- Asset documents
- Approval workflows

## Architectural Rule
HR, Procurement, Inventory and Assets must reuse Workflow, Documents, Cartable and Audit rather than creating parallel approval/document systems.

---

# V8 — Intelligence, Automation & Integrations

## Goal

Automate cross-module work and add operational visibility after the foundational modules and their contracts are stable.

## Scope

### Reporting & Analytics
- Operational reports
- Task performance
- Workflow bottlenecks
- Department performance
- SLA reports
- Correspondence reports
- CRM reports
- PBX reports
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

### PBX ↔ CRM Integration
- Incoming call → customer lookup
- Open the matched customer profile when a match is found
- Save call history against the customer
- Link calls to tasks where appropriate
- Show call notifications in the relevant internal workspace
- Keep integration event-driven and optional so PBX and CRM remain independently usable

## V8 Acceptance Gate
- Cross-module automation uses explicit events/contracts and is auditable.
- Reports respect each source module's authorization boundaries.
- PBX/CRM integration can be disabled independently of both modules.
- Retry and failure handling are defined for asynchronous integrations.

---

# V9 — Production & Enterprise Readiness

## Goal

Prepare the platform for production deployment and long-term operation.

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

# Current Position — 2026-10-13

**Current version: V2 — Office Automation**

**V1 implementation status:** complete.

**V1 technical validation:** the project owner confirmed that the full backend suite passed (380 tests) and the frontend production build succeeded.

**V1 manual acceptance:** any remaining visual/functional acceptance notes remain tracked in the status log. V2 kickoff is explicitly authorized by the project owner; remaining V1 acceptance items must not be silently dropped, and V1 regression coverage remains mandatory.

**V2 implementation status:** started. The first implementation slice is the correspondence foundation, beginning with domain boundaries and the draft/registration data model.

## V2 delivery order

1. Inspect existing Workflow, Documents, Cartable, Notifications and Audit capabilities and define correspondence integration contracts.
2. Correspondence foundation: incoming/outgoing/internal types, draft/registered lifecycle, registration numbering, authorization and audit.
3. Referrals, instructions, replies and correspondence attachments.
4. Correspondence ↔ Workflow and Correspondence ↔ Task integration.
5. Correspondence actions in Cartable plus notifications.
6. Forms & Requests using reusable Workflow + Cartable + Documents.
7. V2 acceptance: end-to-end journeys, authorization/regression tests, frontend build and manual Persian RTL acceptance.

The full Digital Archive product remains V3. V2 may create archive references and use existing document/version infrastructure, but must not grow into the V3 archive product.
# Implementation Order

The implementation order is version-based:

1. **V1 — Foundation & Work Management**
2. **V2 — Office Automation (Correspondence and Forms & Requests)**
3. **V3 — Digital Archive**
4. **V4 — Organizational Telephony (standalone PBX)**
5. **V5 — CRM & Commercial Operations**
6. **V6 — Collaboration & Planning**
7. **V7 — Internal Collaboration, HR, Procurement & Resources**
8. **V8 — Intelligence, Automation & Integrations (including PBX ↔ CRM)**
9. **V9 — Production & Enterprise Readiness**

PBX is intentionally scheduled before CRM-dependent telephony features. V4 must be independently usable; customer lookup, customer-linked call history and other PBX ↔ CRM behavior are delivered in V8 after both modules exist.

Within each version, implementation remains incremental:

**Inspect → Design/Boundary Review → Smallest Coherent Change → Test → Build → Manual Acceptance → Documentation → Next Item**

Do not start unrelated later-version scope merely because it appears in this document. Work proceeds from the active version's acceptance criteria. V2 kickoff is explicitly authorized; remaining V1 manual-acceptance notes remain tracked separately.

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
- وظیفه فقط یک مسئول اصلی دارد و رابطه چندنفره «افراد مرتبط/درگیر» در UI وجود ندارد.
- اطلاعات پروژه، پیوست‌ها و پیگیری خارجی در ستون کناری و گزارش فعالیت، خط زمانی، نظرات و یادآوری‌ها در بخش اصلی سازمان‌دهی شدند.


### 2026-10-07 — V1 Task Activities
- مسئول اصلی وظیفه یک نفر باقی می‌ماند؛ فعالیت‌ها و ارجاعات از مسئول اصلی مستقل نمی‌شوند.
- فعالیت بعدی به‌جای ستون ثابت روی `tasks` به‌صورت جدول مستقل `task_activities` پیاده‌سازی شد تا فعالیت‌های برنامه‌ریزی‌شده، فعالیت‌های جانبی و سابقه انجام‌شده قابل نگهداری باشند.
- ایجاد وظیفه اکنون می‌تواند اولین فعالیت را دریافت کند و صفحه جزئیات امکان ثبت فعالیت جدید و تکمیل فعالیت بعدی را فراهم می‌کند.


### 2026-10-07 — Task detail model correction
- وظیفه فقط یک مسئول اصلی دارد و «افراد مرتبط» در UI وظیفه وجود ندارد.
- «ارتباط و پیگیری بیرونی» برای سازمان/واحد/شخص خارج از شرکت استفاده می‌شود.
- «فعالیت‌های بعدی» به‌صورت لیست مستقل با ثبت انجام و زمان تکمیل پیاده‌سازی شد.
- فعالیت بعدی مسئول جداگانه ندارد و مسئول آن همان مسئول اصلی وظیفه است.

### 2026-10-07 — Task detail information layout correction
- جزئیات وظیفه ساده‌سازی شد: «اقدام بعدی» حذف و «فعالیت‌های بعدی» مرجع ثبت فعالیت‌های برنامه‌ریزی‌شده باقی ماند.
- اطلاعات پروژه اکنون مسئول واحد، مسئول وظیفه، شروع، مهلت و توضیحات را نیز نمایش می‌دهد.
- ارتباط و پیگیری بیرونی از ویرایش وظیفه مستقل شد و از بخش اختصاصی خودش مدیریت می‌شود.

### 2026-10-07 — Task referral history build fix
- مدل TypeScript صفحه جزئیات وظیفه با `assignments` هماهنگ شد تا سوابق ارجاع وظیفه در build به‌درستی type-check شوند.

### 2026-10-07 — Task referral/external controls correction
- دسترسی «ارجاع وظیفه» برای مسئول اصلی، مدیر واحد و مدیر پروژه در صورت داشتن مجوز و دسترسی وظیفه فعال شد.
- دکمه «ارجاع وظیفه» در بخش «سوابق ارجاع وظیفه» نمایش داده می‌شود.
- بخش «ارتباط و پیگیری بیرونی» دکمه‌های «ثبت ارتباط / ویرایش ارتباط» و «حذف ارتباط» را مستقیماً در صفحه جزئیات نمایش می‌دهد.

**Status:** implementation complete; frontend build and focused backend validation pending.


### V1 validation correction — Task detail controls
- Referral remains a contextual task action and is authorized through task update access plus assignee/department-manager/project-manager context.
- External follow-up save/display is validated at the HTTP layer and the detail page refreshes its task data after save.


### V1 validation correction — Referral permission
- Any user who has `tasks.refer` and has scoped access to the task can see and use task referral; contextual manager/assignee restrictions are not imposed by the policy.


### 2026-10-07 — Task referral permission migration fix
- برای اطمینان از وجود `tasks.refer` در دیتابیس‌های موجود و دیتابیس تست، migration مستقل ایجاد/به‌روزرسانی این مجوز اضافه شد.
- مدل دسترسی ارجاع همچنان بر پایه مجوز اختصاصی `tasks.refer` و scope وظیفه باقی می‌ماند.

**Status:** implementation complete; focused TaskHttpTest validation pending.


### 2026-10-07 — Task referral member selection correction
- در ارجاع وظایفِ پروژه‌های پیش‌فرض واحد، تمام اعضای واحد به‌عنوان مسئول جدید قابل انتخاب هستند؛ محدودیت backend نیز با همین مدل هم‌راستا شد.
- عنوان کاربرپسند منوی «کار و عملیات» از «تسک‌ها» به «وظایف» اصلاح شد.
- خطای مسئول جدید در پنجره ارجاع به‌صورت مستقیم نمایش داده می‌شود.

**Status:** implementation complete; focused TaskHttpTest validation pending.


### 2026-10-07 — Task detail relation serialization fix
- مشخص شد داده‌های رابطه‌ای Eloquent در پاسخ Inertia با کلیدهای snake_case (`external_organization`, `external_department`, `external_contact`, `from_user`, `to_user`, `assigned_by`) به Vue می‌رسند، در حالی که صفحه جزئیات با نام‌های camelCase آن‌ها را می‌خواند.
- صفحه جزئیات وظیفه اصلاح شد تا «ارتباط و پیگیری بیرونی» بعد از ذخیره واقعاً نمایش داده شود و سوابق ارجاع نیز نام فرستنده، گیرنده و ثبت‌کننده را درست نشان دهند.

**Status:** code fix committed; frontend build validation pending.


### 2026-10-07 — Multiple external contacts for tasks
- «ارتباط و پیگیری بیرونی» در V1 برای یک وظیفه از یک شخص خارجی به چند شخص خارجی توسعه یافت.
- سازمان و واحد همچنان context وظیفه هستند؛ اشخاص خارجی از طریق رابطه مستقل task_external_contacts نگهداری می‌شوند.
- در صفحه جزئیات، هر شخص خارجی با اطلاعات تماس خود در یک کارت مستقل نمایش داده می‌شود.

**Status:** implementation complete; focused TaskHttpTest validation pending.


### 2026-10-07 — Task external relation UI correction
- انتخاب چند شخص خارجی در صفحه وظیفه با toggle صریح اصلاح شد تا انتخاب شخص جدید، شخص قبلی را جایگزین نکند.
- کارت‌های اشخاص در خود بخش «ارتباط و پیگیری بیرونی» صفحه وظیفه قرار گرفتند و اطلاعات سازمان، واحد و اطلاعات تماس نمایش داده می‌شود.
- دکمه اصلی «افزودن ارتباط» است و هر کارت، در صورت دسترسی، عملیات «ویرایش ارتباط» و «حذف ارتباط» دارد.


### 2026-10-07 — اصلاح نهایی ارتباط و پیگیری بیرونی وظیفه
- ارتباط‌های بیرونی وظیفه در جدول `task_external_contacts` به‌صورت چندتایی نگهداری می‌شوند و افزودن ارتباط جدید، ارتباط‌های قبلی را جایگزین نمی‌کند.
- ویرایش هر ارتباط فقط همان رکورد را جایگزین می‌کند و حذف نیز فقط همان ارتباط را حذف می‌کند.
- صفحه جزئیات وظیفه هر ارتباط را در یک کارت مستقل با اطلاعات کامل و دکمه‌های ویرایش/حذف داخل همان کارت نمایش می‌دهد؛ حذف کلی بیرون کارت حذف شد.
- تست HTTP سناریوی افزودن چند ارتباط به‌صورت مرحله‌ای، ویرایش و حذف ارتباط را پوشش می‌دهد.


### 2026-10-07 — اصلاح نمایش سازمان/واحد ارتباط‌های بیرونی وظیفه
- علت مشخص شد: ستون‌های سازمان/واحد در pivot ذخیره می‌شدند، اما رابطه `Task::externalContacts()` آن دو فیلد pivot را در خروجی Eloquent بارگذاری نمی‌کرد؛ بنابراین UI به مقدار مشترک سطح وظیفه fallback می‌کرد و همه کارت‌ها یک سازمان/واحد نشان می‌دادند.
- رابطه اصلاح شد تا `external_organization_id` و `external_department_id` از pivot همراه هر ارتباط بارگذاری شوند.


### 2026-10-07 — تفکیک کامل سازمان/واحد ارتباط‌های بیرونی
- سازمان و واحد ارتباط بیرونی دیگر هنگام ذخیره روی خود وظیفه به‌عنوان مقدار مشترک به‌روزرسانی نمی‌شوند.
- هر شخص ارتباطی، سازمان و واحد مخصوص خودش را در Pivot `task_external_contacts` نگه می‌دارد و نمایش کارت فقط از همین context استفاده می‌کند؛ fallback به مقدار مشترک Task حذف شد.
- تست HTTP برای وجود هم‌زمان ارتباط‌ها با سازمان/واحدهای متفاوت اضافه شد.


### 2026-10-07 — تکمیل دسترسی ثبت نظر و یادآوری در جزئیات وظیفه
- در صفحه جزئیات وظیفه، دکمه‌های «ثبت نظر» و «ثبت یادآوری» به خود بخش‌های مربوطه اضافه شدند و بر اساس Ability نمایش داده می‌شوند.
- مدل داده و روابط فعلی `comments` و `reminders` از نوع `HasMany` هستند؛ بنابراین ثبت چندین نظر و چندین یادآوری برای یک وظیفه پشتیبانی می‌شود و محدودیت تک‌موردی ایجاد نشده است.

### 2026-10-07 — Task reminder date/time picker correction
- انتخاب تاریخ و ساعت یادآوری به جریان مرحله‌ای تاریخ → ساعت → دقیقه → تأیید تغییر کرد.
- پنجره انتخاب تا زمان تأیید باز می‌ماند و مقدار انتخابی قبل از تأیید به فرم والد منتقل نمی‌شود.
- مقدار یادآوری با زمان محلی بدون تبدیل ضمنی timezone ارسال می‌شود تا اختلاف ساعت بین انتخاب و نمایش ایجاد نشود.

**Status:** implementation complete; frontend build validation pending.

### 2026-10-07 — Task comments/reminders edit-delete + time preservation
- امکان ویرایش و حذف رکوردهای نظر و یادآوری در صفحه جزئیات وظیفه اضافه شد؛ مدل داده همچنان چندرکوردی است.
- `TaskReminder.remind_at` با فرمت صریح `Y-m-d H:i:s` serialize می‌شود تا زمان انتخاب‌شده در رابط کاربری بدون تبدیل UTC نمایش داده شود.
- تست HTTP برای زمان دقیق یادآوری و عملیات ویرایش/حذف اضافه شد.

**Status:** implementation complete; focused task test pending.

### 2026-10-07 — تکمیل Routeهای ویرایش/حذف یادآوری وظیفه
- Routeهای `tasks.reminders.update` و `tasks.reminders.destroy` که Controller و تست HTTP به آن‌ها وابسته بودند، به مسیرهای وظایف اضافه شدند.
- Migration مستقل Permission `tasks.create_reminder` نیز در دیتابیس پروژه وجود دارد.

**Status:** implementation complete; focused TaskHttpTest validation pending.

### 2026-10-07 — اصلاح UI یادآوری وظیفه
- ترتیب انتخاب ساعت/دقیقه در پنجره زمان با RTL اصلاح شد تا «ساعت» در سمت چپ و «دقیقه» در سمت راست قرار بگیرد.
- در کارت هر یادآوری، عملیات «ویرایش» و «حذف» به‌صورت مستقیم اضافه شد و از Routeهای اختصاصی ویرایش/حذف استفاده می‌کند.

**Status:** implementation complete; focused TaskHttpTest validation pending.


### 2026-10-07 — Task work log action button
- در صفحه جزئیات وظیفه، بخش «گزارش فعالیت» به دکمه «ثبت فعالیت» مجهز شد تا ثبت Work Log از همان بخش قابل دسترسی باشد و نمایش آن بر اساس مجوز ثبت فعالیت انجام شود.


### 2026-10-10 — Task activity lifecycle and edit/delete
- فعالیت برنامه‌ریزی‌شده بعد از تکمیل از فهرست فعال حذف نمی‌شود؛ در زیربخش «فعالیت‌های انجام‌شده» همراه با زمان تکمیل نمایش داده می‌شود.
- ویرایش و حذف هم برای فعالیت‌های در انتظار انجام و هم فعالیت‌های تکمیل‌شده در دسترس است.
- کنترل‌های frontend با Routeهای اختصاصی backend و بررسی مجوز/تعلق فعالیت به وظیفه هماهنگ شدند.

### 2026-10-10 — Separate pending activities from completed activity log
- «فعالیت‌های بعدی» فقط فعالیت‌های برنامه‌ریزی‌شده و در انتظار انجام را نشان می‌دهد.
- با ثبت «انجام شد»، رکورد به نوع `logged` تغییر می‌کند و در بخش «گزارش فعالیت» نمایش داده می‌شود؛ دیگر در فهرست فعالیت‌های بعدی باقی نمی‌ماند.
- فعالیت‌های منتقل‌شده به گزارش، دکمه‌های ویرایش و حذف را در همان بخش دارند و زمان تکمیل حفظ می‌شود.
- تست HTTP تکمیل فعالیت برای تغییر نوع رکورد به `logged` به‌روزرسانی شد.

**Status:** code committed; focused task test and frontend build validation pending.

### 2026-10-10 — Activity report CRUD completion
- گزارش‌های ثبت‌شده در بخش «گزارش فعالیت» اکنون شناسه رکورد را در timeline دارند و دکمه‌های ویرایش/حذف در همان کارت نمایش داده می‌شوند.
- Route و عملیات backend برای ویرایش/حذف `TaskWorkLog` اضافه شد؛ کنترل تعلق گزارش به همان وظیفه و مجوز ویرایش بررسی می‌شود.
- تست HTTP برای ویرایش و حذف گزارش فعالیت اضافه شد.

**Status:** implementation committed; focused task test and frontend build validation pending.

### 2026-10-10 — Fix completed activity transfer visibility
- تکمیل «فعالیت بعدی» اکنون در یک تراکنش، گزارش واقعی در `task_work_logs` ایجاد می‌کند و سپس رکورد برنامه‌ریزی‌شده را حذف می‌کند؛ بنابراین فعالیت در همان فهرست «گزارش فعالیت» نمایش داده می‌شود و دکمه‌های ویرایش/حذف همان بخش را دارد.
- تست HTTP به‌جای بررسی تغییر نوع رکورد، ایجاد گزارش فعالیت و حذف رکورد برنامه‌ریزی‌شده را بررسی می‌کند.

**Status:** fix committed; focused task test pending.


### 2026-10-10 — Unified task activity model and UI guidance
- `task_activities` منبع واحد فعالیت‌های وظیفه است؛ `planned/pending` برای فعالیت‌های بعدی و `logged/completed` برای گزارش فعالیت انجام‌شده استفاده می‌شود.
- تکمیل فعالیت برنامه‌ریزی‌شده همان رکورد را تغییر می‌دهد؛ داده‌های جدول قدیمی `task_work_logs` با migration داده‌محور منتقل می‌شوند.
- در صفحه جزئیات، عنوان دکمه «ثبت فعالیت انجام‌شده» است؛ راهنمای دائمی تفاوت دو بخش و راهنمای هاور روی ثبت/تکمیل فعالیت وجود دارد.

**Status:** implementation committed; focused task HTTP test and frontend build pending. V1 remains in validation; V2 scope unchanged.


### 2026-10-10 — Task activity report visibility correction
- «گزارش فعالیت» مستقیماً از رکوردهای `task_activities` با نوع `logged` و وضعیت `completed` ساخته می‌شود؛ فهرست به timeline عمومی وابسته نیست.
- راهنمای تفاوت دو نوع ثبت در بالای «فعالیت‌های بعدی» نمایش داده می‌شود.
- تست صفحه جزئیات برای اطمینان از نمایش فعالیت انجام‌شده‌ی قبلاً ثبت‌شده اضافه شد.
- مدل یکپارچه‌ی فعالیت‌ها و محدوده V1 تغییری نکرده‌اند.

### 2026-10-10 — Task activity guide layout correction
- راهنمای فعالیت‌ها در صفحه جزئیات وظیفه به سکشن مستقل و تمام‌عرض زیر چیدمان دو‌ستونه منتقل شد؛ عنوان «فعالیت‌های بعدی» و دکمه ثبت آن بدون متن راهنما در کنار هم باقی می‌مانند.


### 2026-10-10 — Reminder behavior audit
- یادآوری‌های وظیفه در حال حاضر در دیتابیس ذخیره و از صفحه وظیفه مدیریت می‌شوند.
- در کد بررسی‌شده، Scheduler/Command برای یافتن یادآوری‌های سررسیدشده و منطق نمایش پاپ‌آپ یا اعلان فعال در رابط کاربری وجود ندارد؛ بنابراین رسیدن زمان یادآوری به‌تنهایی به کاربر هشدار نمی‌دهد.
- راهنمای دائمی فعالیت‌ها بنا به درخواست کاربر از صفحه جزئیات حذف شد.

### 2026-10-10 — Task activity guide full-width placement
- راهنمای فعالیت‌ها به سکشن مستقلِ تمام‌عرض زیر چیدمان دو‌ستونه جزئیات وظیفه منتقل شد تا متن راهنما در فضای جداگانه و خوانا نمایش داده شود.
- این تغییر صرفاً چیدمان رابط کاربری است و محدوده V1/V2 یا مدل داده را تغییر نمی‌دهد.


### Shared Project / Sections implementation checkpoint — 2026-10-10
The development branch now contains the first implementation of project sections, shared-project migration/seeding, department-to-section provisioning, section-scoped task creation, manager-first referral history, and general/section document uploads. This extension is **pending local test-suite, migration, and frontend-build validation**; it is not yet accepted as complete.


### 2026-10-13 — V2 kickoff authorized
- Roadmap current position advanced to V2 — Office Automation by explicit project-owner instruction.
- V2 starts with correspondence foundation and reuses existing Workflow, Documents, Cartable, Notifications and Audit capabilities.
- The full Digital Archive product remains V3; V2 only uses existing document/version infrastructure and correspondence archive references.
- PBX remains V4 before CRM V5. V4 is standalone; PBX ↔ CRM customer lookup/history remains deferred until both modules exist.
- V1 full-suite validation (380 tests) and frontend production build were reported passing by the project owner; remaining manual-acceptance notes stay tracked and V1 regression coverage remains required.


### 2026-10-13 — V2 correspondence foundation started
- The first code slice is committed: correspondence model/schema and transactional draft creation service.
- Focused tests cover incoming drafts, outgoing/internal types, unsupported type rejection and blank subjects.
- Local migration/test validation is pending; registration-number allocation, routes/UI, authorization and workflow/Cartable integration are not yet complete.


## 2026-10-14 — V2 correspondence configuration and numbering

The first configuration/registration slice is implemented in `development`:
- Organization settings support the three numbering presets plus a custom token order, separator and sequence padding.
- Writing method is configurable as internal editor, Word upload, or both.
- Approval policy is configurable separately for incoming, outgoing and internal correspondence.
- Numbering uses a database counter row locked inside the registration transaction. Registration assigns the immutable official number and registrar metadata only when the draft can be registered.
- Local migration, focused tests, frontend build and manual RTL acceptance are still pending.
- HTTP correspondence screens and editor/Word-upload integration are implemented on development; local test/build validation remains pending.


### 2026-10-21 — Correspondence HTTP foundation
- Added the first usable HTTP/UI slice for V2 correspondence: permission-protected list, create-draft and register actions, Persian RTL list/form, server-side filters and pagination.
- The registration action reuses the existing numbering/approval service; official numbers remain assigned only during final registration.
- Added view/create/register permissions and focused HTTP regression tests.

Status: correspondence list/draft/registration HTTP flow and editor/file/both composition modes are committed to development. Local tests and frontend build are pending; correspondence detail/edit and deeper workflow/Cartable integration remain incomplete.


## 2026-10-31 — Correspondence personal ownership and action filters

- Added a second independent filter to the correspondence list: «همه مکاتبات»، «نیاز به اقدام»، and «اقدام قبلی ثبت‌شده توسط من». It composes with the existing «مکاتبات من / مکاتبات واحد من / همه مکاتبات مجاز» scope and preserves other search filters and pagination query parameters.
- «نیاز به اقدام» is based on the current assignment (current_user_id equals the signed-in user). «اقدام قبلی ثبت‌شده توسط من» requires both a historical referral addressed to that user (correspondence_actions.to_user_id) and an action authored by that user (actor_id), and excludes letters currently assigned back to that user so pending work takes precedence.
- Corrected the «مکاتبات من» ownership scope: an action authored by the user is no longer sufficient by itself. Historical referrals addressed to the user remain included; merely referring a letter to another user/department does not make it the referrer's personal correspondence.
- Added feature regression coverage for pending referrals, previously acted-on referrals, and letters merely referred onward by the current user. No database migration or workflow authorization changes.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master remains unchanged.


## 2026-11-01 — Formalized correspondence-driven project workflow scope

- Added `CORRESPONDENCE_PROJECT_WORKFLOW.md` as the functional source of truth for Project Management's four employee requests, separate manager decisions, per-project response ownership, response Section/Task creation and generic task-correspondence linking.
- This is a documentation/status correction, not a feature-completion claim. The existing task-to-new-correspondence entry point is only a first slice. The project-specific approval flow and project/response-task/source-letter propagation remain pending.
- Stage-by-stage implementation status and acceptance criteria are maintained in the new reference document. No tests or frontend build were run for this documentation-only change; `master` unchanged.


## 2026-11-01 — Project Management request review: first implementation slice

- Added four employee request actions with mandatory feedback and two separate manager decisions. Employee request does not perform final routing or project creation.
- Manager rejection returns to the same employee; manager approval routes to the selected department, returns the letter to its original recipient department for approved reject/return requests, or sets `project_request_approved` for the next project-creation phase.
- Added manager cartable/index visibility, UI controls and two focused HTTP regression tests.
- **Status:** initial implementation committed on `development`; focused tests and frontend build await local validation. Project creation/response task integration remains pending. `master` unchanged.


## 2026-11-01 — Stage 1 validated; Stage 2 is next

- Project owner confirmed that the focused test run passed and the frontend production build succeeded for the Project Management request/review flow.
- Stage 1 is accepted. Next implementation target is Stage 2: create a real project from a correspondence in `project_request_approved`, require an explicitly selected response department (pre-filled from an organization default that remains editable), and link the project to the source letter.
- Stage 3 response Section/task provisioning and Stage 4 bidirectional task/correspondence links remain separate follow-up stages. `master` unchanged.


## 2026-11-01 — Initial approved-project creation slice

- Added a transaction-backed path from an approved incoming letter to a new project, one response Section and one response task for the selected active department. The source correspondence is linked to the project and task.
- Organization default response-department setting and bidirectional detail links remain pending. Migration, focused HTTP tests and frontend build validation are pending.

## 2026-11-02 — اصلاحات گردش تأیید نامه وارده و کارتابل ایجاد پروژه

- [x] ثبت تصمیم: وقتی تنظیم نامه وارده روی «نیاز به تأیید» است، تأیید نهایی مرحله پیش‌نویس بر عهده مدیر واحد دبیرخانه است؛ کارمند نباید این تأیید را دور بزند.
- [x] پیاده‌سازی اولیه: تخصیص پیش‌نویس وارده به مدیر دبیرخانه تنظیم‌شده، نمایش پیش‌نویس برای مدیر و اقدام پیش‌فرض «تأیید مدیر».
- [x] پیاده‌سازی اولیه: پس از تأیید مدیر، واگذاری ثبت نهایی به ایجادکننده پیش‌نویس در دبیرخانه.
- [x] پیاده‌سازی اولیه: نمایش نامه دارای وضعیت `project_request_approved` در «نیاز به اقدام» مدیر واحد جاری.
- [x] تست Feature برای تأیید مدیر روی نامه وارده و کارتابل درخواست پروژه تأییدشده اضافه/اصلاح شد.
- [ ] اجرای تست‌های هدفمند و کل مجموعه `php artisan test`، build فرانت‌اند و پذیرش UI در محیط محلی.
- [ ] اعتبارسنجی SQL Server و سناریوهای ثبت مستقیم/نیازمند تأیید روی تنظیمات واقعی سازمان.
- وضعیت: کد و مستندات در شاخه `development` به‌روزرسانی شده‌اند؛ تست و build هنوز اجرا/تأیید نشده‌اند. شاخه `master` تغییر نکرده است.


### Correspondence letter-template designer — 2026-11-04 status

First implementation slice exists: a settings-managed A4 text-box editor with move/resize, content editing, visibility, typography/alignment controls, and typed token insertion; saved JSON is rendered in first-page print preview. Remaining work before acceptance: image elements for stamp/signature, explicit recipient/body/footer zones, robust multi-page header/footer behavior, visual guides/snapping/zoom, token completeness and output verification, focused tests, frontend build and manual print/PDF review. This is in progress, not complete.


### 2026-11-04 — Settings UI update

Organization Settings now uses three tabs. Organization letterhead upload is separate from the stamp and can be positioned through the template editor. Validation is pending.


## 2026-11-07 — اصلاح جابه‌جایی و محدوده مختصات طراح قالب نامه

- در طراح قالب، هنگام Drag مختصات افقی و عمودی به دو رقم اعشار گرد می‌شوند و محاسبه با ابعاد واقعی صفحه A4 و کنترل محدوده صفحه انجام می‌شود.
- مقدار مجاز عرض اکنون با فاصله افقی المان هماهنگ است؛ با تغییر دستی عرض یا موقعیت نیز مختصات به محدوده معتبر بازگردانده می‌شوند.
- در زمان ذخیره، مختصات و عرض سمت سرور تا دو رقم اعشار نرمال می‌شوند و تلورانس بسیار کوچک ممیز شناور مانع رد شدن مقدار مرزی ۱۰۰٪ می‌شود؛ خروج واقعی از محدوده همچنان رد می‌شود.
- تست PHP و build پس از pull توسط کاربر اجرا و تأیید می‌شوند. شاخه development؛ master و سرور بدون تغییر.

- تست Feature رگرسیون برای مختصات نزدیک لبه A4 اضافه شد تا گردکردن ممیز شناور و پذیرش مختصات مرزی بررسی شود.

- اصلاح تست مختصات مرزی: JSON ممکن است اعداد صحیحِ گرد‌شده را به‌صورت integer بازگرداند؛ assertionها اکنون برابری عددی را بررسی می‌کنند و به تفاوت نوع integer/float وابسته نیستند.


## 2026-11-07 — Manual attachment page count and draggable stamp/signature

- Added nullable `correspondences.attachment_pages` with a reversible migration. Correspondence create/edit captures a manually entered page count (1–9999); the value is validated, persisted, returned to the print view, and covered by a correspondence HTTP regression assertion. Blank means no attachment pages are declared.
- Added the `{{attachment_pages}}` metadata variable and included it on the default metadata line immediately below the date. Printed text is «پیوست: دارد (N صفحه)» or «پیوست: ندارد» when the field is empty.
- Fixed the designer interaction path for issuer assets: stamp/signature elements now receive pointer selection and dragging, use their actual canvas coordinates, and their image pixels no longer intercept pointer input. Print output applies the stored x/y position to these elements.
- Owner validation remains pending: migration, focused correspondence HTTP test, frontend production build, and manual A4/drag acceptance. Development only; master/server untouched.
