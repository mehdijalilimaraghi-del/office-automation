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

## 2026-11-09 — Full-page print coordinate alignment

- Restored physical A4 dimensions for the absolute template coordinate plane in print mode. The plane now spans 210 × 297 mm and is aligned to the physical page origin using the configured top/right margin offsets, while the flowing letter body remains within the `@page` content area.
- This addresses horizontal percentage scaling and repeat/header/footer positions being calculated against the reduced printable width. Browser margin-dialog interaction and end-to-end PDF acceptance remain pending. Development only; master/release/server unchanged.

## 2026-11-09 — Print coordinate-origin mismatch correction

- Corrected the first-page coordinate origin in print mode: the absolute meta/heading plane is shifted from the `@page` content origin back to the physical A4 top edge. The flowing body remains in the printable content area, preserving the measured spacing relationship used by the screen preview.
- Pending local build, focused PHP tests and Chromium print/PDF comparison before marking print parity complete. Development only; master, release and server unchanged.

## 2026-11-09 — Page-number coordinate reconciliation and organization postal code

- Root cause confirmed in the current print implementation: the designer and screen print preview positioned the page-number element at its raw A4 percentage Y coordinate, while Chromium print output maps that element into a page-margin box. When the configured Y coordinate fell inside the body area, the actual printed counter snapped to the nearest reserved top/bottom margin but the screen previews did not, creating a visible position mismatch. Horizontal margin-box selection also used only the element's starting X coordinate, ignoring text alignment and width.
- The designer and print preview now map the screen page-number sample to the same nearest printable margin boundary and left/center/right margin-box segment used by the print renderer. Horizontal placement derives from the actual text anchor (alignment plus element width), with matching per-segment padding/offsets in both screen previews and the printed margin box. The printed counter also now honors the element's configured font family, size, weight, alignment and format-specific bidi direction rather than hard-coding 9pt Tahoma/LTR preview behavior. Page counters are browser margin-box content and therefore remain constrained to the reserved top/bottom print margins; placing the element in the body area snaps it to the nearest margin boundary, now consistently shown in both previews.
- Added optional organization postal-code setting end-to-end: organization settings form, request validation, settings controller read/write, template editor sample values and token list, repeatable header/footer/legacy-repeat token availability, and print template resolution. Token: `{{postal_code}}`. Print output falls back to the current organization postal code for older identity snapshots that predate this field.
- Added regression assertions for saving organization postal code and passing it to correspondence print props. PHP tests, frontend production build, and Chromium print comparison are still pending owner execution.
- Development branch only; master, release branch, and server remain unchanged.

## 2026-11-09 — Print preview TypeScript ref typing fix

- Replaced element-ref object bindings in the correspondence print preview with typed-safe callback ref setters. This resolves vue-tsc errors where nullable HTMLElement refs were not assignable to Vue's VNodeRef type.
- No runtime layout behavior was intentionally changed. Frontend build remains pending owner verification.
- Development only; master, release and server remain unchanged.

## 2026-11-09 — Correct body flow, template-controlled print assets and RTL page counter

- The printable letter body now measures the rendered bottom edge of first-page metadata/heading elements and adds the body element's configured spacing after that edge. The same measured offset is applied in the template designer preview and print preview; the A4 safe-area boundary remains a minimum start boundary, not a substitute for spacing from the preceding element.
- Stamp and department-signature data URIs are now passed to the print page only when a corresponding visible stamp/signature element exists in the saved template. The print page no longer automatically appends stamp/signature images merely because legacy organization print flags are enabled. Explicit template elements remain supported.
- CSS page-margin counters now use an RTL base direction for Persian «N از M» formats and LTR only for the slash format. This targets the real browser print margin box, not just the screen sample.
- Added regression coverage that removes stamp/signature elements from the saved template and asserts the print props no longer include either image. Existing coverage still verifies configured assets and the persisted body spacing value.
- Local PHPUnit, frontend production build, and Chromium one-/multi-page print acceptance are pending owner execution. Development only; master, release and server remain unchanged.

## 2026-11-09 — Explicit header/footer zones and protected letter text area

- The correspondence template designer now distinguishes repeating header elements and repeating footer elements as separate roles. Legacy `repeat` elements remain supported for saved templates.
- Print layout settings now include four letter-text edge distances (top/right/bottom/left) plus independently reserved header and footer heights. Effective content boundaries are the greater of the text edge distance and the corresponding reserved zone, so flowing body text cannot enter the header/footer regions.
- Defaults for reserved zones are 70 mm from the top edge and 25 mm from the bottom edge. The existing physical text margins remain 14/18/22/18 mm by default; effective text boundaries therefore respect the reserved regions.
- The body starts at the configured safe-area boundary rather than adding the old 42 mm body offset a second time. Both the designer preview and print renderer use the same effective content area.
- Page counter margin-box positioning and page-count calculations now use the effective reserved boundaries. Feature tests cover saving custom reserved zones, default values and passing the settings into print.
- This is a layout implementation awaiting local PHPUnit, frontend build and browser-print acceptance. Development branch only; master, release and server remain unchanged.

## 2026-11-09 — Multi-page A4 preview and page-counter placement

- This entry supersedes the earlier same-day note below that described the preview as a continuous canvas. The on-screen print preview now renders separate A4 page cards. Each card clips the same measured flowing letter content at the configured printable-area height; first-page metadata/heading remain on page one, while configured repeating header/footer items and the page-number sample appear on every preview card.
- A ResizeObserver recalculates the preview page count when the letter body, issuer elements, images or available dimensions change. This is a layout-based preview estimate; exact browser pagination can still differ at widow/orphan handling, unbreakable tables/images, and other fragmentation decisions.
- Print output remains a single natural-flow document in the print media tree (only the first screen card is used for printing), so Chromium's paged-media engine controls real page breaks and the actual page/pages counters.
- The page counter now uses the selected template format, x/y region and alignment, with explicit LTR bidi isolation. A4 margins are configurable in the designer and persisted independently (defaults 14/18/22/18 mm).
- Regression coverage checks margin persistence, defaults and transfer to the protected print page. Browser-specific multi-page print comparison and physical print acceptance remain mandatory before calling pagination fully verified.
- Development branch only; master, release branch and server remain unchanged.

## 2026-11-09 — Configurable print margins and template-positioned page counters

- Added editable A4 print margins (top/right/bottom/left) to the correspondence template designer, persisted separately from the template element array for backward compatibility. Defaults remain 14/18/22/18 mm; server validation prevents invalid or excessively narrow printable areas.
- The print controller passes the saved margin configuration to the print page. Screen preview uses the same margins; print output converts them into the actual `@page` content area.
- Replaced the hard-coded bottom-center page number with a CSS paged-media margin-box counter selected from the page-number element's configured x/y region and alignment. The counter format is generated from the selected template format and uses LTR bidi isolation so «1 از 1» retains its intended order instead of rendering as «از 1 1». The screen sample displays Persian digits and an estimated total.
- Added a ResizeObserver-based screen estimate of page count from rendered content height. This is explicitly an estimate, not a true page-fragmentation renderer: the screen letter remains a continuous canvas and does not yet show separate A4 page cards. Actual print counters use the browser's page/pages counters.
- Added feature assertions for saving custom margins, default margin values, and passing saved margins into the print page. Browser print compatibility, exact pagination and physical print acceptance still require owner verification.
- Development branch only. No changes to master, release branch or server.

## 2026-11-09 — Outgoing correction lifecycle and printable-area pagination

### Outgoing correspondence workflow
- Added `manager_return_for_correction`: the assigned origin-department manager can return an outgoing draft in `awaiting_manager_approval` to its creator, with a mandatory correction note. The creator can reopen the returned draft for editing.
- After the creator saves a returned outgoing draft, the controller reassigns it to the active manager of its originating department and restores `awaiting_manager_approval`; it is not sent directly to Secretariat.
- Secretariat correction is now permitted only while the outgoing draft is still `draft / approved_for_registration`, before final registration. `SECRETARIAT_RETURN` is rejected once the letter is registered. The previous post-registration correction behavior is intentionally removed for new workflow actions.
- The action controller now redirects an actor to the correspondence index when a successful action transfers a private draft out of that actor's visibility. This addresses the observed false 403 after the manager approval transaction had already moved the draft to Secretariat.
- Feature coverage added/updated for manager correction return, Secretariat pre-registration return, rejection of Secretariat return after registration, and the manager-approval redirect. Legacy registered correction records remain readable through the compatibility path.

### Print pagination and template layout
- Print output now uses an A4 printable area with 14mm top, 18mm side and 22mm bottom page margins. The body begins after the configured first-page spacing (the existing body element's spacing, default 42mm); CSS fragmentation applies that initial offset only at the start of the flowing body, while later page fragments start at the page's printable top margin.
- Existing repeat elements are explicitly described in the template editor as repeatable header/footer items and remain positioned per page in print. The page number is rendered through CSS page-margin counters, not fixed-position content.
- Important remaining limitation: the app's on-screen letter preview is still a continuous scrollable canvas and does not yet render dynamically segmented page cards or a reliable live total-page count. The actual browser print pagination, header/footer clearance, and page counters still require verification in the user's Chromium/browser version and printer settings. Do not mark the full preview-pagination requirement complete until this is verified and the screen preview is implemented or explicitly accepted.
- Changes are committed on `development` only. Tests/build and physical print acceptance are pending user execution. No changes to `master`, release branch, or server.

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

## 2026-11-09 — Print page-layout TypeScript contract correction

- Fixed the frontend prop contract in `Correspondence/Print.vue`: `pageLayout` now explicitly types `header_height` and `footer_height`, matching the defaults and values supplied by `CorrespondencePrintController`.
- This resolves the reported `vue-tsc` property-not-found errors without changing print calculations, persisted settings, or runtime behavior.
- The owner must rerun the frontend production build. Browser print acceptance remains pending; development branch only, with master/release/server unchanged.

## 2026-11-09 — Print designer controls, millimeter coordinates and body spacing

- Moved the six A4 print-boundary settings out of the permanent sidebar into a dedicated «تنظیمات محدوده چاپ» dialog. The dialog keeps four text-edge margins separate from the reserved header/footer heights.
- The template preview now visibly shades the reserved header and footer zones and outlines the protected text area, including while viewing the designer's sample preview.
- Element width and absolute x/y position are edited in millimeters against the A4 page dimensions. To preserve existing saved templates and print rendering compatibility, the UI converts these millimeter inputs to/from the existing normalized page-relative coordinates; no bulk data migration is required.
- Restored body-element spacing as a separate «فاصله از بخش قبل» setting. New body elements default to 12 mm; existing saved body spacing is preserved by both template and print controllers and is applied only at the beginning of the flowing body, not as a replacement for the page's top margin.
- Corrected the number_of page-counter content with an RTL isolate around the Persian «از» separator; other counter formats retain their existing output.
- Added a print-controller regression assertion that a saved body spacing value reaches the print page unchanged. Local feature test, npm run build, and browser print/manual layout acceptance are pending. Development branch only; master, release and server remain unchanged.

## 2026-11-09 — Print layout single-origin refactor

- Replaced the previous horizontal print compensation with a single physical A4 origin: zero horizontal `@page` margins, a 210 mm coordinate plane and flow box, and left/right text margins applied once as flow-box padding.
- Retained only effective top/bottom `@page` margins so header/footer reserved heights constrain body flow on every printed page. The template plane is shifted back to physical A4 top while body content remains naturally paginated.
- Implementation committed on `development`. Build, focused PHP test and Chromium preview/PDF acceptance remain pending; print parity is not yet complete. Master/release/server untouched.

## 2026-11-09 — Outgoing dispatch stage and recipient title prefix

- The existing external tracking columns are reused; no duplicate schema was introduced. Outgoing letters in `awaiting_dispatch` are now included in the `needs_action` state filter for all active Secretariat members, fixing the hand-off that previously made the newly registered outgoing letter disappear from the actionable list.
- Final dispatch now requires both a dispatch method and a sent date while the letter is in `awaiting_dispatch`. The workflow service independently enforces the same invariant before recording `dispatched`; external registration number/date remain optional tracking metadata. While awaiting dispatch, only Secretariat members/system administrators may edit this stage; creator/task-owner tracking permissions resume outside that state.
- Added `{{recipient_organizational_title_prefix}}` to the addressing element token allow-list, designer token catalog/sample values, and print resolver. It reads the immutable `organizational_title_prefix` captured from the selected external contact at registration.
- Frontend build, focused correspondence tests, and manual Secretariat workflow verification are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Recipient personal title prefix token

- Added `{{recipient_personal_title_prefix}}` to the addressing/heading element token catalog. It resolves from the external contact's immutable `recipient_addressing_snapshot.personal_salutation` value in print, independently of `{{recipient_title}}` (which continues to combine personal salutation and professional title for backward compatibility).
- No database migration is needed; the personal salutation is already captured in the recipient addressing snapshot. Build and correspondence regression tests are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Correspondence rich-text editor and structured appendix (in progress)

- [x] Add persisted appendix mode and rich-text body fields.
- [x] Separate appendix file links from the main letter's files.
- [x] Remove manual appendix page count entry from the correspondence form while retaining the legacy database field for compatibility.
- [x] Add automatic appendix summary token based on rendered text pages and linked appendix files.
- [x] Split print preview into main-letter and appendix page sequences; keep issuer/signature at the end of the main letter and preserve the existing pre-paginated A4 print architecture.
- [x] Replace the old `execCommand` toolbar with a reusable Home/Insert/contextual-tab editor.
- [x] Add regression tests for text-only and combined appendix creation.
- [ ] Run `npm run build`, focused PHP feature tests, and browser/PDF acceptance. Verify long text, images, tables, signature placement, independent appendix numbering, and exact preview/print sheet parity before closing this roadmap item.


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

## 2026-11-11 — Editor usability refinement (in progress)

- [x] Replace permanently visible color swatches with compact dropdown palette controls for text, highlight, table borders, image borders, shape fill and shape outline; show the current color on the trigger.
- [x] Add theme/standard swatches, custom color input and no-color actions where applicable.
- [x] Add drag-to-select table cell ranges without Shift and table alignment controls.
- [x] Add selected-state visuals, image aspect-ratio-aware sizing, image/shape rotation and flip controls, shape border thickness, and context-specific right-click menus.
- [ ] Verify style transform preservation through backend sanitization and save/reopen.
- [ ] Verify 350 KB inline image limit against request validation, sanitizer and database persistence.
- [ ] Run frontend build and manually test all palette, table, image, shape and context-menu workflows, then verify A4 preview/print parity.

## 2026-11-11 — TypeScript correction
Fixed Vue `ref` access in table pointer-drag selection: assignments and reads now consistently use `dragSelection.value`. This addresses the reported TS2588/TS2339 errors at the drag-selection handlers. The correction is committed to `development`; build has not yet been rerun and no build success is claimed.


## 2026-11-11 — Editor controls follow-up (implementation in progress)

- [x] Close color palettes on outside click without closing when interacting inside the palette.
- [x] Make no-color actions remove fill/border styling (and disable table border sides where requested); add no-color for table borders and shape outline.
- [x] Replace vague paragraph spacing presets with explicit numeric line-height multiplier and paragraph-after spacing in pixels.
- [x] Read dimension values from the selected element; add separate table height control; make image aspect-ratio lock visible and opt-in by default.
- [ ] Verify no-color and palette dismissal in browser; verify numeric spacing, all selected-element dimensions, image ratio lock/unlock, save/reopen, and A4 preview/print.
- [ ] Run frontend production build. No build or browser acceptance is claimed. Development only; master/release/server unchanged.


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

## 2026-11-11 — Correspondence editor usability follow-up

- **Implemented in source / awaiting verification:** sticky contextual tabs and active toolbar controls while scrolling long correspondence content. The sticky region is offset below the fixed-height application navigation.
- **Implemented in source / awaiting verification:** outgoing-only pre-registration preview button beside the form's bottom actions. It opens the shared A4 print renderer with current unsaved form values, template settings, letterhead and configured issuer assets; preview construction is in-memory and does not save the correspondence.
- **Known preview boundary:** uploaded file contents are not merged into the preview; the screen explicitly warns about this. Incoming correspondence remains unchanged.
- Acceptance remains open until production build, focused PHP tests, and browser checks confirm the toolbar stays visible and the preview matches the current form/template.

## 2026-11-11 — A4 table width regression

- **Implemented / awaiting verification:** prevent manually resized tables from exceeding the letter body width. Remove the persisted `max-width:none!important` behavior at editor resize time and normalize legacy table markup in the print renderer before page measurement. Preserve fixed table layout and wrap long cell content.
- Acceptance: resized three-column table stays inside printable text column at smaller and larger widths; an unmodified table remains correctly fitted; no horizontal overflow in print output.


## 2026-11-14 — Correspondence editor context menus, object deletion and row-height consistency

- Expanded right-click menus by target: text now exposes common formatting, paragraph alignment, list, clipboard and select-all actions; tables expose row/column insertion and deletion, row/column/table selection, merge/split and table deletion; pictures and shapes expose rotation, flipping, z-order and deletion actions. These are native editor operations, not a replacement editor.
- Added Delete/Backspace handling for the currently selected/pinned image or shape. A table is removed with Delete/Backspace only when the full table is selected; a normal caret inside a cell retains normal text-editing behavior.
- Corrected row-height ownership: changing a cell's height now writes the shared height on its parent row and clears conflicting per-cell height overrides. Reading the height from any cell in that row resolves the parent row's authored/rendered height, so sibling cells report the same value.
- **Status: source changes implemented, not verified.** Run Vue/TypeScript production build and browser acceptance for each right-click menu, clipboard/formatting actions, object Delete/Backspace, full-table selection/deletion, and row-height readback from every sibling cell. Clipboard behavior and browser-specific contenteditable keyboard handling require real-browser confirmation. Development branch only; master/release/server unchanged.


## 2026-11-14 — Organization correspondence settings and numbering templates

- Reorganized the Organization settings navigation labels to `مشخصات سازمان`, `واحدهای اصلی سازمان`, and `مکاتبات`; correspondence now has internal `شماره‌گذاری نامه` and `تنظیمات نامه` views.
- Added separate editable templates for outgoing letter numbers and Secretariat registration numbers. Supported placeholders currently include Persian/Gregorian year, month and day variants, `Dept`, and exactly one `Num-1..12-r|c` sequence token. Separators are literal template text.
- Added a scoped counter table keyed by counter type and template-derived scope. `r` scopes the sequence to the values of non-counter placeholders actually present; `c` uses one continuous scope. Allocation is transactional, row-locked, and checks existing correspondence numbers before returning a value. Existing legacy counters and historical correspondence numbers are retained.
- Added organization defaults for editor font family/color/size and table/shape border colors. New correspondence body content receives an inline typography snapshot; editor table/shape creation uses configured border colors.
- Added A4/A5 setting values in storage/UI; **print pagination remains hard-coded to A4 and A5 is not yet wired to the print pipeline**. Do not treat A5 as supported until print/template measurements are made page-size aware.
- Validation and browser/build/test execution are still pending. The migration must be run before enabling template numbering on an environment.


## 2026-11-14 implementation follow-up

- Added request-level validation for both templates: exactly one `Num-1..12-r|c` token, known date/unit tokens only, no duplicate placeholders, and a 200-character limit.
- Added a reference modal with the token table and examples.
- Added a global per-number-type allocation lock in addition to per-scope counters. This serializes allocations across template scopes before checking existing values, preventing two concurrent template configurations from passing the collision check simultaneously.
- Added feature coverage for `r` resetting by a template-used unit variable and `c` continuing across unit changes; updated organization settings tests for the new persisted fields and existing registration-counter assertions.
- Letter body typography (font family, size and color) is now wrapped into newly created rich-text body HTML so later organization setting changes do not restyle already-created letters. Editor defaults supply table and shape border colors for newly inserted elements.
- Correction to the earlier note: the page-size selector is restricted to A4 because the current preview/print renderer, coordinate system and pagination are A4-specific. A5 is deliberately not offered until that pipeline is refactored; page-size persistence currently represents the supported A4 default only.
- No local PHP/Vue build or PHPUnit run was available from the GitHub editing connection. Tests were added/updated but remain unexecuted. Run migrations and the focused tests before production use.


## Final implementation notes — 2026-11-14

- Persian date fallback now calculates Jalali year/month/day directly when PHP Intl is unavailable, including Persian month names; the Intl calendar formatter is used when available.
- Template validation is enforced both when saving settings and again inside the allocator, including malformed/unclosed placeholder rejection.
- The A4-only page-size control reflects the current A4-only preview/print geometry. A5 is not exposed as a selectable option.
- Focused feature tests were updated for new settings persistence and both counter modes. They have **not been executed** in this editing session; CI status checks returned no reported statuses.
