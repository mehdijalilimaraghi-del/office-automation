## 2026-11-11 — Context-tab pointer timing and dimension readback correction

- Follow-up inspection identified a timing defect in contextual tab navigation: pinning selection in the tab click handler occurs after pointer focus has already moved. A capture-phase pointerdown handler now snapshots the editor range and pins a still-connected selected image, shape, or table cell before focus/selectionchange can run. This applies uniformly to all three object types.
- Dimension fields now prefer the element's authored CSS width/height (converted to millimetres) rather than only offsetWidth/offsetHeight. Rendered dimensions remain the fallback when no explicit CSS dimension exists; this avoids readback values masking the authored setting due to layout constraints and rounding.
- The earlier table sizing logic still needs browser acceptance, especially table/column sizing with merged cells and content-driven row heights. Do not mark dimension editing or contextual tabs complete until image, shape and table are each tested in the browser. Build/browser verification pending. Development branch only.

## 2026-11-11 — Correspondence editor contextual-tab and dimension follow-up

- The contextual toolbar now pins the selected image/table/shape while switching tabs, instead of relying only on browser focus/selectionchange timing. A real pointer interaction inside the editable document releases the pin so selecting a different object refreshes context. The tab strip no longer suppresses native pointer focus; the original selection is captured before switching.
- Dimension controls now resolve the selected table/cell/image/shape from connected element references with a selection-range fallback, so a stale transient ref does not silently make the setter a no-op. Table width uses fixed layout and removes cell max-width constraints; table height also applies a distributed row-height target because CSS table height alone is only a minimum.
- This is a code-level correction, not a verified acceptance. Frontend build and browser checks for tab persistence, image/shape/table width and height, and content persistence are still pending. Development branch only; master, release and server unchanged.

## 2026-11-09 — Print uses the preview's pre-paginated A4 sheets

- Root cause confirmed in `Print.vue`: screen preview renders fixed A4 sheets, each with its own physical coordinate plane and a clipped body slice translated by available content height. The previous print CSS hid every sheet after the first and let Chromium reflow the full body. That was a different layout algorithm, so letterhead, repeating artwork, first-page elements, issuer/signature flow and page counters could not reliably match preview.
- Replaced that divergent print path: `@page` is A4 with zero page margins; every existing `.paper` sheet prints at 210 × 297 mm with an explicit page break. Coordinate plane, letterhead background, repeating elements, page number, content window and translated body slice retain the same absolute positioning and clipping model as screen preview. Chromium no longer repaginates the letter body.
- Page count and slicing still depend on the existing measured-height estimate. Long content, font loading, images and print scaling require actual PDF validation; any mismatch must be addressed in the estimator, not by returning to browser reflow.
- The earlier letterhead-only fixed-position correction is superseded by this unified page-sheet approach. Build, focused PHP tests and PDF acceptance are pending. Development only; master/release/server unchanged.

## 2026-11-09 — Correct letterhead physical-page geometry in Chromium print

- Image comparison exposed a concrete geometry defect: the letterhead background used `position: fixed` but retained the screen rule's `inset: 0`. In Chromium print, the fixed containing area is the `@page` content box after the configured top/bottom margins, so the full-page letterhead image was being anchored and scaled to the reduced content rectangle. This shifted the logo downward and displaced/scaled the artwork at the bottom.
- Updated only the print rule for `.letterhead-background`: explicitly anchor it at physical page top by subtracting the effective top reserved zone from the fixed content-box origin, and set its physical dimensions to 210 × 297 mm. Override all inherited inset edges so the image is no longer sized to the reduced content box.
- This is a targeted correction to the confirmed letterhead defect, not a claim that all print/preview differences are solved. Repeating elements, first-page metadata/heading, flowing body, issuer/signature flow, and page numbering still require comparison in a real Chromium-generated PDF.
- Frontend build, focused PHP tests, and manual PDF acceptance have not yet been run. Development branch only; master/release/server unchanged.

## 2026-11-09 — Print coordinate follow-up: repeating elements and page counter

- The print page now emits one explicit A4 `@page` rule with only the configured effective top/bottom reserved zones and zero CSS side margins; horizontal text margins remain applied once to the flowing document box. This is intended to prevent a second browser-side horizontal margin from being compounded with template margins. The browser/printer's non-printable hardware area remains outside CSS control.
- Repeating header/footer/legacy-repeat elements now receive physical A4 millimetre coordinates for print: their Y coordinate is translated from physical page top by the effective top reserved zone, and X/width are expressed in millimetres against the full 210mm page width.
- Page numbering no longer uses CSS page-margin boxes, which could only snap to a top/bottom margin segment. The print counter is a fixed repeated element positioned from the saved page_number element's physical A4 X/Y/width; its displayed value uses paged-media page/pages counters and the configured format/typography.
- First-page meta/heading elements and the body-flow safe area retain their existing distinct roles. This is an implementation iteration, not yet accepted: frontend build, focused PHP tests, and Chromium PDF comparison remain pending. Specifically verify default vs minimum print-dialog margins, header/footer clearance, page-number X/Y, and multi-page repeat behavior.
- Development only; master/release/server unchanged.

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

## Correspondence print rendering — physical A4 coordinate model (2026-11-09)

The browser-native print renderer uses two coordinated layers with one physical page origin:

- **Physical template plane:** 210 × 297 mm, positioned against the physical A4 page origin for absolute metadata, heading, repeating header/footer and other template elements.
- **Flowing text plane:** natural document flow for browser pagination. Its horizontal box spans 210 mm and applies the configured left/right text margins once as padding. Its vertical start/end are constrained by `@page` top/bottom margins, each equal to the greater of the text-edge margin and reserved header/footer height.

Horizontal `@page` margins must remain zero in this model; applying them as well as flow-box padding would create a second margin layer and shrink/shift the coordinate plane. The physical template plane is vertically offset from the `@page` content origin by the effective top safe area. Body content remains in normal flow so Chromium can fragment it across pages; repeating template elements remain page-positioned.

The screen A4 preview and browser PDF still require explicit visual acceptance. Browser-specific pagination and fixed-element behavior must not be treated as verified solely from CSS or PHP tests.

## 2026-11-09 — Outgoing dispatch stage and recipient title prefix

- The existing external tracking columns are reused; no duplicate schema was introduced. Outgoing letters in `awaiting_dispatch` are now included in the `needs_action` state filter for all active Secretariat members, fixing the hand-off that previously made the newly registered outgoing letter disappear from the actionable list.
- Final dispatch now requires both a dispatch method and a sent date while the letter is in `awaiting_dispatch`. The workflow service independently enforces the same invariant before recording `dispatched`; external registration number/date remain optional tracking metadata. While awaiting dispatch, only Secretariat members/system administrators may edit this stage; creator/task-owner tracking permissions resume outside that state.
- Added `{{recipient_organizational_title_prefix}}` to the addressing element token allow-list, designer token catalog/sample values, and print resolver. It reads the immutable `organizational_title_prefix` captured from the selected external contact at registration.
- Frontend build, focused correspondence tests, and manual Secretariat workflow verification are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Recipient personal title prefix token

- Added `{{recipient_personal_title_prefix}}` to the addressing/heading element token catalog. It resolves from the external contact's immutable `recipient_addressing_snapshot.personal_salutation` value in print, independently of `{{recipient_title}}` (which continues to combine personal salutation and professional title for backward compatibility).
- No database migration is needed; the personal salutation is already captured in the recipient addressing snapshot. Build and correspondence regression tests are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Rich-text editor and appendix print architecture

- Added `CorrespondenceRichTextEditor.vue` as a shared editor for main-letter body and appendix body. It uses DOM Selection/Range operations and emits HTML through `v-model`; it does not use `document.execCommand` or introduce an npm dependency. Contextual controls are shown for table, image, and shape selections.
- Server-side `sanitizeBody` now permits a constrained set of rich-text tags and formatting properties. Images are restricted to PNG/JPEG/WEBP base64 data URLs with a size ceiling; hyperlinks are restricted to HTTP(S)/mailto. Arbitrary event handlers, external image URLs, scripts, and unapproved styles are not retained.
- Correspondence schema now has `appendix_mode` and `appendix_body`. Appendix files are stored as document links with purpose `correspondence_appendix`, distinct from original incoming-letter scans and main-letter uploads.
- Print preview computes main and appendix page counts independently. Main content includes issuer/signature flow; appendix content starts on the next physical sheet and receives “پیوست ـ صفحه N از M” numbering. Automatic attachment summary combines measured text pages and linked appendix-file count.
- Keep all physical output in the existing preview-generated A4 sheet model. Any page-count mismatch must be corrected in measurement/layout code, never by allowing Chromium to repaginate the content independently.
- Verification remains pending: build, feature tests, interactive browser checks, and Chromium PDF comparison.


## 2026-11-10 — Focused correspondence editor usability improvements

- Kept the existing lightweight correspondence-body editor; no editor-engine migration or new npm dependency was introduced. The editor remains limited to the letter body/appendix body and does not own letterhead, headers, footers, stamps, signatures, or A4 pagination.
- Replaced text-character toolbar symbols with consistent Lucide icons from the already-installed `@lucide/vue` package, using Word-familiar visual metaphors for formatting, alignment, lists, undo/redo, tables, images and shapes.
- Expanded contextual table controls: table width, cell background, border color/thickness, cell padding, and horizontal/vertical cell alignment, alongside existing row/column insertion/deletion.
- Expanded image controls with height and border presets, retaining width, alignment, replacement and removal.
- Expanded shape controls with width, height and border thickness, retaining fill/border colors and removal.
- This is a targeted UI/control improvement, not a replacement for a full Word editor. Advanced freeform resizing/positioning, multi-cell merge/split, and complex image text-wrapping are not included. Existing DOM Selection/Range behavior and HTML sanitization still require interactive verification.
- Verification pending: run `git pull origin development`, `npm run build`, then manually verify toolbar rendering, table/image/shape controls, save/reopen, and preview/print preservation. No build, PHP tests, or browser/PDF acceptance has been run for this change.
- Development branch only; master, release and server unchanged.


## 2026-11-10 — Correction: correspondence editor controls are still in progress

The prior 2026-11-10 entry overstated completion. The initial toolbar/control patch did NOT provide full Word-like table, image, and shape behavior. Do not treat that entry as acceptance or completed functionality.

Current incremental work on `development` adds: numeric millimetre inputs for table/image/shape dimensions; numeric cell width/height and padding; table/row/column selection and Shift-click rectangular cell selection; same-row cell merge and colspan split; border side targeting, border style/color/width; a fixed theme color swatch palette plus custom color input; image text-flow modes (inline, block, float left/right); and pointer-based cell edge resizing. These are implementation attempts only and have not been built or browser-tested.

Known gaps / acceptance blockers:
- Border targeting for inner grid lines and complex selections needs browser verification and may need refinement; the current “split cell” handles colspan, not rowspan or arbitrary multi-cell splitting.
- Table resizing currently acts on a cell edge and may not behave like Word's shared column-boundary drag in every table layout; verify and refine.
- Shape color palette and full Word-style contextual ribbon remain incomplete; native color input behavior is browser-dependent.
- Image wrapping and mm sizing need preview/print verification; float behavior may interact with letter pagination.
- Dragging table row/column boundaries, merge/split edge cases, selection persistence, and sanitized HTML round-trip are not accepted until manually tested.
- There is no claim of Word-equivalent behavior. Build, type-check, browser tests, save/reopen, and A4 preview/print comparison are all pending.

Next validation must start with `git pull origin development`, then `npm run build`. Do not move to master/release/server. Report actual build/test results before claiming completion.


## 2026-11-10 — Further editor implementation (not yet accepted)

The latest development commits further implement rectangular multi-cell selection (Shift-click), table/row/column selection, rectangular cell merge, colspan/rowspan split, per-side border formatting with outer/inner targeting, theme swatches plus custom colors for text/highlight/table cells/shapes, numeric mm sizing for table/image/shape and cells, image text-flow modes, and pointer-based shared table column/row boundary resizing.

These are code-level implementations only. The feature remains **in progress / unverified**. In particular verify: selection behavior across cells; merge/split with tables containing existing spans; border application to rectangular selections; boundary dragging in fixed and auto table layouts; numeric dimension persistence; color palettes in the actual browser; image float/wrapping effects on A4 preview and printed PDF; and HTML sanitization/save-reopen. The current implementation is not equivalent to the full Microsoft Word table engine and must not be described as such. No build, automated tests, or browser acceptance has been run.

## 2026-11-11 — Rich-text editor interaction layer refinement (unverified)

- The Vue editor now presents color controls as compact dropdown palettes rather than inline swatch strips. Current computed color is shown on each palette trigger; palette variants cover text, highlight, table borders, image borders, shape fill and shape outline.
- Table selection supports pointer drag range selection in addition to Shift-click. Table alignment actions set horizontal placement within the editable body.
- Image/shape interaction adds visible selected-state outlines, rotation/flip metadata expressed as inline transforms, and contextual right-click menus. Image sizing uses intrinsic aspect-ratio metadata; shape outline width is adjustable.
- Inline image client cap increased to 350 KB. Base64/data-URL expansion means request validation and server-side sanitizer constraints must be checked before treating this as a supported storage limit.
- These changes do not alter the editor's scope or print architecture. Verify sanitizer allowlisting for transform/aspect-ratio properties, event cleanup, selection across rowspan/colspan, and preview/print rendering before acceptance. Build and browser tests have not been run.

## 2026-11-11 — TypeScript correction
Fixed Vue `ref` access in table pointer-drag selection: assignments and reads now consistently use `dragSelection.value`. This addresses the reported TS2588/TS2339 errors at the drag-selection handlers. The correction is committed to `development`; build has not yet been rerun and no build success is claimed.


## 2026-11-11 — Editor property-panel synchronization

The correspondence editor's contextual property controls now derive displayed width/height from the selected DOM element's rendered bounds (converted from CSS pixels to millimetres), rather than fixed defaults. Image ratio locking is explicit opt-in state; when enabled, the paired dimension is calculated from intrinsic image ratio. Numeric paragraph controls apply a line-height multiplier and paragraph-after spacing in pixels. Color menus are dismissed by an outside-pointer listener scoped to the editor root; clear/no-color operations remove or disable the relevant inline styles. These changes are local to the Vue editor and do not change persisted schema or print architecture. Build, sanitization round-trip, browser and print validation remain pending.


## 2026-11-11 — Editor dimension and border reset refinement

Dimension readback now uses the selected element's untransformed layout box (`offsetWidth`/`offsetHeight`) so rotation does not distort the width/height values shown in the property controls. Clearing a table border disables that side; choosing a color afterward restores a visible solid border. Image intrinsic-ratio handling now registers the load callback before assigning the data URL. These are source-level refinements only; build and browser verification remain pending.


## 2026-11-11 — Text highlight clear action

The highlight palette's no-color action now removes the inline background color from fully selected highlighted ancestor elements instead of applying another transparent wrapper. Partial-selection behavior still requires browser acceptance. This is a source-level correction only; no build or browser verification is claimed.


## 2026-11-11 — Build feedback: unsupported Lucide icon

The user's production build identified `BorderAll` as unavailable from `@lucide/vue`. Replaced the unsupported icon reference in the shape-outline color control with the already-used/exported `RectangleHorizontal` icon and removed `BorderAll` from the import. This is a targeted compile-error correction; the build has not yet been rerun, so further build errors may remain. Development branch only.


## 2026-11-11 — Contextual selection, font-size readout and RTL dropdown refinement

- Preserved the selected image/shape/table-cell context while focus moves between the contenteditable surface and contextual toolbar, preventing selectionchange/focus events from hiding the relevant contextual tab.
- The font-size dropdown now reads the computed size at the current selection range endpoints, displays the actual point size (including custom sizes), and indicates a mixed-size selection rather than silently showing a fixed default.
- Styled editor dropdowns with a consistent left-side chevron, RTL option alignment, reserved arrow space, and control-specific minimum/maximum widths to prevent the arrow overlapping option text and improve fit for long labels.
- Source-level implementation only. Build and browser acceptance are pending; verify image selection/tab persistence, shape/table contextual tabs, font-size readout for preset/custom/mixed selections, and dropdown layout at desktop and narrow widths. Development branch only; master/release/server unchanged.


Follow-up in the same editor refinement: clicking in ordinary text now clears a previously selected table cell and its range anchor, so stale table context cannot keep the table tab visible after the user has moved outside the table. Browser verification remains pending.


## 2026-11-11 — Editor interaction and dimension correction

- Added outside-pointer dismissal for the editor's right-click context menu; interactions inside the menu do not dismiss it prematurely.
- Kept the browser text selection active when switching editor tabs by preventing tab-button pointer focus, while also snapshotting the selected range before toolbar interaction.
- Restored normal caret placement and typing inside table cells. Cell pointer-down no longer unconditionally prevents the browser's default caret action; rectangular drag selection is reserved for Shift+drag, while edge dragging remains available for resizing.
- Added a reactive dimension revision so width/height fields are recalculated after DOM style mutations and commits instead of retaining stale displayed values.
- Improved image dimension application by removing the CSS max-width clamp and applying explicit box sizing; shape dimensions now apply inline-block and border-box sizing; table dimensions validate positive numeric values and remove max-width clamping for explicit widths.
- Implementation is source-level only. Build and browser acceptance are still required. Verify right-click menu outside dismissal, retained text selection while switching tabs, typing/caret placement in table cells, and independently changing/readback of table/image/shape width and height. Development branch only; master/release/server unchanged.

## 2026-11-11 — Rich-text editor dimension persistence and partial highlight clearing

- Prevented the `modelValue` watcher from replacing the editor DOM while focus is anywhere inside the editor shell, including toolbar inputs. This avoids invalidating the selected image/table/shape DOM references during dimension edits. Deferred external synchronization until focus leaves the editor shell.
- Reworked highlight removal to extract the selected range, remove background-color declarations only from the extracted fragment, and restore the selection. Partial selections now clear without erasing the background of unselected text or removing unrelated inline formatting.
- The changes are committed on `development` only. Frontend build and browser acceptance remain pending; verify image, table, cell and shape width/height edits, then test clearing the background of both a whole highlighted phrase and a partial selection.

## 2026-11-11 — Preserve contextual tabs and selected ranges across tab clicks

- Root cause of the contextual tab disappearing: the tab strip itself was outside `.rte-toolbar`. `refreshContext()` therefore treated focus on a contextual tab as leaving the editor/toolbar, cleared table/image/shape context, and forced the active tab back to Home. Added the toolbar marker to the tab strip so contextual object state is retained when switching tabs.
- The same omission prevented `preserveEditorSelection()` from snapshotting a text range when the user clicked the tab strip. The tab strip now participates in the same selection-preservation path as the tool controls, allowing the no-background action to receive the saved selection.
- This is a source-level correction. Build and browser acceptance are still pending; verify contextual tabs remain visible after clicking them and that the no-background action clears both complete and partial text selections.

## 2026-11-11 — Rich-text dimension controls and text highlight clearing follow-up

- Re-inspected the actual editor handlers and template bindings. The previous dimension setters wrote ordinary inline widths while the table remained in automatic table layout; in that mode cell widths are advisory and the browser can redistribute them. The setters now apply explicit important dimensions, disable the table max-width constraint, and use fixed table layout when setting table/column widths. Image and shape dimensions are also applied explicitly so generic editor image constraints cannot cap the requested size. Cell height is applied to its row as well as the cell.
- The previous text-highlight removal only removed the `background-color` longhand from extracted HTML. It left the CSS `background` shorthand and semantic `<mark>` highlights untouched. The revised handler clears both background properties and unwraps selected `mark` fragments while operating on the extracted selection, preserving adjacent unselected formatting.
- These are code-level corrections based on the observed implementation paths. Production build and interactive browser verification have not yet been run; do not mark either behavior accepted until the owner verifies the actual editor. Development branch only; master/release/server unchanged.

## 2026-11-11 — Rich-text dimension controls and inherited highlight clearing

- Re-inspected the actual editor handlers and bindings. The previous dimension setters wrote ordinary inline widths while tables remained in automatic table layout; cell widths are advisory there and the browser may redistribute them. Table/column sizing now uses fixed table layout and explicit dimensions; image and shape dimensions are applied explicitly so generic editor constraints cannot cap the requested size. Cell height is applied to the row as well as the cell.
- The previous highlight remover only cleared the extracted fragment. When the selection was inside a highlighted wrapper, the source `span`/block remained the owner of the background, so reinserting plain selected text still inherited that color. The revised handler captures and clears the source ancestor chain as well as the extracted fragment, removes both `background-color` and `background`, and unwraps `<mark>` nodes.
- These code changes address the implementation paths identified in review. Production build and interactive browser verification have not yet been run; neither behavior is accepted until the owner verifies the editor. Development branch only; master/release/server unchanged.

## 2026-11-11 — Keep contextual tabs active while editing element dimensions

- Confirmed the tab regression was caused by focus being left inside a conditional toolbar panel. Switching tabs unmounted that panel via `v-if`; browser focus then fell back to `body`, and the document `selectionchange` handler ran `refreshContext()`, which interpreted the missing editor selection as deselection and forced the active contextual tab back to Home.
- Added an explicit tab-switch operation that moves focus to the persistent tab-strip button before changing the active panel and snapshots the editor range. `refreshContext()` now treats focus on the tab strip as an intentional editor interaction and does not clear the selected table/image/shape or reset the active tab.
- This also preserves the selected element references required by the width/height controls while moving from the editor or one toolbar panel to the contextual settings panel. The previously updated dimension setters remain in place: fixed table layout for width changes, explicit table/cell dimensions, row height, and explicit image/shape dimensions.
- Build and interactive browser acceptance are still pending. Verify tab persistence and actual element geometry changes before accepting this fix. Development only; master/release/server unchanged.


## 2026-11-11 — Image/shape dimension setters and shape-tab selection correction

- Root cause in contextual selection refresh: the editor-focus path returned early when an older connected image/shape/cell ref existed. That stale ref could mask the newly selected object before the click handler refreshed it. Context refresh now preserves refs only while focus is in a toolbar; with editor focus it resolves the current Selection/Range instead.
- Before toolbar focus changes, contextual pinning now resolves the current object from the saved editor range and updates the image/shape/cell refs and context together. This protects the shape tab from being removed by an intervening selectionchange and avoids pinning a stale object.
- Image and shape dimension fields now read through their selected-object resolvers, not only the transient refs. Their setters validate that the target belongs to this editor and synchronize the active object/context before applying dimensions.
- Shape dimensions now override the editor's minimum-size constraints and maximum-width constraint, in addition to applying explicit important width/height and border-box sizing. Image sizing continues to remove max-width clipping before applying dimensions.
- Code updated on development only. Build and browser acceptance have not yet been run; verify image width/height, shape tab persistence across repeated tab switches, shape width/height, and saved HTML after edits before marking complete.


## 2026-11-11 — Root-cause correction: stable contextual tabs and DOM-target selection

- Previous tab implementation generated the table/image/shape tab buttons conditionally from reactive context. When a selection/focus event briefly cleared context, Vue removed the focused tab button; browser focus fell back to body and the editor then reset the active tab. This explains why tab failures returned across image, table, and shape.
- Contextual tab buttons are now rendered permanently in a stable tab strip. Table/image/shape buttons are disabled when no matching object is selected instead of being mounted/unmounted. Switching a tab therefore cannot destroy the focused button.
- Object identity is now captured from the actual editor pointer/click target (img, [data-editor-shape], td/th, or table) into one persistent contextTarget ref. It is no longer reconstructed from a browser Selection/Range during toolbar focus changes; browser caret positions are unreliable for non-text objects. refreshContext() preserves that DOM target while toolbar controls have focus and resolves it when editor focus returns.
- Image, shape, and table contexts are synchronized from the same target helper. Image/shape dimension setters update the authoritative target ref before applying styles. Context-menu selection and table drag-selection also update the target. Removing an image/shape clears the target and contextual state.
- Image/shape dimension CSS remains explicit; the dimension readback decimal parser was corrected. Build and interactive browser verification are pending. Do not mark the UI issue complete until the project build and browser tests confirm tab persistence and rendered dimensions for all three object types.
- Changes are limited to the development branch; master, release, and server branches were not changed.


## 2026-11-11 — Final selection resolver hardening

- Dimension target resolvers now consult the authoritative contextTarget first, then fall back to the connected typed reference and saved editor range. This prevents a still-connected but previously selected image/shape/cell from being reused after the user clicks another object.
- Image aspect-ratio calculation now falls back to rendered geometry while the image is not yet decoded, avoiding zero/invalid ratios when the dimensions are changed immediately after insertion.
- Decimal authored CSS dimensions are parsed using a literal decimal-point character class. No CI workflow is configured for the development branch; build and browser interaction remain unverified until run in the project environment.


## 2026-11-11 — Contextual settings readback correction

- Root cause found in the object-settings resolver chain: image, shape, cell, and table resolvers could fall back from the latest explicit click target to a still-connected typed reference or the saved text Selection/Range. A stale saved range could therefore make settings display or modify a previously selected object instead of the object just clicked.
- Contextual object resolvers now use only the connected `contextTarget` established by the latest editor pointer/click event. Text Selection/Range remains available for text-editing commands, but is no longer used as a fallback for object-specific settings.
- Editor pointer/click selection now advances the dimension readback revision so width/height controls refresh even when the same object is clicked again. Table-cell and table resolution follow the same explicit target.
- **Validation status:** code committed to `development`; production build and interactive browser verification are still pending. Do not treat the issue as accepted until the user verifies that selecting different images/shapes/tables shows each object's own dimensions and that edits affect only that object. No changes to `master`, `release`, or `server`.


## 2026-11-11 — Restore unmodified table drag selection and editor-shell focus handling

- Table range selection now begins on an ordinary primary-button press and becomes a rectangular multi-cell selection only when the pointer crosses into another cell. A simple click or text drag contained within one cell is left to the browser, preserving caret placement and native text selection. Shift-click range selection remains supported.
- The drag anchor is the cell actually pressed, not a previously focused cell. During a cross-cell drag, the native text range is cleared and the rectangle is painted; the final active cell and contextual target are synchronized at pointer-up.
- Context refresh now treats every focused descendant of the editor shell (tab strip, toolbar controls, color popovers and contextual controls) as an internal editor interaction. It preserves the connected object target and active contextual tab rather than clearing selection because focus is no longer in the contenteditable node. Leaving the entire editor shell still clears contextual selection.
- This is a source-level correction on development only. Production build and browser acceptance remain pending. Verify plain mouse drag across cells (including multi-row and RTL tables), ordinary text selection/caret placement within one cell, Shift-click range selection, and switching among Home/Insert/Table/Picture/Shape tabs without losing the selected object's settings. Do not mark accepted until these checks pass.

## 2026-11-11 — Pin contextual object identity across toolbar focus

- Follow-up inspection found that the editor relied on a single transient `contextTarget` while the browser moved focus from a selected table/shape into contextual toolbar controls. The image path happened to retain a stable target more often, but table/shape controls could resolve against a changed or collapsed document selection.
- Added an explicit pinned object reference captured at the editor-shell pointerdown capture phase before toolbar focus changes. Context refresh and dimension target resolution now prefer that still-connected pinned object; a new pointer interaction inside the editable document clears the old pin and selects the newly targeted object. This applies consistently to images, shapes, table cells and tables.
- This change addresses target identity/focus timing; it does not establish that all dimension changes render correctly. Table/shape/image width and height fields, persistence after editing, and contextual-tab stability still require browser acceptance. No build or browser test has been run for this change. Development branch only; master, release and server unchanged.

## 2026-11-11 — Fix transient contenteditable blur resetting contextual tabs

- Root cause: table cells and shapes are editable content (contenteditable), unlike images. Moving focus from these targets to a toolbar tab/control can emit `selectionchange` during the brief blur/focus handoff. `refreshContext()` previously interpreted the transient `document.activeElement` outside the editor shell as a confirmed exit, cleared the contextual target and reset the active tab to Home. The image path generally did not trigger the same caret/selection transition, explaining the difference.
- `refreshContext(forceClear = false)` now preserves a still-connected pinned contextual object during transient focus gaps. A real exit is confirmed separately: an outside document pointerdown clears context immediately, while shell focusout waits for `nextTick()` and then calls `refreshContext(true)` after focus has settled. This avoids treating selectionchange as proof of leaving the editor.
- The same preservation keeps table/shape targets available while dimension inputs receive focus and their change handlers execute. Existing dimension conversion and CSS application routines were not rewritten in this correction.
- Source-level verification only; no production build or interactive browser test has been run. Acceptance must verify table and shape tabs remain active when clicking their controls, and width/height values update the selected object. Development branch only; master, release and server unchanged.


## 2026-11-11 — Correct contextual toolbar pointer scope and keyup typing

- Fixed the reported `vue-tsc` failure: the contenteditable's `keyup` event no longer passes its `KeyboardEvent` into `refreshContext(forceClear?: boolean)`; it calls a dedicated zero-argument `onEditorKeyUp()` wrapper instead.
- Narrowed contextual selection pinning to pointer interactions originating inside `.rte-toolbar`. The editor-shell capture handler now ignores pointerdowns in the editable document, so selecting a new table or shape cannot first pin a stale previously selected object. The redundant tab-strip capture handler was removed; the shell capture handler still snapshots toolbar targets before focus changes.
- This corrects a concrete event-routing defect and the reported TypeScript error. It is not yet evidence that the contextual-tab or dimension issues are fully resolved. The local production build and browser verification remain pending; specifically verify table/shape tab persistence and numeric width/height changes. Development branch only; master, release and server unchanged.


## 2026-11-11 — Fix mouseup event typing in correspondence editor

- Fixed the next `vue-tsc` event-handler mismatch: the editor content area's `@mouseup` now calls a zero-argument `onEditorMouseUp()` wrapper rather than passing Vue's `MouseEvent` to `refreshContext(forceClear?: boolean)`.
- The wrapper preserves the intended context refresh behavior while preventing a DOM event from being interpreted as the optional force-clear Boolean.
- This is a compile-time correction only. It does not establish that contextual tabs or numeric dimension editing work; those still require the production build and browser interaction verification. Changes are restricted to the `development` branch.
