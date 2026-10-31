## 2026-10-27 — Correspondence form grid rows

- Expanded the external organization section (sender for incoming / recipient for outgoing correspondence) to span the full row in the four-column form grid. Validation pending: run the frontend build after pulling `development`.
- Kept the first correspondence metadata row at five large-screen columns for fields from correspondence type through the applicable date/recipient fields.
- Started a separate four-column grid for subject and all subsequent form fields, preventing the first-row column count from distorting the remaining sections. Recipient department retains its two-column span where shown, keeping the selector usable without pushing the date field to another row.
- Validation pending: owner to run the focused correspondence test and frontend build after pulling `development`.

## 2026-10-27 — Correspondence recipient field layout and validation

- Confirmed server-side validation already requires `recipient_department_id` for both incoming and internal correspondence; the form label explicitly displays a red required marker.
- Expanded the correspondence metadata grid to six large-screen columns and kept the recipient selector at two columns with `min-w-0`, so the wider tree selector does not force the adjacent Jalali date field onto a new row.
- Extended the correspondence HTTP regression test to verify that incoming and internal submissions without a recipient department are rejected and no draft is persisted.
- Validation pending: pull `development` and run `php artisan test --filter=CorrespondenceHttpTest`; report the result before further changes. `master` remains unchanged.

- Follow-up fix: `DepartmentTreeSelect.vue` now teleports its dropdown to `body`, positions it against the trigger, and recalculates on viewport scroll/resize. This prevents parent containers (including project section dialogs using `overflow-hidden`) from clipping the unit tree; the list retains a bounded height and internal scrolling. Validation is pending until the owner runs `npm run build` and `php artisan test`.

## 2026-10-27 — Department tree selector rollout checkpoint (validation pending)

- The shared `DepartmentTreeSelect.vue` now renders department-specific icons where supplied, hierarchical indentation/connector lines, expandable branches, search, and single/multiple selection. Internal and external unit pickers use the shared UI instead of page-specific native selects.
- Migrated selectors across Tasks, Projects, Correspondence, organization settings, department parent selection, user memberships, and external-party unit management. Backend selector payloads include `parent_id` and icon data where applicable.
- Replaced native Gregorian date inputs found in correspondence tracking with `JalaliDatePicker.vue`; date-time inputs must use `JalaliDateTimePicker.vue`. Do not create page-specific calendars.
- All related changes are committed on `development`; `master` remains untouched. **Validation pending**: the project owner must pull, run PHP tests and `npm run build`, and report the result. The assistant has not run the local test suite or frontend build.

## 2026-10-27 — Shared selector coverage and Persian date consistency (validation pending)

- Extended the shared department tree to organization settings, correspondence workflow referrals, department-parent selection, and external-party unit hierarchy. Inactive departments remain visible for existing settings but cannot be newly selected.
- Replaced native Gregorian date inputs for external registration and dispatch dates with the shared Jalali date picker; the shared date picker now supports a disabled state.
- A repository-wide Vue-page scan found no other native `type="date"` or `type="datetime-local"` inputs in the checked pages. Continue using the shared Jalali date/date-time components for all new fields.
- Validation is still pending. Do not treat the branch as tested until the owner pulls `development`, runs the PHP suite and frontend build, and reports results. `master` unchanged.

## 2026-10-27 — Shared department tree selector rollout (validation pending)

- Added the shared `DepartmentTreeSelect.vue` with department icons, a collapsible hierarchy, search, expand/collapse-all controls, and single/multiple selection.
- Migrated unit selectors in task creation/filtering, project section creation, project section management, correspondence creation/copy recipients, correspondence workflow referrals, user department membership/primary unit, and department-parent selection.
- Added `parent_id` to the relevant task/project/correspondence selector data so the UI can render hierarchy instead of flattening names. Future selectors must reuse the shared component.
- Documented the permanent department-selector and Jalali date-picker reuse standards in `CURRENT_STATUS.md`, `AI_CONTEXT.md`, and `PROJECT_CONTEXT.md`. Date fields must use `JalaliDatePicker.vue`; date/time fields must use `JalaliDateTimePicker.vue`, and visual changes belong in shared components.
- **Validation pending:** pull `development`, inspect all migrated selectors, run the focused correspondence/task/project/user feature tests, `php artisan test`, and `npm run build`. Do not claim validation until the project owner reports results. `master` unchanged.

## استاندارد دائمی رابط کاربری: انتخاب واحد و تقویم شمسی

- تمام انتخاب‌گرهای واحد در صفحات Vue باید از کامپوننت مشترک `resources/js/Components/DepartmentTreeSelect.vue` استفاده کنند؛ ساختن فهرست یا درخت واحد اختصاصی در یک صفحه مجاز نیست.
- درخت واحد باید نام و آیکون واحد، تورفتگی سلسله‌مراتبی، باز/بسته‌کردن زیرواحدها، جستجو و نمایش واضح والد/فرزند را داشته باشد. در حالت چندانتخابی، مقدارها باید از طریق `v-model:model-values` همگام شوند؛ در انتخاب تکی از `v-model` استفاده شود.
- داده‌های ارسالی به این کامپوننت باید تا حد امکان شامل `id`، `name` و `parent_id` باشند. اگر گزینه‌های فیلتر محدود به واحدهای مرتبط هستند، مراقب باشید حذف والدها باعث تخت‌شدن درخت نشود.
- برای تاریخ شمسی فقط از `JalaliDatePicker.vue` و برای تاریخ‌وساعت از `JalaliDateTimePicker.vue` استفاده شود. از ساخت تقویم محلی/اختصاصی در صفحه‌ها یا استفاده مستقیم از input تاریخ با ظاهر متفاوت خودداری شود. هر بهبود ظاهری تقویم باید در کامپوننت مشترک اعمال شود.
- بعد از افزودن صفحه یا فرم جدید، کل کد را برای انتخاب واحد و تاریخ بررسی کنید و کامپوننت‌های مشترک را به‌کار ببرید. در بازبینی UI نیز همه استفاده‌های قدیمی باید شناسایی و مهاجرت داده شوند.
- این تغییر در `development` است؛ تست‌ها و بیلد باید در محیط توسعه محلی اجرا و نتیجه توسط مالک پروژه تأیید شود. `master` نباید تغییر کند.

## 2026-10-27 — Correspondence create-form refinement (validation pending)

- Incoming «تاریخ دریافت» and outgoing «تاریخ ارسال» are required in server validation (`required_if`) and in the date-picker (`required`); explicit red asterisks were added because the custom picker labels did not visibly inherit the CSS required marker.
- The related-letter search input now stays above results and selected letters. Results and selected items share a fixed-height scroll area to avoid increasing the whole form row as items/search results grow.
- «رونوشت به» no longer displays «اختیاری»; the explanatory paragraph below it was removed and its meaning moved to a question-mark tooltip. Related-letter guidance is also a question-mark tooltip, with duplicate help removed.
- Changes are on `development`; no local build or tests have been run by the assistant. Validate with `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`.

## 2026-10-27 — Correspondence create-form UX and multi-link support (validation pending)

- The correspondence create form now gives Subject the full row; related correspondence and copy recipients sit side by side beneath it.
- Removed the redundant related-letter search helper sentence and renamed the field to «ارتباط با مکاتبات قبلی». The picker supports selecting multiple registered, visible letters and removing any selection.
- Added the `correspondence_related_letters` pivot table with backfill from legacy `reply_to_id`; the legacy field continues to point to the first related letter for compatibility. Server-side visibility checks protect confidential related letters.
- Replaced the copy-recipient checkbox grid with a single-select combo box that displays departments in hierarchy order and a removable selected-unit list. Label is now «رونوشت به».
- Migration and frontend/backend changes are on `development`; validation is pending. Run the migration, targeted correspondence tests, full `php artisan test`, and `npm run build`. Do not claim validation until the owner reports results. `master` unchanged.

## 2026-10-27 — Correspondence list scopes (validation pending)

- Added a server-side scope filter to the correspondence index: `mine` (default), `department`, and `all`.
- `mine` includes correspondence created/registered by the user, assigned to them, explicitly authorized for them, or on which they acted. `department` limits results to the user's member/managed departments, including created/recipient/current departments, copies, and department workflow actions.
- All scopes are layered on top of `CorrespondenceAccessService::visibleToQuery`; the `all` option means all correspondence the user is authorized to view, not an access-control bypass. Confidential-letter checks remain enforced server-side.
- Added feature coverage for all three scopes and adjusted the ordinary-letter visibility test to request `all` explicitly. Pull `development`, run the targeted Correspondence HTTP tests, then the full test suite and frontend build. Validation is pending; no tests/build were run by the assistant. `master` unchanged.

## 2026-10-27 — Correspondence dispatch, notifications, and Cartable integration (validation pending)

- Added privacy-safe database notifications for correspondence actions. Notification payloads contain a generic message, correspondence ID, and authorized detail URL; they intentionally omit letter subject/body and workflow notes.
- Final outgoing dispatch is now a distinct `dispatched` workflow state and `dispatched` business action/Core Audit event. Only the configured Secretariat (or system administrator) may set `sent_at`; the first transition to sent records dispatch once and notifies the creator. External tracking changes continue to audit changed field names only.
- The shared Cartable now includes actionable correspondence assigned to the current user or their current department, and the Vue page displays these items. The query applies `CorrespondenceAccessService::visibleToQuery`; copy-only recipients are excluded from actionable Cartable items.
- Added regression coverage for dispatch authorization/audit/notification privacy and for Cartable exclusion of copy-only access. **Validation pending:** pull `development`, run `php artisan test`, then `npm run build`. Do not start Forms & Requests until these pass and the V2 journeys receive manual acceptance. `master` unchanged.

## 2026-10-27 — Full validation confirmed for correspondence audit slice

- Project owner confirmed the full `php artisan test` suite passes and the frontend production build succeeds after the syntax correction in the external-tracking audit regression test.
- Treat the latest registration, copy-recipient, and outgoing external-tracking audit changes as user-validated. No tests or build were independently executed by the assistant.
- Next grouped review before Forms & Requests: inspect end-to-end incoming/outgoing/internal journeys, especially final dispatch audit, correspondence-specific notifications, and whether correspondence actions are correctly surfaced in the shared Cartable. Check the V2 acceptance gate in `ROADMAP.md`; do not assume these areas are complete from the audit tests alone.
- Branch: `development`; `master` unchanged.

## 2026-10-26 — Correspondence audit coverage and validation

- Project owner confirmed `php artisan test --filter=CorrespondenceHttpTest` passes after final-registration audit coverage was added. Registration now writes a `registered` business-history action and `correspondence.action.registered` Core Audit event; the note contains the registration number only.
- Next grouped slice: outgoing external-tracking audit. The controller now saves tracking updates under a row lock and DB transaction, emits `correspondence.external_tracking.updated` only when values actually change, and records changed field names only. It intentionally excludes old/new field values so private tracking notes are not copied into Core Audit.
- Added a regression test for persisted tracking data and audit privacy. Validation for this grouped slice is pending; after pulling `development`, run the full Laravel suite and frontend production build once, then report the result. `master` unchanged.

## 2026-10-26 — Copy audit regression test passed

- Project owner confirmed `php artisan test --filter=CorrespondenceCopyAuditTest` passes after pulling the syntax fix.
- This validates that adding a new copy recipient creates one correspondence action and one Core Audit event, while re-adding the same department does not duplicate either record.
- The broader `CorrespondenceHttpTest`, full PHP suite, and frontend build have not been rerun for this latest slice; do not infer their status from the focused pass.
- Next review: inspect registration and outgoing dispatch for equivalent business-history/Core Audit coverage and check Cartable/notification contracts against the V2 acceptance gate. Branch: `development`; `master` unchanged.

## 2026-10-26 — Audit coverage for copy recipients

- Updated `CorrespondenceWorkflowService::addCopies()` to record newly attached copy recipients through the same correspondence action + Core Audit path used by workflow actions.
- Copy attachment and audit/history writes run in one database transaction. Re-adding an already attached department no longer emits a duplicate action/audit event.
- Added `CorrespondenceCopyAuditTest` to verify the copy action and Core Audit event are created once.
- Validation pending: pull `development` and run the focused copy-audit and correspondence HTTP tests. No test result is claimed. Branch: `development`; `master` unchanged.

## 2026-10-26 — Correspondence workflow audit integration

- Connected every correspondence workflow action recorded through the shared workflow service to Core Audit via AuditServiceInterface. Audit entries identify the action type and preserve the action ID plus source/target department and user IDs as metadata; the correspondence action history remains the module-specific business timeline.
- Added regression coverage to the manager-approval HTTP test asserting the corresponding Core Audit entry is created alongside the business action.
- Scope deliberately remains narrow: no workflow/Cartable architecture changes and no notifications or request-engine scope expansion in this slice.
- Validation pending: pull development and run the focused correspondence HTTP test file. The full suite and frontend build were last reported passing before this change; they have not been rerun for this commit.
- Branch: development; master unchanged.

## 2026-10-26 — Validation confirmed; handoff for new chat

- Project owner reports that after the latest updates, the **full Laravel test suite passes and the frontend production build succeeds**. This supersedes the earlier “validation pending” note below for the SQL Server migration fix and the two regression-test alignment changes.
- Most recent reported full-suite result before the final alignment was **424 passed, 2 failed (426 total)**. The failures were:
  1. OrganizationSettingsTest: fixture lacked the required default correspondence font (correspondence_font_family); this was addressed by adding Vazirmatn to the test setup.
  2. CorrespondenceNumberingServiceTest: expected validation text no longer matched the manager-approval guard; the test expectation was updated to the current message. The application validation was not weakened.
- Owner then confirmed “پاس و بیلد شد”. Treat the current PHP suite and frontend build as user-verified successful; no test commands were executed independently by the assistant.
- SQL Server migration compatibility follow-up: removed restrictOnDelete() from the new correspondence workflow migration because SQL Server rejected generated ON DELETE RESTRICT. The foreign keys now rely on default NO ACTION behavior. The owner's subsequent successful test run indicates migration setup and tests completed in that environment.
- **Current feature checkpoint:** correspondence V2 workflow and read-only copy access are implemented in development: workflow state/current department/current user, action history, copy recipients, manager approval/referral/request actions, employee written result to manager, manager confirmation/completion, and separate outgoing dispatch tracking. Copy recipients can view but cannot act solely because of copy access.
- **Business rules to preserve:** incoming mail is finally registered by Secretariat and then initially referred to one destination department; internal mail bypasses Secretariat but may require manager approval; external outgoing mail can be routed internally, requires approval where configured, and is finally registered/dispatched by Secretariat. Subsequent inter-department referrals are distinct from the Secretariat's initial referral. Task/project/correspondence states remain independent. Project requests must go through Project Management; never create projects or tasks by bypassing their modules. Confidential visibility is limited to explicitly related users, Secretariat, and explicitly selected copy recipients; copies are read-only.
- **Next session:** read this file and inspect the latest development code before making changes. First check the project roadmap / version-2 plan and identify the next unfinished slice; do not assume the next task from stale notes. Keep CURRENT_STATUS.md updated after every code change. All code changes go to development; do not modify master.
- **Validation:** latest PHP suite and frontend build are user-reported passing. Continue to run focused tests, full php artisan test, and npm run build after relevant future changes; do not claim new validation without a reported or actually observed result.

## 2026-10-26 — Regression test alignment

- Full test run reported 424 passing and 2 failing; migration setup completed.
- Added the required `correspondence_font_family=Vazirmatn` value to the organization-settings test fixture.
- Updated the numbering test's expected validation text to match the current manager-approval guard. No application validation was weakened.
- **Validation pending:** rerun the two affected tests and the full suite. No passing result is claimed. Branch: `development`; `master` unchanged.

## 2026-10-26 — SQL Server foreign-key delete syntax follow-up

- The next test run showed SQL Server rejects Laravel's generated `ON DELETE RESTRICT` syntax; all 24 correspondence tests stopped during migration setup, with zero assertions executed. This was a migration/bootstrap failure, not evidence that the workflow tests themselves failed.
- Removed explicit `restrictOnDelete()` declarations from the new correspondence workflow migration. Foreign keys now use the database's default `NO ACTION` behavior, avoiding invalid SQL Server syntax while preventing cascading deletes and the multiple-cascade-path issue.
- **Validation pending:** pull `development` and rerun the focused correspondence tests, full PHP suite, and frontend build. No passing result is claimed. Branch: `development`; `master` unchanged.

## 2026-10-26 — SQL Server foreign-key compatibility fix

- Updated the new correspondence workflow migration to use restrictive/no-action delete behavior for its nullable foreign keys instead of `ON DELETE SET NULL`. SQL Server rejects the previous `correspondences_current_department_id_foreign` definition because it creates potential multiple cascade paths.
- This avoids database-side cascade paths across the new workflow references; referenced departments/users must be retained or have their references handled explicitly before deletion.
- **Validation pending:** rerun the focused correspondence tests and full suite against SQL Server. No test pass is claimed. Branch: `development`; `master` unchanged.

## 2026-10-26 — Restrict result UI to eligible letters

- The employee result control is now offered only for registered incoming/internal correspondence, matching the backend guard; outgoing dispatch tracking remains separate.
- Validation remains pending; no test/build result is claimed. Branch: `development`; `master` unchanged.

## 2026-10-26 — Final-result state guard

- Added a server-side terminal-state guard: after a manager confirms a documented result, the correspondence workflow rejects further routing actions. The detail page also hides manager/employee action controls for this completed state.
- **Validation remains pending**; no test/build result is claimed. Branch: `development`; `master` unchanged.


## 2026-10-26 — Correct correspondence result handling

- Removed the generic “reject request” and “close without result” actions from the supported backend action allowlist, workflow service, and detail-page controls.
- Replaced the employee return action with “submit result”: for registered incoming/internal letters, a non-manager member of the currently responsible department must provide a written investigation result or the reason the request cannot be fulfilled. The letter is routed to that department’s manager and remains open for a decision.
- Added the manager’s “confirm result and complete workflow” action. It is available only after a documented employee result is submitted, requires a manager note, and then marks the correspondence workflow as completed with a result. Managers may instead refer the correspondence or request an authorized next step.
- Kept outgoing-letter dispatch tracking separate; result submission/confirmation is not available for outgoing correspondence. Task/project/new-letter actions remain requests/routing only and do not create records by bypassing their modules.
- Added feature coverage for required result text, employee-to-manager routing, manager confirmation, and rejection of the two obsolete action names.
- **Validation pending:** source and tests have been committed to `development`; no PHP tests, migration, or frontend build have been run in this editing environment. Pull `development`, run focused correspondence tests and the full suite, then run the frontend production build. `master` remains unchanged.

## 2026-10-26 — Implement correspondence routing actions and read-only copies

- Added a correspondence-specific workflow state (workflow_state, current department, and current user) plus append-only correspondence_actions history and a correspondence_copies table. Existing correspondence rows are initialized to a basic routing state by the migration.
- Draft creation now initializes approval/registration routing state based on the configured approval setting. Final registration for incoming letters proceeds through Secretariat and then creates the initial referral to the destination department. Internal/outgoing letters configured for approval require a manager approval action before final registration; internal registration then routes to the destination department, while outgoing letters move to Secretariat dispatch tracking.
- Added workflow actions for an employee to return a letter to the current department manager with a note, and for the manager to approve a draft, refer to another department, request a task/project/new letter, reject a request, or close without result. Project requests are restricted to the configured Project Management department; this does not create tasks or projects directly and does not bypass their module policies.
- Added optional department copy recipients to the correspondence creation form. Registered letters are visible to members/managers of copy departments, including confidential letters, but copy membership alone does not authorize workflow actions. Copy entries and all workflow actions are recorded in history.
- Added feature tests for manager approval, copy visibility, and copy-recipient action denial.
- Added the workflow history/action panel to the correspondence detail page, localized workflow-state labels, and limited the approval option to drafts currently awaiting manager approval. Removed a duplicate outgoing-tracking panel found during verification.
- Validation pending: no PHP test suite, migration, TypeScript check, or frontend build has been run from this environment. Pull development, run the focused correspondence feature tests and full test suite, then run npm run build. Treat this implementation as unvalidated until those checks pass. Review edge cases around missing department managers and downstream task/project/letter request fulfillment; requests are currently recorded and routed, not auto-created.
- Branch: development; master unchanged.

## 2026-10-26 — Audit correspondence workflow integration before implementation

- Reviewed the existing correspondence model/controller/routes, workflow service/models, cartable service, task policy, project policy, and organization department-role settings on `development`.
- Confirmed correspondence already has an optional `workflow_instance_id`, but `CorrespondenceService::createDraft()` does not start a workflow or establish a routing history. Correspondence routes currently expose draft creation, registration, and outgoing tracking; there is no correspondence referral/action endpoint.
- The current `WorkflowService` is task-oriented: each workflow step creates a Task and advances when that task is approved. It does not itself provide the required correspondence action cycle (employee note back to manager, manager decision, inter-department referral, close-without-result, or rejection).
- Existing task referral is represented by `TaskAssignment` handoffs and is task-specific; it should not be repurposed as correspondence routing.
- Project creation is guarded by `ProjectPolicy::create()` and `OrganizationDepartmentRoleService::PROJECT_MANAGEMENT` (with the existing system-admin bypass). New correspondence actions must create a request/referral to the Project Management department and must not call project creation directly or broaden project permissions.
- Task creation is also controlled by `TaskPolicy::create()` and permission scopes. Correspondence actions must route a task request through existing authorized task-creation rules rather than bypassing them.
- Implementation direction: add a correspondence-specific, auditable action/routing history and current-recipient state; keep approval, registration, referral, and closure as distinct transitions; use the existing department manager assignments and existing module policies for all downstream work. Preserve the current outgoing tracking feature and keep task/project/correspondence statuses independent.
- This is an audit/design checkpoint only; no functional workflow changes have been made in this entry. The end-to-end lifecycle remains pending implementation and feature tests.
- Branch: `development`; do not modify `master`.

## 2026-10-25 — Fix correspondence detail TypeScript build errors

- Removed duplicated `CorrespondenceDetail` fields (`task_id`, `task`, and external tracking fields) and the duplicate `trackingForm`/`saveExternalTracking()` declarations in `resources/js/Pages/Correspondence/Show.vue`.
- Typed Inertia `usePage()` with the shared `PageProps` type so the required `auth` property is included while preserving the page's validation errors and flash-message typing.
- Committed to `development`. The production frontend build has not been run here; pull the branch and rerun `npm run build` to verify.

## 2026-10-25 — Fix correspondence controller parse error

- Removed the stray literal `undefined` that caused `ParseError: unexpected identifier "undefined", expecting "function"` at `CorrespondenceController.php:362`; this parse error explains the broad HTTP 500 responses in correspondence feature tests.
- Confirmed the outgoing external-tracking action is present again and the tracking route is declared once.
- The code has been updated on `development`; tests and frontend build remain unverified. Pull the branch and rerun `php artisan test --filter=CorrespondenceHttpTest` before considering the fix validated.

## 2026-10-25 — Remove duplicate declarations in correspondence controller

- Fixed the fatal duplicate `Task` import in `CorrespondenceController.php`.
- Inspection also found duplicated `updateExternalTracking()` methods and repeated response keys for task/tracking data; removed the duplicates to prevent the next PHP class-load fatal error and avoid duplicate payload declarations.
- Commit on `development`: `b6fd7fb7a5758578bb53f31aadc5e69f84d36c53`. Tests/build are still unverified; run the focused correspondence HTTP tests after pulling this update.

## 2026-10-25 — Fix duplicate correspondence task relation

- Fixed the fatal `Cannot redeclare Correspondence::task()` error by keeping a single `task(): BelongsTo` relation in `app/Modules/Correspondence/Models/Correspondence.php`.
- Also removed duplicate `task_id` / external-tracking entries from `$fillable` and the duplicate `external_registered_at` cast found during inspection.
- Commit on `development`: `75c0788bebc882d437dba8a03d06da36304799d6`. Tests and frontend build remain unverified; this fixes the PHP class-load fatal error but does not establish that the correspondence feature tests pass.

## 2026-10-25 — Fix duplicate Task import and clarify correspondence workflow status

- Fixed the duplicate `use App\\Modules\\Tasks\\Models\\Task;` import in `app/Modules/Correspondence/Models/Correspondence.php` (commit `06a1605fcb13b4ea78910ef167f2f6064681e924`).
- The agreed end-to-end correspondence lifecycle is **not yet implemented**. The previous changes cover the initial outgoing-letter tracking fields/UI and task association only; they do not implement approval/registration/referral for incoming, outgoing, and internal correspondence.
- Re-run the correspondence feature tests after pulling this fix. Test/build results are not yet verified here.

## 2026-10-25 — External outgoing-letter tracking linked to tasks

- Added structured tracking fields for outgoing correspondence: destination organization's registration number and date, dispatch method, tracking notes, and sent date.
- Added an optional `task_id` relation from correspondence to a task, with a nullable foreign key and `ON DELETE SET NULL` behavior. The task and correspondence statuses remain independent; completing a task does not imply destination registration.
- Added an outgoing-letter tracking section to the correspondence detail page. Eligible users can link a task and maintain dispatch / destination-registration information. Access is limited to Secretariat/system administrators, the correspondence creator, and the linked task creator/assignee; task selection is limited to tasks the current user created or is assigned to.
- Added route `correspondence.external-tracking.update` and migration `2026_10_25_120000_add_external_tracking_and_task_link_to_correspondences_table.php`.
- **Validation pending:** tests and frontend build have not been run here. Next phase is to implement the agreed approval/registration/referral lifecycle for incoming, outgoing, and internal correspondence and add feature tests. Keep `master` unchanged.

## 2026-10-25 — External outgoing-letter tracking linked to tasks

- Added structured tracking fields for outgoing correspondence: destination organization's registration number and date, dispatch method, tracking notes, and sent date.
- Added an optional `task_id` relation from correspondence to a task, with a nullable foreign key and `ON DELETE SET NULL` behavior. The task and correspondence statuses remain independent; completing a task does not imply destination registration.
- Added an outgoing-letter tracking section to the correspondence detail page. Eligible users can link a task and maintain dispatch / destination-registration information. Access is limited to Secretariat/system administrators, the correspondence creator, and the linked task creator/assignee; task selection is limited to tasks the current user created or is assigned to, except Secretariat/admin.
- Added route `correspondence.external-tracking.update` and migration `2026_10_25_120000_add_external_tracking_and_task_link_to_correspondences_table.php`.
- **Validation pending:** changes were committed to `development`; tests and frontend build have not been run here. Next phase is to implement the agreed approval/registration/referral lifecycle for incoming, outgoing, and internal correspondence and add feature tests. Keep `master` unchanged.

## 2026-10-25 — Correspondence registration feedback and workflow audit findings

- The owner reports that pressing final registration for a newly created incoming letter appears to do nothing. In the current implementation, `CorrespondenceNumberingService::register()` rejects incoming/outgoing letters when their configured approval is required but there is no linked workflow instance in `completed` state. The UI did not display the returned `registration` validation error, making the rejection look like a dead button.
- Updated `resources/js/Pages/Correspondence/Show.vue` to display registration errors and success flash messages. Commit on `development`: `63296b00cfac86484cb903569c262aab2d2b7b0e` (error display) and `e2b02b1f5b307290817e89e4480b4afa492a7d9f` (success feedback). `master` unchanged.
- Initial route audit: incoming creation is restricted to Secretariat; incoming requires a recipient department and defaults to approval-required. Outgoing drafts can be created by users with `correspondence.create`, but final registration is Secretariat-only and defaults to approval-required. Internal letters default to direct registration and the creator may register their own draft. These rules are reflected in `CorrespondenceAccessService` and `CorrespondenceCompositionSettings`.
- Structural gap found: `CorrespondenceController::store()` creates a draft but does not start or associate a workflow; no correspondence-specific referral endpoint appears in the current correspondence routes. Therefore, when approval is required by settings, the current registration guard can block a new incoming/outgoing letter indefinitely. Do not bypass the approval guard until the intended approver/route is defined.
- **Validation pending:** no tests or frontend build were run here. Next, pull `development`, build/test, reproduce final registration and send the exact on-screen error. Before implementing routing, confirm who should approve incoming/outgoing letters and whether incoming registration itself should be Secretariat-only followed by referral to a recipient department, or whether approval should precede registration. Keep `master` unchanged.

## 2026-10-24 — Scanner preview, repeat viewing, and Windows UI polish

- The project owner confirmed the scanner workflow now completes. They reported that the browser download-manager integration intercepted the staged PDF preview, the flatbed review window looked rough, and the create form did not provide a way to reopen the accepted scan.
- Changed the create form to fetch the staged PDF as a blob and display a local object URL in the embedded preview, avoiding direct navigation to the PDF endpoint. Added a persistent “مشاهده دوباره” action for a scan already accepted in the draft; it reopens the preview without requiring another scan. Blob URLs are revoked when the scan is discarded, replaced, the correspondence type changes, or the page unmounts.
- Polished the native Windows scanner progress, device selection, and flatbed review forms with clearer spacing, a larger preview window, consistent Segoe UI typography, and distinct action buttons. Scan/next-page/rescan/finish behavior is unchanged.
- Commits on development: `af1b2417dc63881a9ba093a0fd1fd75e13093daa` (Windows UI) and `7afd2691e839e389ca6e1dcce7b8b4001b0b28e2` (browser preview and repeat viewing). `master` remains unchanged.
- **Validation pending:** the assistant has not run the .NET publish, frontend build, or physical scanner test for these latest changes. Pull `development`, publish the scanner helper, replace installed helper files while preserving `appsettings.json`, then verify the PDF preview is embedded, “مشاهده دوباره” works before saving the draft, and the redesigned flatbed controls remain usable. Run `npm run build` as well. Do not treat these changes as validated until the local checks pass.

## 2026-10-24 — Keep scanner UI message loop alive

- The scanner log consistently stops after detecting the Samsung SCX-3200 as a flatbed device, before any page-return log. This narrows the failure to the start of the native flatbed scan or the helper's UI/runtime lifecycle; the exact driver failure is not yet confirmed.
- Updated the Windows helper to run inside a persistent WinForms message loop (Application.Run) and moved the asynchronous scan workflow into that loop. This is intended to keep WIA/TWAIN native interactions, progress forms, and the flatbed review form alive across awaits.
- Commit on development: 8f0e586e7891f7e52760cf5a77a119dddfb68b23.
- Validation pending: no .NET publish or physical scanner test has been run here. Republish and test once; if the log still stops at capability detection, collect the matching Windows Event Viewer application error. Keep master unchanged.

## 2026-10-24 — Add persistent Windows scanner diagnostics

- The project owner reports that the scan window closes without any message while the correspondence form remains in “waiting for scan completion”. The browser is polling the upload status, so no completed upload has been confirmed.
- Added persistent stage-by-stage logging to the Windows helper, including protocol launch, scanner discovery, detected capabilities, pages returned, PDF export, upload start/success, and managed exceptions. No upload token or full URL is written to the log.
- Log location: `%LOCALAPPDATA%\OfficeAutomation\Scanner\scanner.log`.
- **Next diagnostic:** pull `development`, republish the helper, replace the installed helper files, reproduce once, and inspect the final lines of `scanner.log`. If the log stops abruptly during native scanning, inspect Windows Event Viewer for an application crash at the same timestamp.
- **Validation pending:** no .NET publish or physical scanner test has been run here. Keep `master` unchanged.

## 2026-10-24 — Simplify Windows scanner appsettings

- Removed the obsolete `Naps2GuiPath` setting from `tools/WindowsScanner/appsettings.json.example`. The current SDK-based helper does not launch the NAPS2 desktop executable.
- The installed `appsettings.json` must contain `ApplicationOrigin` set to the exact base origin of the deployed application (scheme + host + optional port), for example `https://office.example.org`; replace the example with the real deployment URL. Do not include a path such as `/login` or a trailing route.
- Validation pending: after publishing, copy the example to `appsettings.json` beside the EXE, set the real origin, install the helper, and test scanner upload. No build or device test is claimed.
- Branch: `development`; `master` unchanged.

## 2026-10-24 — Replace manual NAPS2 desktop workflow with SDK-driven scanning

- Replaced the launch/wait/save/select workflow with the official NAPS2 .NET SDK in the Windows bridge. It enumerates WIA/TWAIN devices, asks the user to choose when multiple devices are detected, and reads reported feeder/flatbed capabilities.
- When ADF is reported, the helper scans the batch and creates one PDF automatically; the browser then shows its existing PDF preview for explicit attach/discard confirmation.
- For flatbed-only scanners, each scanned page is previewed in a native window with the requested three actions: scan next page, rescan/replace the current page, or finish and save. Pages are assembled into one PDF and uploaded without a manual Save dialog or file picker.
- Added official NAPS2 SDK, GDI image support, and the official 32-bit TWAIN worker package. The NAPS2 desktop executable is no longer launched by the helper.
- **Validation pending:** source changes have not been compiled or tested here. Run the Windows publish command and test ADF and flatbed workflows with the actual scanner/driver. Capability reporting varies by driver; if a device reports both feeder and flatbed, the current flow prioritizes ADF.
- **Branch:** `development` only; `master` unchanged.

## 2026-10-24 — Scanner launch requires a prepared session

- Updated the correspondence creation page to prepare the short-lived scanner upload session before the user clicks the scan button, then invoke `officeautomation-scanner://` directly during the click gesture. This avoids relying on a delayed custom-protocol navigation after an asynchronous request.
- Added clearer feedback when the session is still preparing, the browser blocks the launch, or no upload arrives. Manual file selection remains available.
- **Validation status:** not run; frontend build and workstation protocol launch still need verification. If the blank tab persists, check that `OfficeAutomation.Scanner.exe` exists under `%LOCALAPPDATA%\OfficeAutomation\Scanner`, `appsettings.json` is configured, and `install.ps1` was run under the same Windows account used by the browser.
- **Branch:** `development` only; `master` remains unchanged.

## 2026-10-24 — Fix browser handoff to the NAPS2 scanner protocol

- Updated the incoming-letter scan button to open a browser tab synchronously during the click gesture, then navigate that reserved tab to the `officeautomation-scanner://` protocol after the server returns the short-lived upload URL. This addresses browsers suppressing external-protocol launches initiated only after an awaited HTTP request.
- Improved the visible status messages and timeout guidance for protocol registration and helper setup. Manual file upload remains available.
- **Validation status:** not run. Verify on the secretariat workstation that the custom protocol is registered for the signed-in Windows user, the helper EXE and `appsettings.json` are installed in `%LOCALAPPDATA%\OfficeAutomation\Scanner`, then test the browser handoff. No build or scanner success is claimed.
- **Branch:** `development` only; `master` remains unchanged.

## 2026-10-24 — Replace direct WIA scanner implementation with NAPS2 integration

- Reworked the Windows bridge to delegate scanning, feeder/duplex handling, page assembly, and PDF generation to the free NAPS2 command-line application instead of implementing scanner drivers and PDF generation in the bridge itself.
- The bridge still provides the small required integration layer: validates the custom protocol launch and trusted application origin, invokes NAPS2 with the configured profile, enforces the 20 MB PDF limit, uploads the PDF to the short-lived user-bound endpoint, and reports success/failure to the user.
- Added configurable NAPS2 executable path and profile name in `appsettings.json.example`; updated the workstation setup guide to install NAPS2, create a feeder/duplex profile, and configure the bridge.
- Updated the incoming-letter UI label and help text to describe NAPS2 scanning with automatic upload. Manual file upload remains available.
- Removed the bridge's PdfSharp/System.Drawing dependencies because PDF creation is now handled by NAPS2.
- **Validation status:** intentionally deferred as requested. NAPS2 CLI invocation, .NET publish, PHP correspondence tests, frontend production build, and physical scanner tests have not been run. Confirm the installed NAPS2 CLI path and profile name during later workstation setup.
- **Branch:** `development` only; `master` remains unchanged.

## 2026-10-24 — Incoming correspondence template cleanup

- Cleaned up stray template text left by the incoming-field reorder in `Create.vue`. The incoming recipient and received-date controls remain in the top metadata grid.
- Local Vue compilation/build is still pending; no successful build is claimed.
- **Branch:** `development`; `master` unchanged.

## 2026-10-24 — Incoming correspondence direction and received-date placement

- Incoming correspondence now presents the internal recipient unit as **واحد دریافت‌کننده** instead of exposing the outgoing-letter creator unit in the form. The server still derives the creator/numbering unit internally when saving the draft; users do not have to select it for incoming letters.
- The incoming recipient unit is required by server validation and saved in `recipient_department_id`. Internal letters continue to require a recipient unit; outgoing letters continue to show their creator unit and send date.
- Moved **تاریخ دریافت** into the top metadata grid alongside the relevant fields, rather than leaving it below the external-party panel. The external-party panel is labeled as sender organization for incoming letters and recipient organization for outgoing letters.
- **Branch:** `development`; `master` unchanged. Build, correspondence tests, and manual UI checks are pending; no test result is claimed.

## 2026-10-24 — Correspondence reply row and direction icon correction

- Moved the “پاسخ به مکاتبه ثبت‌شده” field in the form markup to immediately precede the full-width external-party panel, so the reply field is placed with the subject row and the external-party panel begins on the following row.
- Replaced the text labels on the RTL/LTR editor toolbar controls with matching direction icons, retaining Persian accessible labels and hover titles.
- **Branch:** `development`; `master` unchanged. Frontend build and manual layout/icon checks are pending; no test result is claimed.

## 2026-10-24 — Fix correspondence create-page Vue compiler error

- Fixed the Vite/Vue compiler error in `resources/js/Pages/Correspondence/Create.vue`: the scoped `<style>` block for the editor toolbar had been placed inside the page's `<template>`. Moved the style block after the closing template, preserving its CSS.
- Local frontend build and correspondence tests are pending; do not report them as passed until run locally.

## 2026-10-24 — Reuse external-party directory in correspondence

- Analyzed the existing `Core/ExternalParties` feature before adding any new directory. It already manages external organizations, hierarchical departments, and contacts, and task management consumes those records; no parallel external-party schema was introduced.
- Correspondence drafts now accept optional `external_organization_id` and `external_department_id` references to the existing active records. The form filters units by the selected organization, clears stale unit selections when the organization changes, and retains the legacy sender/recipient text fields for a person or title and for compatibility with records or parties not yet in the directory.
- Added a SQL Server-safe migration with nullable foreign keys using NO ACTION on delete, Eloquent relationships, service-level validation, and request validation that verifies the selected unit belongs to the selected organization and both records are active.
- The correspondence list and detail page display the linked external organization/unit. Added regression tests for successful selection, mismatched organization/unit rejection, circular external department hierarchy prevention, and rejecting inactive external organizations/departments in task context.
- Hardened external-party maintenance: prevent moving a department with children/contacts to another organization, prevent hierarchy cycles, and reject deletion of organizations/departments/contacts that are still referenced by tasks or correspondence, instead of relying on a database constraint failure.
- Local validation is **pending**. Pull `development`, run migrations, build the frontend, then run the focused correspondence and external-party service tests. Do not merge or modify `master`.

## 2026-10-21 — Correspondence authorization boundaries (V2)

- Reviewed the V2 roadmap and existing organization settings before changing authorization. The configured secretariat remains the source of truth via `organization.department_roles.secretariat`.
- Active staff can view registered ordinary letters, create internal letters, and create outgoing drafts. Only the configured secretariat can create incoming letters and register/number incoming or outgoing letters. A creator can register their own internal draft.
- Applied authorization in the access service, HTTP handlers, draft domain service, and numbering/registration service. The form hides unauthorized types and defaults to an allowed type; list registration actions are computed per letter.
- Added regression tests for ordinary-user creation boundaries, secretariat access, internal-letter registration, and direct service authorization. Tests/build have **not** been run after these changes; validate locally before starting referrals or Workflow/Cartable integration.
- Branch: `development`; `master` unchanged.


## 2026-10-21 — Baseline access for correspondence menu

- The correspondence menu was hidden for ordinary active staff because `correspondence.view` and `correspondence.create` were only explicit role grants, and the standard operational role setup did not grant them.
- `User::responsibilityDerivedScopes()` now grants organization-wide baseline `correspondence.view` and `correspondence.create` to active users, matching the V2 rule that staff can access the correspondence module and create drafts. Final registration/numbering is enforced against the configured secretariat; the creator may register their own internal draft. Confidential letter access is enforced by `CorrespondenceAccessService` for related users and the configured secretariat.
- Updated the HTTP test to verify active staff can open the correspondence index and draft form without an explicit role grant.
- Branch: `development`; `master` unchanged. Local validation pending: pull the branch and run `php artisan test --filter=CorrespondenceHttpTest`.

## 2026-10-21 — Correspondence test permission fixture

- Fixed a SQL Server unique-name collision in `CorrespondenceHttpTest`: test permission names now include the permission code, while permissions are still reused by unique code. The three visibility tests had been failing during fixture setup before reaching their assertions.
- Local validation pending: pull `development` and rerun `php artisan test --filter=CorrespondenceHttpTest`. No production authorization logic was changed by this fix. Keep `master` unchanged.
## 2026-10-21 — Full test suite follow-up

- The user ran the full Laravel suite: 401 tests passed; two tests failed.
- `CorePermissionSeederTest` expected 53 permissions, but the current seeder correctly creates 56 after the three V2 correspondence permissions were added. Updated the expected count to 56.
- `ExampleTest` expected HTTP 200 for `/`, but the intended guest route redirects to the login page. Updated the test to assert the named login redirect; application routing was not changed.
- These are test expectation corrections only. Changes are on `development`; keep `master` unchanged.
- Local verification pending: run the full test suite after pulling the branch.

## 2026-10-21 — V2 correspondence composition options

- The correspondence draft form now supports three modes: internal editor, file-only upload, or editor text plus attachments.
- The internal editor provides basic bold/italic/underline and ordered/unordered list formatting. Submitted HTML is allow-listed and stripped of attributes before persistence.
- File uploads use the shared Documents service and link documents to their correspondence; the existing DocumentPolicy now permits viewing linked files to users with correspondence.view.
- Up to 10 attachments are accepted, each up to 20 MB, limited to the configured document types. File-only mode requires at least one attachment; combined mode requires both body text and an attachment.
- Added focused tests for editor HTML sanitization, missing file validation, file-only draft creation and permission fixture setup. Fixed the test fixture import to use the actual enum namespace `App\\Core\\Permissions\\Enums\\PermissionScope` after local execution exposed a class-not-found error. The focused test must be rerun locally; build remains pending.
- **Branch:** development. Keep master unchanged.

## 2026-10-21 — V2 correspondence HTTP foundation

- Added permission-protected correspondence list/create routes and a final-registration action using the existing CorrespondenceService and CorrespondenceNumberingService.
- Added Persian RTL correspondence list and draft form for incoming, outgoing and internal letters, with server-side search/type/status filters, pagination, department selection, reply-to selection and registration feedback.
- Added correspondence.view, correspondence.create and correspondence.register permissions; exposed view capability to Inertia and added navigation to the authenticated work menu.
- Added CorrespondenceHttpTest coverage for list access, draft creation and denied access without permission.
- Validation pending: pull development, run the focused correspondence tests, then npm run build. Verify permissions are seeded and granted to the intended roles. Detail/edit screen, referrals/instructions, workflow/Cartable actions and correspondence-linked tasks remain the next V2 slices. Keep master unchanged.

## 2026-10-21 — Fix notification dropdown TypeScript build error

- Fixed the mobile notification list in `resources/js/Layouts/AuthenticatedLayout.vue`: it referenced the undefined `markNotificationRead` handler.
- Reused the existing `openNotification(item)` handler, which closes the dropdown, marks unread notifications as read through the existing route, and navigates to the notification destination.
- User reports the focused `NotificationTest` suite passed. Frontend production build is pending.
- Development working directory on the user's machine: `D:\\KPMM\\Office-automation`. Keep `master` unchanged.

## 2026-10-21 — Notification regression fixture aligned with recipient policy

- The failing `NotificationTest` expected a task creator to receive comment notifications even when the creator was not the current department/project manager. That contradicted the implemented policy: notify the current department manager (project manager only as fallback) and current assignee, excluding the author.
- Updated the test fixture so the creator is also the department manager; the test verifies manager/assignee notifications and author exclusion without changing production notification behavior.
- Local validation is pending. Run `php artisan test --filter=NotificationTest` after pulling `development`.

## 2026-10-21 — SQL Server comment reply migration fix

- Fixed the self-referencing `task_comments.parent_comment_id` foreign key to use SQL Server's default `NO ACTION` instead of `ON DELETE SET NULL`, which SQL Server rejected due to possible multiple cascade paths.
- When deleting a parent comment, existing replies are detached first so they remain visible as standalone comments and deletion does not violate the self-reference constraint.
- Migration, tests, and build have not been executed by the assistant. Pull the latest `development` branch and rerun migration and checks locally.

## 2026-10-20 — Task comment recipients, direct replies and header notification center

- Updated comment-notification recipients to the current task manager (department manager, with project manager as fallback) and current assignee only. The author is excluded, duplicate recipients are collapsed, and historical assignees/participants/creator no longer receive new comment alerts merely because of past involvement.
- Added direct replies to task comments with a nullable self-referencing `parent_comment_id`, request validation, model relationship, and inline reply UI. Existing comments remain valid and render without a parent.
- Added a notification-summary endpoint and a responsive header bell/dropdown showing unread count, recent notification previews, mark-all-read action, and a link to the full notification center. The summary refreshes on page mount, when opened, and every 30 seconds.
- Added focused regression tests for current manager/current assignee recipients, exclusion of a previous assignee, and parent-linked replies. These tests and the frontend build have not been run by the assistant; pull and validate locally.
- **Branch:** `development`. Keep `master` unchanged.

# Office Automation — Current Status

## 2026-10-21 — Correspondence authorization boundaries (V2)

- Repository/roadmap review confirmed V2 is active and correspondence authorization must be enforced in backend services, with the configured organization secretariat as the registration authority.
- Every active user may view registered ordinary letters and access the draft form; ordinary users can create internal letters and outgoing drafts, while incoming-letter creation is restricted to the configured secretariat.
- Final registration/numbering of incoming and outgoing letters is restricted to the configured secretariat. A user may register their own internal draft.
- Confidential visibility treats the configured secretariat as the privileged operational unit rather than relying solely on a generic register permission.
- Regression coverage added for type-specific creation and registration boundaries. Validation is pending; do not proceed to referrals/workflow integration until focused tests pass. Branch: development; master unchanged.


## 2026-10-20 — Task filters, comment notifications and reminder review

- Reorganized the task-list filters: the search field is now a separate full-width row at the bottom of the filter panel, after the compact selectors, to prevent the large input from disrupting the grid.
- Added a server-provided `canFilterUnit` capability. The «محدوده دستورکار» selector is shown only to users with organization scope or actual managed departments/projects. For ordinary users, a manually supplied `scope=unit` query is normalized away; backend task visibility/authorization remains authoritative.
- Added a regression assertion that an ordinary user's task list does not expose the unit-scope filter. Tests and frontend build have not been run by the assistant.
- Re-inspected task-comment notifications: the current `development` branch already dispatches `TaskCommented` after a comment is stored, the registered notification listener targets the task creator, assignee and participants (excluding the author), and the notification center recognizes `task_commented`. If this is not visible locally, first pull the latest `development` branch and test by adding a comment as a different user; if still absent, inspect runtime event/listener errors and the notifications table.
- Reminder review: reminders are currently persisted as pending rows and displayed in the task detail/dashboard. Users can manually complete or cancel them. No scheduled command/worker was found that sends a notification when `remind_at` arrives, so registering a reminder currently does **not** generate a time-triggered alert. Implementing scheduled reminder notifications is a separate follow-up, requiring scheduler/cron or Windows Task Scheduler setup and a regression test.
- **Branch:** `development`. Keep `master` unchanged.


## 2026-10-20 — Dashboard operational UX and task filters

- Renamed dashboard task terminology to «دستورکار» and managerial «تیم» terminology to «واحد». Added a matching full-width-style «عملکرد شخصی من» panel alongside «عملکرد واحد», with independent personal KPIs and task rows; removed the duplicate KPI strip at the top to avoid repeating the same numbers.
- Connected dashboard KPI cards and status breakdown rows to task-list query filters. Personal scope filters to the signed-in user's assigned tasks; unit scope uses managed departments/projects (or organization scope) and excludes the manager's own assigned tasks. Added due-today, overdue, review-pending, current-month-completed, combined pending-status, assignee, and due-date-range filtering in the task list, with server-side validation and authorization scope retained. Due-date range controls use the existing Jalali date picker.
- Added dashboard lists for pending planned activities and reminders tied to the user's assigned tasks, plus recent comments from other users on those tasks (last seven days).
- Added event-driven database notifications for new task comments to the task creator, assignee, and participants, excluding the commenter. Personal notification center routes/navigation are available to authenticated users, and each user still sees and updates only their own notifications.
- Added regression tests for personal/unit task filters, dashboard follow-up payload, notification center access, and task-comment notifications. Filter option lists are scoped to visible tasks, and due-date range validation handles one-sided ranges correctly. These tests and the frontend build have **not** been run by the assistant; local validation is pending.
- **Branch:** `development`. Keep `master` unchanged.

## 2026-10-20 — Remove the entire dark dashboard hero section

- Removed the complete dark `bg-slate-950` hero section from `resources/js/Pages/Dashboard.vue`, including its headline, explanatory text, and personal-task counter. The dashboard now proceeds directly from the page header to the KPI cards; the task-management/navigation sections remain.
- **Branch:** `development`. Local validation pending; no tests or build were run by the assistant. Keep `master` unchanged.

> This is the concise handoff/checkpoint file for continuing development in a new conversation.

## 2026-10-20 — Dashboard header cleanup

- Removed the redundant “گزارش زنده از داده‌های سامانه” badge from the dashboard hero.
- Removed the two top-of-dashboard shortcut buttons (“مشاهده تسک‌ها” and “پروژه‌ها”) because they duplicated navigation and added little value to the page header.
- **Branch:** `development`. Local validation pending; no tests or build were run by the assistant. Keep `master` unchanged.


## 2026-10-19 — Role-aware dashboard and direct entry routing

- Redesigned `/dashboard` as an operational dashboard with separate personal-task and managerial-team sections. Personal KPIs only count tasks assigned to the signed-in user; team metrics and the team task list are scoped to managed departments/projects or an organization-wide task-view scope.
- Added a real seven-day trend based on visible tasks' creation and completion timestamps, and surfaced team overdue/review/completion metrics separately from personal metrics.
- Kept existing backend task-scope filtering in place; the dashboard does not treat frontend visibility as authorization.
- Changed `/` to redirect guests directly to login and authenticated users to `/dashboard`, removing the extra click through the standalone welcome page. The login page itself remains the existing username/password screen.
- Added dashboard feature assertions for the personal/team payload and root routing. Overdue styling is driven by server-calculated state to avoid browser timezone discrepancies.
- **Branch:** `development`. **Validation pending:** pull changes, run the focused DashboardTest and frontend production build, then manually verify with both an ordinary user and a department/project manager. No tests or build have been run by the assistant. Keep `master` unchanged.


## Last Updated
2026-10-19

## 2026-10-19 — Fix user department membership modal

- Fixed the Vue template nesting in `resources/js/Pages/Core/Users/Index.vue`: the membership dialog was rendered inside the user create/edit dialog, so clicking «واحدها» alone could not display it and a subsequent click on «ویرایش» could reveal the stale membership dialog.
- The membership dialog is now a sibling of the user form dialog. Opening «واحدها» displays membership management independently; opening «ویرایش» displays the user edit form without the membership dialog.
- No routes, authorization rules, or membership persistence logic were changed.
- **Branch:** `development`. **Validation pending:** run frontend production build and manually verify both actions on the user list.

## 2026-10-19 — Lock task content during manager review and protect attachments

- While a task is submitted and awaiting manager review, task-content actions are locked for everyone; only the manager's approve/return actions remain available.
- The review workflow copy now simply says the task is awaiting manager review; it no longer tells users not to change status manually.
- Activity create/complete/edit/delete, work-log changes, comments, reminders, task edits, referral/participants and task attachments are restricted server-side to the current assignee and blocked while review is pending.
- The task page hides those mutation controls while pending and does not offer activity creation/editing to the manager.
- Added a task-specific attachment deletion route; the current assignee can delete an attachment only outside pending review.
- Removed the separate `tasks.submit` route middleware so the active assignee's policy authorization can grant submission without an extra permission.
- **Branch:** `development`. **Validation pending:** run the focused task review tests and `npm run build`; verify submit/return/approve and attachment deletion locally. Do not merge to `master` without user validation.

## 2026-10-19 — Enforce manager-approved task completion

- Every newly created task now requires manager review; it cannot be created directly as completed or cancelled.
- The assigned active user can submit their task for review without requiring the separate `tasks.submit` role permission. Backend policy still restricts submission to the task's current assignee.
- The review workflow is visible for all tasks. Submission moves the task to `submitted/pending` and marks review as required, including older tasks created before this rule.
- Manual status changes are restricted to users with the existing status-change permission who are also the task's department or project manager. Assignees cannot change task status directly.
- Completion remains approval-driven: the manager's approval action changes the task to completed; direct status changes cannot bypass that lifecycle.
- Added/updated regression coverage for an assigned user without `tasks.submit` permission submitting a task.
- **Branch:** `development`. **Validation pending:** run `TaskReviewLifecycleTest` and `npm run build`; do not merge to `master` until user validation.

## 2026-10-19 — Complete task review workflow in detail UI

- Added the missing «ارسال برای بررسی مدیر» action for the assigned user on review-required tasks in `resources/js/Pages/Tasks/Show.vue`, using the existing `tasks.submit` route and permission.
- Added manager-only «تأیید و تکمیل دستورکار» and «برگشت برای اصلاح» actions when a task is pending review. Returning requires a reason, and the reason/reviewer/time are displayed on the task detail page.
- While review is pending, the detail page hides manual status changes and the edit action. For review-required tasks, the UI does not offer manual completion before approval.
- Hardened `ChangeTaskStatusAction` so no manual status transition can bypass a pending manager review; hardened task creation so review-required tasks cannot be created already completed/cancelled.
- Added a regression test that a task awaiting review cannot be manually changed to another status.
- Kept referral separate from review submission; referral continues to transfer responsibility and is not used as the review action.
- **Branch:** `development`. **Validation pending:** run the focused review lifecycle test and frontend production build locally. Do not merge to `master` until user validates.

## Product Goal
Build a Persian-language, RTL, production-oriented company office-automation platform as a Modular Monolith.

Long-term architecture:
**Identity & Organization → Cartable → Workflow → Work Items → Documents → Notifications/Audit**

Projects and Tasks are early business capabilities, not the architectural center of all future modules.

## Version
**Current version: V2 — Office Automation**

V1 core scope is implemented; the shared-project/project-sections extension is in progress and must pass technical validation before acceptance.

**Current checkpoint:** V2 correspondence foundation; numbering/configuration implementation in progress.

**Previous version:** V1 — Foundation & Work Management (V1 regression coverage remains mandatory).

Version boundaries and acceptance gates are authoritative in `ROADMAP.md`.

## V1 Implemented
- Authentication and username-based login
- Invitation-based onboarding and user/profile management
- Roles, permissions and backend authorization
- Protected `SYSTEM_ADMIN` bootstrap account outside normal organizational membership
- Internal departments, hierarchy, memberships, managers, positions and position assignments
- Organization Settings
- Projects, project managers, members and contextual project roles
- Project sections linked to responsible departments, with section-level task-creation authority and section documents
- Tasks, assignment, referral/history, status/priority, comments, work logs, reminders, timeline, filtering, pagination and access scopes
- Task review/approval lifecycle and resubmission
- Task dependencies
- Documents/attachments foundation
- Workflow V1
- Unified Cartable V1
- Notifications V1
- Audit V1 core
- Custom Fields foundation and integration across V1 entities
- System-generated business codes
- External organizations, departments and contacts as a separate external domain
- Persian RTL and Jalali presentation foundations

## Important Domain Decisions
- A Role is a reusable set of application permissions.
- A normal user may have at most one application Role.
- Contextual responsibilities such as Project Manager and Department Manager are assigned in their business/organization context, not as generic global Roles.
- Internal Users/Departments and external Organizations/Contacts must remain separate domains.
- Business modules must not directly manipulate another module's internal data.
- Shared Workflow/Documents/Cartable/Notifications/Audit capabilities must not be duplicated inside future modules.
- Backend authorization is authoritative; frontend permission checks are UX only.
- System/business codes are generated by the application; users should not manually maintain them.
- Custom-field technical keys are internal system data and are not a user-entered field.

## Current Technical State
The previously documented two blockers were re-verified against the actual `development` branch:

### ExternalPartyController
`app/Core/ExternalParties/Http/Controllers/ExternalPartyController.php` is currently syntactically complete. The `custom_fields` validation rules are present in all organization/department/contact create/update actions, and the file does not contain the documented malformed edit.

**Status: resolved in current repository state.**

### HasSystemCode / SQL Server identity
`app/Models/Concerns/HasSystemCode.php` currently uses a query-builder update of only the `code` column after creation instead of Eloquent `updateQuietly()`. This avoids treating the identity primary key as an updateable model attribute.

A dedicated `tests/Feature/Core/SystemCodeGenerationTest.php` also exists and covers generated codes for Core, Projects and External Parties plus explicit system-role codes.

**Status: root fix already present in current repository state; dedicated regression test and full Laravel suite have passed locally.**

### User validation messages
During manual V1 acceptance, creating a user with a password shorter than the enforced 8-character minimum displayed the translation key `validation.min.string` instead of a Persian validation message. A Persian Laravel validation dictionary was added at `lang/fa/validation.php`, covering the relevant validation rules and user attributes.

**Status: fixed in current repository state; local manual confirmation remains.**

## Validation Completed
- Dedicated system-code regression test: passed
- Full PHPUnit/Laravel test suite: passed
- Frontend production build (`npm run build`): passed

## User identity form consistency

The user form no longer asks for a standalone display name in addition to first name and last name. `users.name` remains the system display field for compatibility, but it is now derived from `first_name` + `last_name` during user creation/update. Legacy backend `name` input remains accepted as a compatibility fallback.

**Status: fixed.**

## Immediate Next Sequence
1. Pull the latest `development` change and manually retry creating a user with a password shorter than 8 characters; verify the Persian message is shown.
2. If that passes, continue final V1 manual UI/functional acceptance.
3. Record any newly discovered runtime defects and fix them at root cause.
4. Close the V1 acceptance gate.
5. Only then begin V2.

No V2 implementation is authorized before V1 acceptance is explicitly closed.

## Development Protocol
- Repository: `mehdijalilimaraghi-del/office-automation`
- Development branch: `development`
- Stable branch: `master`
- Never develop directly on `master`.
- Inspect the repository and persistent project documents before changing code.
- Make ordinary implementation/debugging changes directly when user approval is not required.
- Assistant writes/edits code and tests in GitHub.
- User runs the application/tests on their own computer.
- After every code change, provide the exact PowerShell validation command.
- Put `git pull origin development` at the beginning of the validation command only when code changes were made immediately before that validation.
- If no code was changed, do not add `git pull origin development`.
- Prefer one validation step at a time.
- Use standard production-quality Laravel/PHP/Vue/TypeScript patterns.
- Add appropriate tests for meaningful behavior and regressions.
- Fix root causes; do not hide errors by weakening tests or using destructive changes.
- UI/UX should follow modern Google Material Design principles adapted to Persian RTL.
- Record meaningful implementation decisions, completed work and blockers in the project documentation.

## Documentation Authority
1. `AI_CONTEXT.md` — mandatory compact handoff and working protocol for any AI/engineer
2. `ROADMAP.md` — product versions, scope and acceptance gates
3. `CURRENT_STATUS.md` — current state, blockers and immediate next steps
4. `PROJECT_CONTEXT.md` — persistent detailed context
5. `ARCHITECTURE.md` — architecture and module boundaries
6. `PROJECT_SPEC.md` — system/product specification
7. `DEVELOPMENT.md` — engineering workflow

## New-Conversation Rule
At the start of a new conversation, read `AI_CONTEXT.md` and `CURRENT_STATUS.md`, then inspect `ROADMAP.md` and the relevant implementation before making the next code change. Continue from the documented checkpoint; do not restart or redesign the project.


## User Controller Regression Fix — 2026-10-06
The user identity change exposed a PHP syntax error in `UpdateUserAction.php` caused by the display-name expression. The expression was simplified to explicit first-name/last-name variables and a direct concatenation. **Status: fixed; focused test passed; full UserControllerTest exposed a legacy-name compatibility regression in update, now fixed.**


## User update compatibility regression — 2026-10-06
The full `UserControllerTest` exposed that `UpdateUserAction` no longer honored the documented legacy `name` compatibility fallback when an older caller submitted `name` without `first_name`/`last_name`. The update action now derives the display name from first/last name and falls back to the legacy `name` input when those fields are absent/blank.

**Status: fixed; full `UserControllerTest` validation is pending.**


## Required-field indicators — 2026-10-06
During manual V1 UI acceptance, required fields were found to lack a consistent visual indicator. The frontend now renders a red `*` beside labels whose associated control is required, with required attributes completed for the applicable V1 forms. External-party forms were also given visible labels for their required fields. Conditional user-password validation is reflected by a dynamic required attribute.

**Status: implemented; frontend build validation pending.**


## External parties navigation — 2026-10-06
The external-parties page now uses the standard authenticated layout so the main navigation remains available. A dedicated «بازگشت» action was also added to return to the previous page, with dashboard fallback when browser history is unavailable.

**Status: implemented; frontend build validation pending.**


## External parties hierarchy UI — 2026-10-06
The external department screen now presents departments as a visible hierarchy. Parent-child indentation is shown in the department list, and the «واحد بالادست» selector uses the same hierarchical ordering so nested units are distinguishable during creation/editing. The external-parties back action was also moved into a typed handler to resolve the Vue/TypeScript build error.

**Status: implemented; frontend build validation pending.**

## Task default-project visibility — 2026-10-06
Department creation already persists a real system-default project in the database. The task creation options previously loaded only projects with explicit project membership, so a department's default project could exist in the database but remain absent from the task project selector. Task creation options, authorization, assignee validation, and task project access now recognize system-default projects through the user's department membership.
**Status: implemented; backend/frontend validation pending.**

## Project view scope — 2026-10-06
Project listing and individual project viewing now honor the `projects.view` permission scope. Users with unit scope can see projects belonging to their units, including unit default projects; department-tree, project, self, and organization scopes are also enforced consistently.
**Status: implemented; backend validation pending.**


## Permission scope audit — 2026-10-06
A cross-check of the current role/permission implementation found that permission existence is enforced broadly, but permission **scope** is not yet enforced consistently across every action. The clearest current defect was task creation project options: organization-scoped users (including the bootstrap system administrator) could have `tasks.create` permission but still receive only explicitly related projects. Task project options now honor `tasks.create` scopes: organization sees all active projects; department/tree scopes include the corresponding department projects; project/self scopes are applied, while direct project membership remains available.

The audit also identified remaining scope gaps in several task actions (`refer`, `submit`, `review`, reminder actions) and project mutation actions that currently check permission existence plus contextual ownership/manager rules without applying the configured permission scope. These must be normalized before V1 authorization can be considered fully consistent.

**Status: audit in progress; task project-option defect fixed; broader scope normalization pending.**


## Permission scope normalization — 2026-10-06
The V1 authorization audit continued beyond project listing. Project mutation policies now apply the configured permission scope in addition to the existing manager/context rule. Task create, refer, submit, review, and reminder completion/cancellation now also pass through scoped task access rather than checking permission existence alone. This closes several identified cases where a Role permission was technically present but its configured scope was ignored.
**Status: implemented; full backend validation pending.**


### Permission-scope test fixture normalization (2026-10-06)
- tests/Feature/Modules/Tasks/TaskHttpTest.php now creates task permission fixtures with the same PermissionScope::defaultForPermission() semantics used by production role-permission synchronization.
- This prevents the database default organization scope from masking project-scoped authorization in HTTP tests.
- TaskHttpTest passes after this normalization.


### Permission Scope Capability Audit — 2026-10-06
- V1 permission scopes are now capability-aware: only `tasks.*` and `projects.*` permissions accept contextual scopes (`self`, `department`, `department_tree`, `project`, `organization`). Other V1 permissions are organization-wide and are persisted only with `organization` scope.
- Role permission validation and the synchronization action reject invalid scope combinations; the role-permission UI no longer presents misleading scope selectors for non-scope-aware permissions.
- Regression coverage added for rejecting a non-organization scope on `users.view` and accepting project scope on `projects.view`.


### UI terminology and task page normalization — 2026-10-06
- V1 UI terminology for `Task/Tasks` is standardized to Persian **وظیفه/وظایف** in the task list/create experience.
- The Tasks page was redesigned so the **وظایف** list is the primary content; creation is opened through **ایجاد وظیفه** in a modal instead of occupying the top of the page.


## 2026-10-07 — V1 Task Detail UX
- بازطراحی صفحه جزئیات وظیفه به‌عنوان workspace عملیاتی: اقدامات اصلی در بالای صفحه، ویرایش و ارجاع به‌صورت اقدام سریع، گزارش فعالیت و خط زمانی در محتوای اصلی، اطلاعات پروژه/پیوست/پیگیری خارجی در ستون کناری و نظرات/یادآوری‌ها در انتهای صفحه.
- اصطلاح کاربری `تسک` در صفحه جزئیات به `وظیفه` استاندارد شد.
- قابلیت «افراد درگیر» با امکان انتخاب چند کاربر و نمایش اطلاعات تماس داخلی اضافه شد.
- پیگیری خارجی در صفحه جزئیات به‌صورت واضح نمایش داده می‌شود و فرم ویرایش نیز در همان صفحه در دسترس است.


### 2026-10-07 — Task activities and related users
- مسئول وظیفه همچنان یک نفر اصلی است؛ ارتباط با سایر کارکنان به‌عنوان «افراد مرتبط» نگهداری می‌شود و محدود به اعضای پروژه نیست.
- جدول `task_activities` برای فعالیت‌های برنامه‌ریزی‌شده و فعالیت‌های ثبت‌شده اضافه شد. هنگام ایجاد وظیفه می‌توان «فعالیت بعدی» را تعیین کرد و با انجام آن، سابقه فعالیت حفظ می‌شود.
- فعالیت‌های بعدی به‌صورت رکورد مستقل مدل شده‌اند تا امکان زنجیره فعالیت‌ها و ثبت فعالیت‌های جانبی بدون از بین رفتن ترتیب برنامه‌ریزی وجود داشته باشد.
**Status:** implementation complete; frontend/build and focused backend validation pending.


### 2026-10-07 — Task detail model correction
- وظیفه فقط یک مسئول اصلی دارد؛ مفهوم «افراد مرتبط» از UI حذف شد.
- اطلاعات سازمان/واحد/شخص خارج از شرکت در بخش «ارتباط و پیگیری بیرونی» قرار دارد.
- «فعالیت‌های بعدی» به‌صورت فهرست مستقل نمایش داده می‌شود و هر مورد پس از انجام با تاریخ و زمان تکمیل ثبت می‌شود.
- فعالیت بعدی مسئول جداگانه ندارد و مسئول آن همان مسئول اصلی وظیفه است.
**Status:** implemented; frontend/build and focused backend validation pending.

### 2026-10-07 — Task detail information layout correction
- بخش «اقدام بعدی» از بالای صفحه حذف شد؛ فعالیت‌های برنامه‌ریزی‌شده فقط در بخش «فعالیت‌های بعدی» نمایش داده می‌شوند.
- مسئول واحد، مسئول وظیفه، شروع و مهلت به «اطلاعات پروژه» منتقل شدند و توضیحات نیز زیر همان اطلاعات نمایش داده می‌شود.
- «ارتباط و پیگیری بیرونی» از فرم ویرایش وظیفه جدا شد و از بخش مستقل خودش قابل ثبت، ویرایش و حذف است.

**Status:** implemented; frontend build validation pending.


### 2026-10-07 — Task referral and external follow-up UX correction
- فاصله بالای محتوای جزئیات وظیفه اصلاح شد.
- «خط زمانی» که برای این صفحه مفهوم مناسبی نداشت با «سوابق ارجاع وظیفه» جایگزین شد و مسیر ارجاع از شخص مبدأ به شخص مقصد و ثبت‌کننده ارجاع را به‌صورت واضح نمایش می‌دهد.
- دکمه «ارجاع وظیفه» در بخش سوابق ارجاع نیز در دسترس است.
- بخش «ارتباط و پیگیری بیرونی» اکنون دکمه ثبت/ویرایش و کنترل حذف ارتباط دارد.

**Status:** implemented; frontend build validation pending.

### 2026-10-07 — Task detail referral TypeScript fix
- مدل TypeScript صفحه جزئیات وظیفه با داده `assignments` هماهنگ شد تا سوابق ارجاع وظیفه بدون خطای `vue-tsc` build شوند.

**Status:** implementation complete; frontend build validation pending.

### 2026-10-07 — Task referral/external controls correction
- دسترسی «ارجاع وظیفه» برای مسئول اصلی، مدیر واحد و مدیر پروژه در صورت داشتن مجوز و دسترسی وظیفه فعال شد.
- دکمه «ارجاع وظیفه» در بخش «سوابق ارجاع وظیفه» نمایش داده می‌شود.
- بخش «ارتباط و پیگیری بیرونی» دکمه‌های «ثبت ارتباط / ویرایش ارتباط» و «حذف ارتباط» را مستقیماً در صفحه جزئیات نمایش می‌دهد.

**Status:** implementation complete; frontend build and focused backend validation pending.


### 2026-10-07 — Task detail referral/external follow-up fix
- Task referral is available to users with `tasks.update` access when they are the current assignee, department manager, or project manager; route and policy are aligned with this authorization model.
- External organization/department/contact saving now explicitly refreshes task data after a successful update, and validation errors are visible in the form.
- HTTP coverage added for referral persistence and external-party persistence.


### 2026-10-07 — Referral permission correction
- Task referral visibility/authorization now depends on the dedicated `tasks.refer` permission and its configured scope; it is not restricted by assignee, department-manager, or project-manager identity.
- Task activity completion binding now compares foreign-key IDs by value, preventing false 404 responses.


### 2026-10-07 — Task referral permission migration fix
- تست HTTP ارجاع وظیفه در دیتابیس تست به‌دلیل نبودن رکورد `tasks.refer` با `ModelNotFoundException` متوقف می‌شد.
- با توجه به اینکه `CorePermissionSeeder` این مجوز را دارد اما دیتابیس‌های موجود ممکن است قبلاً migrate شده باشند، migration مستقل برای ایجاد/به‌روزرسانی `tasks.refer` اضافه شد.

**Status:** code fix committed; focused TaskHttpTest validation pending.


### 2026-10-07 — Task referral member selection and terminology correction
- منوی «کار و عملیات» اکنون به‌جای «تسک‌ها» عنوان «وظایف» را نمایش می‌دهد.
- فهرست مسئول جدید در ارجاع وظیفه برای پروژه‌های پیش‌فرض واحد، تمام اعضای همان واحد را نمایش می‌دهد و حساب `SYSTEM_ADMIN` از فهرست حذف می‌ماند.
- اعتبارسنجی backend ارجاع نیز برای پروژه پیش‌فرض، عضویت در واحد مرتبط را به‌عنوان عضویت معتبر می‌پذیرد.
- خطای `assignee_id` در پنجره ارجاع نمایش داده می‌شود تا خطاهای ذخیره‌سازی پنهان نمانند.

**Status:** implementation complete; focused TaskHttpTest validation pending.


### 2026-10-07 — Task detail relation serialization fix
- مشخص شد داده‌های رابطه‌ای Eloquent در پاسخ Inertia با کلیدهای snake_case (`external_organization`, `external_department`, `external_contact`, `from_user`, `to_user`, `assigned_by`) به Vue می‌رسند، در حالی که صفحه جزئیات با نام‌های camelCase آن‌ها را می‌خواند.
- صفحه جزئیات وظیفه اصلاح شد تا «ارتباط و پیگیری بیرونی» بعد از ذخیره واقعاً نمایش داده شود و سوابق ارجاع نیز نام فرستنده، گیرنده و ثبت‌کننده را درست نشان دهند.

**Status:** code fix committed; frontend build validation pending.


### 2026-10-07 — Multiple external contacts on task detail
- «ارتباط و پیگیری بیرونی» اکنون می‌تواند چند شخص خارجی را هم‌زمان به یک وظیفه متصل کند.
- رابطه اشخاص خارجی از ستون تکی external_contact_id به جدول رابطه‌ای task_external_contacts منتقل شد و داده قبلی نیز هنگام migration حفظ می‌شود.
- صفحه جزئیات، اطلاعات هر شخص خارجی را به‌صورت کارت مستقل نمایش می‌دهد و پنجره ثبت ارتباط امکان انتخاب چند شخص را دارد.

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

### 2026-10-07 — اصلاح انتخاب تاریخ و ساعت یادآوری وظیفه
- انتخاب زمان یادآوری از DateTimePicker قبلی جدا شد و به یک پنجره مرحله‌ای تبدیل شد: ابتدا تاریخ، سپس ساعت و دقیقه، و در پایان دکمه «تأیید تاریخ و ساعت».
- انتخاب تاریخ یا ساعت دیگر باعث بسته‌شدن پنجره نمی‌شود و زمان فقط پس از تأیید به فرم یادآوری منتقل می‌شود.
- مقدار نهایی به‌صورت زمان محلی بدون تبدیل ضمنی timezone ارسال می‌شود تا ساعت انتخاب‌شده با ساعت ذخیره و نمایش داده‌شده یکسان بماند.

**Status:** implementation complete; frontend build validation pending.

### 2026-10-07 — تکمیل ویرایش/حذف نظر و یادآوری وظیفه
- برای نظرات و یادآوری‌های وظیفه، ویرایش و حذف در جزئیات وظیفه اضافه شد؛ هر دو همچنان چندرکوردی هستند.
- نمایش `remind_at` برای یادآوری با فرمت صریح `Y-m-d H:i:s` اصلاح شد تا زمان انتخاب‌شده در UI هنگام serialization به UTC ISO تبدیل نشود و ساعت ثبت‌شده تغییر نکند.
- تست HTTP برای حفظ دقیق زمان یادآوری و عملیات ویرایش/حذف اضافه شد.

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
- در بخش «گزارش فعالیت» صفحه جزئیات وظیفه، دکمه «ثبت فعالیت» اضافه شد و در صورت داشتن Ability مربوط به ثبت گزارش فعالیت، پنجره ثبت فعالیت را باز می‌کند.

**Status:** implementation complete; focused TaskHttpTest validation pending.


### 2026-10-10 — Task activity lifecycle and edit/delete
- فعالیت‌های برنامه‌ریزی‌شده پس از فشردن «انجام شد» در همان بخش و زیر عنوان «فعالیت‌های انجام‌شده» باقی می‌مانند؛ زمان تکمیل نیز نمایش داده می‌شود.
- برای هر فعالیت برنامه‌ریزی‌شده، چه در انتظار انجام و چه تکمیل‌شده، دکمه‌های ویرایش و حذف اضافه شد.
- Route و عملیات backend برای ویرایش/حذف فعالیت با بررسی تعلق رکورد به وظیفه و مجوز ویرایش وظیفه اضافه شدند.

**Status:** implementation complete; TaskHttpTest and frontend build validation pending.

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


### 2026-10-10 — Unified task activity storage and guidance
- جدول مرجع برای فعالیت‌های برنامه‌ریزی‌شده و گزارش‌های انجام‌شده، `task_activities` است؛ تفاوت با `activity_type` و `status` مشخص می‌شود.
- با زدن «انجام شد»، همان رکورد به `logged/completed` تغییر می‌کند و دیگر بین دو جدول کپی یا حذف نمی‌شود.
- Migration جدید سوابق `task_work_logs` را همراه با عنوان، شرح، زمان شروع/پایان و کاربر ثبت‌کننده به `task_activities` منتقل می‌کند و سپس جدول قدیمی را حذف می‌کند؛ مسیر rollback نیز داده‌های گزارش‌شده را بازسازی می‌کند.
- ویرایش/حذف گزارش‌ها از Routeهای یکپارچه فعالیت استفاده می‌کند. دکمه «ثبت فعالیت انجام‌شده» و راهنمای دائمی تفاوت دو بخش در صفحه جزئیات اضافه شده‌اند.

**Status:** implementation committed; focused `TaskHttpTest` and frontend production build validation pending.


## Task activity model — 2026-10-10
- `task_activities` is the single source of truth for planned next activities and completed activity reports.
- Planned activities use `activity_type=planned, status=pending`; completed/manual activity reports use `activity_type=logged, status=completed`.
- Completing a planned activity updates the same record and preserves `completed_at`. The data migration adds `started_at`, transfers legacy `task_work_logs` records, and then removes the old table.
- The task detail page labels the action `ثبت فعالیت انجام‌شده`, includes persistent guidance for both workflows, and adds hover hints to the next-activity controls.
- Validation for the focused task HTTP tests is pending after this change; V1 remains in technical validation and manual acceptance. V2 is not started.


## 2026-10-10 — Unified task activities and test fixture correction
- `task_activities` is the single table for both planned activities and completed activity reports. Completing a planned activity updates that same record to `activity_type=logged`, `status=completed`, and stores the completion timestamp; manual completed activities are stored in the same table.
- Historical rows from `task_work_logs` are migrated into `task_activities` by `2026_10_10_120000_unify_task_activities_and_work_logs.php` before the old table is removed.
- Task detail UI labels the manual-entry action «ثبت فعالیت انجام‌شده» and includes a persistent guide explaining when to use «فعالیت‌های بعدی» versus «گزارش فعالیت»; button tooltips provide short contextual guidance.
- The focused HTTP test failed before exercising the manual activity flow because the test database lacked the seeded `tasks.log_work` permission. `TaskHttpTest` now seeds `CorePermissionSeeder` during setup so the test fixture includes the canonical permission registry.

**Validation status:** focused `TaskHttpTest` and frontend build are pending after this fixture correction.


### 2026-10-10 — Restore previous activity report entries and relocate guide
- بخش «گزارش فعالیت» اکنون مستقیماً از رکوردهای تکمیل‌شده‌ی رابطه‌ی واحد `task.activities` نمایش داده می‌شود، نه از timeline عمومی؛ این بخش دیگر به شکل‌دهی timeline برای فهرست فعالیت‌ها وابسته نیست.
- راهنمای کامل از «گزارش فعالیت» به بالای بخش «فعالیت‌های بعدی» منتقل شد تا قبل از ثبت، تفاوت فعالیت برنامه‌ریزی‌شده و فعالیت انجام‌شده روشن باشد.
- تست HTTP نمایش فعالیت تکمیل‌شده‌ی از قبل ثبت‌شده در صفحه جزئیات اضافه شد.

**Validation:** migration، تست HTTP وظایف و build فرانت‌اند باید در محیط محلی اجرا شوند.

### 2026-10-10 — Task activity guide layout correction
- راهنمای فعالیت‌ها از سربرگ «فعالیت‌های بعدی» خارج شد و به سکشن مستقلِ تمام‌عرض، زیر چیدمان دو‌ستونه صفحه جزئیات وظیفه منتقل شد؛ بنابراین متن دیگر فضای عنوان و دکمه ثبت فعالیت بعدی را فشرده نمی‌کند.


### 2026-10-10 — Remove task activity guide section
- طبق بازخورد کاربر، راهنمای دائمی فعالیت‌ها از صفحه جزئیات وظیفه حذف شد تا پایین صفحه یک سکشن اضافی ایجاد نکند.
- بررسی یادآوری‌ها: فعلاً ثبت، ویرایش، حذف و تغییر وضعیت یادآوری وجود دارد؛ اما در مسیرهای بررسی‌شده، زمان‌بندی اجرای یادآوری یا نمایش پاپ‌آپ هنگام رسیدن زمان پیدا نشد. این قابلیت هنوز نیازمند پیاده‌سازی است.


### 2026-10-10 — Defer task activity guide layout to normal-user acceptance
- راهنمای فعالیت‌ها فعلاً در صفحه جزئیات وظیفه نمایش داده نمی‌شود؛ کاربر تأکید کرد که این مورد باید هنگام بررسی نهایی با حساب کاربری عادی ارزیابی شود، نه با حساب مدیر سیستم.
- در مرحله پذیرش دستی V1، با حساب کاربری عادی بررسی شود که راهنما در یک سکشن مستقل و تمام‌عرض، زیر چیدمان دو‌ستونه «فعالیت‌های بعدی» و «ثبت فعالیت بعدی» قرار بگیرد؛ متن راهنما نباید داخل هیچ‌یک از دو ستون باشد.
- تا زمان آن بررسی، تغییر دیگری برای نمایش راهنما انجام نشود.

### 2026-10-10 — Task activity guide full-width placement
- راهنمای فعالیت‌ها در یک سکشن مستقل و تمام‌عرض، زیر چیدمان دو‌ستونه صفحه جزئیات وظیفه قرار گرفت؛ سکشن‌های «فعالیت‌های بعدی» و «گزارش فعالیت» بدون تغییر در جای خود باقی ماندند.
- متن راهنما توضیح می‌دهد چه زمانی از «فعالیت‌های بعدی» و چه زمانی از «ثبت فعالیت انجام‌شده» استفاده شود.
- **Validation:** فرانت‌اند هنوز باید در محیط محلی build شود.


## Project Sections / Shared Project — 2026-10-10

Implementation has started for the agreed cross-department project model:
- New `project_sections` entity belongs to a project and a responsible internal department.
- Each section stores its name, description, ordering, and whether its department manager may create tasks.
- Project creation accepts multiple sections.
- Section tasks are created with the department manager as the initial assignee; an optional direct referral to an active member of that department records both assignment-history steps.
- Project detail UI groups tasks by section, shows the fixed project/section task modal, and supports general and section-specific document uploads.
- Department creation ensures a section in the shared default project instead of creating a separate project.
- A migration consolidates existing department-default projects into one shared default project, creates a section per legacy department project, and preserves legacy project rows as inactive for historical references.

**Validation status:** not yet run in a local Laravel environment. Migration, backend authorization, tests, and frontend build must be validated before this work is considered complete. Existing per-department default projects are hidden from ordinary project selection; the shared default project is created by `db:seed` on fresh installs.


## 2026-10-12 — Restore system administrator and combine role/responsibility access

- The active `SYSTEM_ADMIN` user now receives every permission and organization-wide scope centrally through `User::hasPermission()` and `User::permissionScopes()`. This no longer depends on the role seeder having every newly added permission attached; inactive users remain denied.
- The role-permission editor now allows active operational permissions (including project/task permissions) to be granted explicitly to a normal application role, with scope validation retained.
- Role permission synchronization no longer rejects responsibility-derived permissions. Explicit role grants and access derived from actual organizational/project/task responsibilities are combined, so a role grant can provide an additional capability beyond the user's position or contextual responsibility.
- `CorePermissionSeeder` no longer detaches operational grants from non-system roles on every seed run. Historical grants removed by the earlier migration are not automatically restored; an administrator can now assign them again in the role editor.
- Added regression coverage for system-admin permission/middleware bypass, operational permission assignment to a role, and preserving explicit operational grants during permission reseeding.

**Validation status:** code committed to `development`; focused Laravel tests and frontend production build are pending. V1 remains the active version; V2 has not started.


### 2026-10-12 — Fix permission seeder test syntax
- The focused authorization test run stopped before executing tests because `tests/Feature/Core/CorePermissionSeederTest.php` contained an invalid fully qualified class reference with doubled namespace separators.
- Corrected the reference so PHPUnit can parse the test file.
- The user also requested PowerShell validation commands avoid Persian console messages and must not close the shell on failure. Future validation commands should use English status text and report `$LASTEXITCODE` without calling `exit`.

**Validation status:** syntax fix committed to `development`; focused authorization tests must be rerun locally.


### 2026-10-12 — Correct authorization test fixtures after focused run
- Two `UserAuthorizationTest` failures were caused by its shared test-role helper using the reserved `SYSTEM_ADMIN` role code. Since system-admin access intentionally bypasses explicit grants, those tests were accidentally testing the admin bypass instead of normal-role behavior. The helper now uses `TEST_ROLE`; the dedicated system-admin tests continue to create `SYSTEM_ADMIN` explicitly.
- `RolePermissionControllerTest` created `tasks.view` directly even though the permission already existed from `grantPermissions()`. The test now reuses the canonical permission by code, avoiding SQL Server's unique-key violation.
- The previous focused run confirmed `PermissionMiddlewareTest` and `CorePermissionSeederTest` pass. The authorization and role-permission test files need to be rerun after these fixture fixes.

**Validation status:** test-fixture fixes committed to `development`; rerun the focused test set. PowerShell instructions must leave the session open on errors and use English console messages.


## 2026-10-13 — V2 Office Automation kickoff

- Project owner authorized starting V2 while any remaining V1 manual-acceptance notes remain visible and tracked.
- V1 validation reported by the project owner: full backend suite passed (380 tests); frontend production build succeeded.
- First V2 slice: correspondence foundation. Inspect and reuse the existing Workflow, Documents, Cartable, Notifications and Audit modules; do not introduce parallel implementations of those shared capabilities.
- V2 includes incoming/outgoing/internal correspondence, draft/registered lifecycle, numbering, referrals/instructions/replies, attachments, task/workflow links, Cartable actions, notifications and audit, followed by Forms & Requests.
- Full Digital Archive remains V3. PBX remains V4 (standalone) and CRM V5; PBX ↔ CRM integration stays deferred until both modules exist.
- Validation for new V2 code must include focused backend tests, authorization checks, full regression tests, frontend production build where UI changes, and manual Persian RTL acceptance.


### 2026-10-13 — V2 correspondence foundation (first code slice)

- Added the `Correspondence` model and initial `correspondences` schema with incoming/outgoing/internal type values, draft/registered lifecycle fields, optional registration metadata, department ownership, workflow linkage, reply threading, and document-link relationship.
- Added `CorrespondenceService::createDraft()` to create drafts transactionally and reject unsupported correspondence types or blank subjects.
- Added focused service tests for incoming drafts, outgoing/internal draft types, invalid type rejection and blank-subject rejection.
- Files are committed to `development`; tests and migrations have **not yet been run locally**. Registration-number allocation, HTTP authorization/routes, UI, referral/instruction/reply workflows and Cartable integration remain future slices.


## 2026-10-14 — V2 correspondence numbering and settings

- Organization Settings now include correspondence numbering presets (simple sequence, Jalali year + sequence, Jalali year + department code + sequence), custom ordering of year/department/sequence, separator and sequence padding.
- Organization Settings also expose the writing method policy (internal editor, Word upload, or both) and approval policy per incoming/outgoing/internal correspondence type.
- Added a database-backed singleton counter and a correspondence numbering service. Number allocation is serialized with a row lock; final registration updates status, number, timestamp and registrar in one transaction.
- Registration enforces the configured approval policy: correspondence types requiring approval cannot be registered until their linked workflow instance is completed.
- Added tests for preset/custom number formatting, counter increments, final registration, duplicate registration prevention, approval gating, and persistence of settings.
- **Not yet validated locally:** focused Laravel tests/migrations and frontend production build must be run by the project owner.
- **Completed on development:** authorized correspondence list/draft creation/final-registration HTTP flows, internal editor/file/both composition modes, shared-document attachment linking and attachment-view authorization. Local validation remains pending.
- **Next slice:** correspondence detail/edit, referrals/instructions, audit/notifications and Cartable actions.


## Document access authorization alignment — 2026-10-18

- Audited document authorization on `development`: operational access to a task/project was being checked separately from global `documents.view` and `documents.create` grants. This caused authorized task assignees and project/section managers to be blocked from attachments.
- Task attachments now rely on the already-scoped task update policy for upload authorization. Project and section attachments rely on the existing project/section responsibility checks; a separate global document-create grant is not required for these contextual attachment routes.
- Linked-document viewing/downloading now accepts scoped access to the linked task/project/section. Explicit document-level creator/access grants still require `documents.view`; the linked-record check remains authoritative for contextual attachments.
- Added regression coverage for an assigned user attaching and downloading a file without document-specific permissions, and for an unrelated user being denied upload.
- **Branch:** `development`. **Status:** implementation committed; local focused Laravel tests and the full suite are pending. Do not cherry-pick/merge to `master` until validation and user acceptance.


## Follow-up: contextual document route middleware — 2026-10-18

- The first focused test run exposed an additional authorization layer: route middleware still required `documents.create` on task/project/section attachment routes and `documents.view` on download, before the scoped policies could evaluate responsibility-based access.
- Removed those global document-permission middleware checks only from contextual attachment routes and document download. The controller still authorizes uploads against task/project/section responsibility, and download still authorizes through the document policy.
- **Branch:** `development`. Focused tests must be rerun; do not promote to `master` until green and accepted.


## Follow-up: contextual attachment buttons — 2026-10-18

- Manual acceptance found that normal users could view/download linked documents but the upload controls were hidden because the Inertia props still required the global `documents.create` permission.
- Task upload visibility now follows the scoped task-update authorization already enforced by the upload controller. Project-level upload visibility follows project responsibility or organization-scoped project administration; section upload visibility also includes the responsible department manager without requiring a global document permission.
- Added regression assertions for task and project upload-control authorization for users who have contextual access but lack document-specific permissions.
- **Branch:** `development`. Run the focused document, task HTTP, and project HTTP tests plus the frontend production build before acceptance. Keep `master` unchanged until explicitly approved.

## Project public-document upload button UX — 2026-10-18

- In the project detail page under «مستندات → مدارک عمومی پروژه», replaced the text upload control with a compact sky-blue icon-only upload button.
- Added an upload icon, hover/focus tooltip «افزودن فایل», an accessible label, visible keyboard focus, and a loading indicator while upload is in progress.
- Upload behavior, accepted file types, and existing authorization visibility remain unchanged.
- **Branch:** `development`. Local frontend production build and manual hover/focus/upload acceptance are pending; keep `master` unchanged.

## Frontend Task type fix — 2026-10-19

- Fixed the TypeScript build error in `resources/js/Pages/Tasks/Show.vue` by declaring `assignee_id: number | null` on the frontend `Task` interface, matching the existing assignee fallback used by the current-assignee check.
- Commit: `4e62fdb70e7c649a45111df10251f753461faca2`.
- **Branch:** `development`. Run `npm run build` and the focused task review tests locally. Keep `master` unchanged; promotion is deferred until Version 2 is complete and explicitly approved.

## Corrected Task assignee type placement — 2026-10-19

- Follow-up build output showed the previous insertion matched `TaskActivity` instead of `Task`, creating a duplicate field and leaving `Task.assignee_id` undeclared.
- Corrected `resources/js/Pages/Tasks/Show.vue`: removed the duplicate from `TaskActivity` and declared `assignee_id: number | null` on `Task` itself.
- Commit: `8e4886c8b0b8f83c5d8fffa1c2064606480e626a`.
- **Branch:** `development`; build verification is pending. Do not promote to `master` before Version 2 is complete.

## Task comments authorization — 2026-10-19

- Comment creation now follows task visibility: anyone authorized to view a task can add a comment, including during manager review. It no longer requires the separate `tasks.comment` permission or the assignee-only task-content edit check.
- Comment edit/delete routes are authorized in the controller: the actor must be able to view the task, the comment must belong to that task, and the actor must be the comment author. Route-level `tasks.update` middleware was removed for these routes so authors do not need task-edit permission to manage their own comments.
- The task detail UI shows the create-comment action to viewers and only shows edit/delete controls on comments authored by the current user.
- Updated the focused HTTP test fixture to use `tasks.view` for comment creation.
- Commits: `5d6a1b27485bf53fb3fa405b26a5ec05ab27741a`, `c5dab070531f65c70acaea073b53019536d285ed`, `6ad27dc6edb4524e6f319fa46b4a4b42cc893ef6`, `1592bed1dc192bf10655b5479f9d0f8fb9d3fd2f`, `933e83b68c292d1f068200f68a0c4e086e1545b5`.
- **Branch:** `development`. Run the focused task HTTP tests and frontend production build before acceptance. Keep `master` unchanged until Version 2 is complete.

## Comment author controls follow-up — 2026-10-19

- Adjusted the comment UI ownership check to use the persisted `comments.user_id` field rather than the nested `comments.user.id` relation. This ensures managers and other viewers see edit/delete controls for their own comments even if the nested user relation is not serialized as expected.
- Backend ownership enforcement remains unchanged: only the authenticated comment author may update or delete a comment.
- Commit: `9466357a6b5035157736fc3e673606b1724da3bb` on `development`. Frontend build and manager-side verification are pending. Do not merge to `master` until Version 2 is complete.


## Task comment controls — 2026-10-19 (root-cause fix)

- Root cause confirmed in `resources/js/Pages/Tasks/Show.vue`: the edit/delete buttons were wrapped in `abilities.update && canEditTaskContent`, which hides comment controls from managers who are not the assignee or when task content is locked for review. Comment ownership is independent of task-content edit permission.
- Removed that unrelated wrapper; each control is shown only when `comment.user_id` matches `currentUserId`. Both edit and delete now use the same persisted author ID.
- Backend author-only enforcement remains unchanged.
- **Branch:** `development`. Frontend build and manager-side manual verification are pending. Keep `master` unchanged until V2 is complete and explicitly approved.


## 2026-10-19 — Implement task main/sub-status lifecycle and final-state lock

- Added nullable `tasks.sub_status` with a reversible migration. Existing `submitted/pending` tasks are migrated to main status `in_progress` with sub-status `manager_review`; rejected review tasks receive `returned_for_completion`.
- Main statuses now use `todo`, `observed`, `in_progress`, `completed`, `cancelled`, and `cannot_continue`. Creation starts automatically at `todo`; manual status changes are limited to manager-authorized `cancelled` and `cannot_continue`. Completion remains exclusive to manager approval.
- Viewing a task as its current assignee moves `todo` to `observed`; viewing a newly referred task marks `referral_seen`. Adding/completing/logging the first activity moves `todo/observed` to `in_progress` and clears transient referral/return sub-statuses.
- Sub-status values implemented: `referred`, `referral_seen`, `manager_review`, and `returned_for_completion`. Task detail/list status badges render the sub-status in parentheses; timeline entries now include transition notes.
- Manager return preserves main status `in_progress` and sets `returned_for_completion`. Manager approval sets `completed` and clears the sub-status.
- Terminal states `completed`, `cancelled`, and `cannot_continue` are guarded in policy, controller, task actions, comments, reminders, assignments and attachment endpoints. Read access remains available; mutations are blocked server-side and mutation controls are hidden in the task UI. The pre-existing manager-review lock is also enforced across assignment, content edits, comments, reminders, deletion, and attachment paths.
- Added/updated task review, service and status-history tests for main/sub-status behavior, manager-only terminal transitions, and rejection of direct completion. Timeline history now records automatic view, manager-review and return notes.
- The UI hides comment edit/delete, activity, reminder, referral, attachment, content-edit, and status mutation controls for finalized tasks; the server independently rejects those mutations.
- **Branch:** `development`. **Validation pending:** run `php artisan migrate`, focused task lifecycle/review/HTTP tests, the full backend test suite and `npm run build`; manually verify automatic statuses, manager review/return/approval, final-state locking, and existing data migration. No test result is claimed yet. Keep `master` unchanged until Version 2 is complete and explicitly approved.

## Task lifecycle regression-test alignment — 2026-10-19

- Updated notification and workflow review fixtures to use the current review state: main status `in_progress`, sub-status `manager_review`, and review status `pending`.
- Updated the task status-scope HTTP test to submit a valid manager-only terminal status so it exercises authorization rather than failing earlier in request validation.
- Updated task creation coverage to assert that new tasks start in the automatic `todo` state, and adjusted status-policy coverage to reflect that an assignee without manager responsibility cannot manually change task status.
- Updated the HTTP status test to cover manager-authorized cancellation rather than direct completion; cancellation does not set `completed_at`.
- **Validation pending:** these are source/test updates committed to `development`; rerun the four affected test files and then the full Laravel suite. No local test run was performed by this update. Keep `master` unchanged.

## Workflow review fixture follow-up — 2026-10-19

- Fixed the second approval setup in `WorkflowServiceTest`: it now sets `status=in_progress`, `sub_status=manager_review`, and `review_status=pending` instead of legacy `submitted`.
- **Validation pending:** rerun `WorkflowServiceTest`, then the broader affected tests and full suite.
- PowerShell test scripts must pause on failure without using `exit`; keep the console available for inspection.
- Keep `master` unchanged.

## Regression suite validation — 2026-10-19

- Project owner reports that the focused notification/task tests and the full Laravel test suite all passed after the task lifecycle and workflow review fixture updates.
- This is user-reported local validation; no test command was executed in the assistant environment.
- **Branch:** `development`. Keep `master` unchanged until Version 2 is complete and explicitly approved.
- **Next step:** continue V2 implementation from the correspondence foundation/numbering checkpoint, inspect the roadmap and current code before choosing the next slice, and preserve focused tests plus full regression validation.


## 2026-10-21 — Correspondence visibility and detail page

- Made the correspondence navigation entry available to users who have either view or create permission; users with view permission go to the list, while create-only users go directly to the creation form. This avoids hiding the module from users whose role can create but cannot browse.
- Added the correspondence detail route and RTL detail page with status, registration data, sender/recipient, department, linked reply chain, formatted body, and attachment download links.
- Added a detail-page HTTP regression test. Local validation pending: run the focused correspondence HTTP tests and frontend production build. Keep `master` unchanged.


## 2026-10-21 — V2 correspondence visibility baseline

- Added a correspondence classification column with `ordinary` and `confidential` values. Existing rows default to ordinary for backward compatibility.
- Added `correspondence_user_access` to record users explicitly related to a letter; the model exposes this through `authorizedUsers`.
- Added `CorrespondenceAccessService` to centralize visibility rules:
  - Registered ordinary letters are visible to active users with `correspondence.view`.
  - Drafts are visible to their creator, explicit participants, and users with `correspondence.register` (secretariat).
  - Confidential registered letters are visible only to their creator, registrar, explicit participants, and users with `correspondence.register`.
- Applied the visibility filter to the correspondence index, detail route, reply choices, and linked reply metadata.
- Linked document downloads now check the access policy of the associated correspondence first, preventing generic document access from bypassing confidentiality.
- Added the classification selector to the create form and classification labels to the list.
- Added HTTP tests for ordinary visibility, unrelated confidential denial, and explicitly authorized participant access.
- No test suite or frontend build has been run in this environment. Pull `development`, run migrations, then run the correspondence feature tests and frontend build before proceeding to referrals/workflow integration.

## 2026-10-21 — Correspondence department semantics and Jalali dates

- Replaced the ambiguous correspondence `department_id` with explicit `recipient_department_id`, and added nullable `created_department_id` through a reversible migration. The migration renames the existing column rather than dropping it.
- Draft creation derives the creator department from the user's primary department membership; if the user has exactly one department membership, that department is used as a fallback; if membership is absent and the user manages exactly one department, that managed department is used. The creator department is not user-editable.
- Internal correspondence now asks for a recipient department. The UI shows the sender automatically as the current creator for internal letters, asks for an external sender only for incoming letters, and asks for an external recipient only for outgoing letters.
- Correspondence list/detail now distinguish creator department from recipient department. The recipient department is not treated as the current assignee; referral-to-person and manager-led routing remain a subsequent workflow slice.
- Replaced Gregorian browser date controls with the already-installed Jalali `DatePicker` component for received/sent dates (`system=jalali`, Persian locale). The picker submits Gregorian ISO values for backend validation/storage while users select dates in the Persian calendar. Dates continue to be displayed using the existing Persian locale formatting.
- Updated correspondence HTTP tests to supply recipient departments for internal drafts and assert that the internal sender is the authenticated creator; added service coverage for storing creator and recipient departments independently.
- **Branch:** `development`. **Validation not run here:** pull the branch, run the focused correspondence HTTP/service tests and the frontend production build. Verify the migration against the local SQL Server database and manually check Jalali date conversion. Keep `master` unchanged.

## 2026-10-21 — SQL Server correspondence department FK fix

- The project owner reported that migration `2026_10_21_000003_split_correspondence_departments` failed on SQL Server because `ON DELETE SET NULL` on `created_department_id` can introduce multiple cascade paths.
- Changed the new creator-department foreign key to `NO ACTION` on delete. This avoids SQL Server's cascade-path restriction and preserves referential integrity; a department referenced by correspondence cannot be deleted until the reference is handled.
- **Branch:** `development`; `master` unchanged. The migration has not yet been rerun after this fix. Pull `development` and retry migration, then run the correspondence tests and frontend build.

## 2026-10-24 — Correspondence numbering after department split

- The project owner reported correspondence registration and numbering tests failing because `CorrespondenceNumberingService` still called the removed `department()` relation.
- Updated number formatting to read the department code from `createdDepartment`, treating the originating/creator department as the issuer component of a number; the recipient department is not used as the issuing unit.
- Updated registration's eager-loaded relations to `createdDepartment` and `recipientDepartment`.
- Added regression coverage proving a department-based number uses the creator department code and not the recipient department code.
- **Branch:** `development`; `master` unchanged. Tests and frontend build have not been run by the assistant; rerun correspondence tests and build locally.

## 2026-10-24 — Correspondence creator-unit selection and editor controls

- Updated correspondence creation so users with multiple active department memberships or managed departments can select the originating/creator unit per letter. The backend restricts the selected unit to the authenticated user's active memberships or managed units; the recipient unit remains a separate field.
- Added focused HTTP coverage for selecting a non-primary creator unit and verifying creator and recipient units persist independently.
- Expanded the existing RTL in-browser editor toolbar with paragraph/heading styles, text alignment, remove formatting, undo/redo, and accessible labels. It remains a lightweight contenteditable editor; a dedicated rich-text editor package has not been introduced.
- External outgoing correspondence still uses the existing free-text recipient field. No external organization/unit directory model was identified in the correspondence code inspected for this change; replacing the field with structured organization and unit selection requires confirming whether to introduce a new external-party directory or reuse an existing registry elsewhere in the application.
- **Branch:** `development`; `master` unchanged. Local Laravel tests and frontend build must be run after pulling the latest commits.

## 2026-10-24 — Word-style correspondence editor toolbar

- Reworked the correspondence editor toolbar into grouped, icon-first controls with accessible titles/labels rather than text-only buttons. Added font family/size, text color, bold/italic/underline/strike-through, superscript/subscript, alignment, lists, indentation, heading/paragraph, horizontal rule, and undo/redo controls.
- The editor still uses the browser's built-in contenteditable commands. This is a visual and functional improvement, but it is not yet a full-featured document editor engine such as Tiptap; adopting one also requires aligning the backend HTML allow-list so supported formatting survives sanitization.
- The outgoing recipient is still a free-text field because the application currently has no identified external-organization/unit directory. Reusing internal departments for external recipients would mix separate domains. A dedicated external-party directory (organizations plus units, selection UI, and a way to maintain those records) needs explicit approval before adding schema and management screens.
- User reports the correspondence tests and frontend build passed before this latest toolbar change. The latest toolbar change still needs local validation.
- **Branch:** `development`; `master` unchanged.


## 2026-10-24 — Correspondence form layout, text direction, and organization font

- Reorganized the correspondence metadata grid into four desktop columns so type, access classification, creator unit, and outgoing send date can sit in one row; the layout collapses responsively on smaller screens.
- Added explicit right-to-left and left-to-right writing-direction controls to the editor. Direction is applied to the selected text block and retained through correspondence HTML sanitization using a restricted `dir=rtl|ltr` allow-list.
- Added an organization-level default correspondence font in Organization Settings. The setting is stored through the existing system-settings service (no schema migration required), validated against supported font names, and applied to the editor and correspondence detail display. Default remains Vazirmatn for backward compatibility; the selected font must be installed on user devices or separately supplied as a web font.
- **Branch:** `development`; `master` unchanged. Local frontend build, focused correspondence tests, and manual RTL/LTR/font verification are pending; no test result is claimed.


## 2026-10-24 — Correspondence create form layout follow-up

- Reordered the form so the reply-to correspondence field occupies the row beside the subject, while the external-party panel is moved to its own full-width row below them. This avoids placing the external-party panel beside the subject in the four-column metadata grid.
- Replaced the classification and creator-unit helper paragraphs with compact question-mark popovers; the external-party explanation is also now available from a question-mark popover. The guidance appears on click and no longer increases the default field-row height.
- **Branch:** `development`; `master` unchanged. Frontend build and manual responsive/layout checks are pending; no test result is claimed.


## 2026-10-24 — Incoming correspondence originals and Windows scanner helper

- Incoming correspondence no longer uses the internal text editor or the editor/file composition selector. The server forces incoming letters into file-based mode and requires at least one original-letter PDF/image; supplying typed body text alone is not accepted.
- Added a dedicated `original_letters` upload collection (PDF/JPG/PNG, up to 10 files and 20 MB each) and retained `attachments` as a separate, optional collection for accompanying documents. Both are stored through the existing `DocumentService`, but use distinct document types and link purposes (`correspondence_original` and `correspondence_attachment`).
- The correspondence detail page displays original incoming letters separately from accompanying attachments. Existing non-original links continue to appear under attachments for backward compatibility.
- Added a short-lived, random, user-bound scanner upload token workflow. The browser launches the `officeautomation-scanner://` protocol, polls the authenticated status endpoint, and attaches the staged scan token to the draft. The unauthenticated upload endpoint is token-protected, rate-limited, accepts PDF only, and excludes CSRF only for that exact upload route. Staged files are deleted after a successful draft save; stale staged PDFs older than 30 minutes are cleaned when a new scan starts.
- Added source for a Windows x64 .NET 8 helper under `tools/WindowsScanner`. It uses Windows Image Acquisition (WIA), attempts feeder-based multi-page scanning, assembles a PDF, uploads only to the configured application origin, and refuses cross-origin redirects. Per-user protocol registration and build/install instructions are included.
- Added regression coverage for incoming original-file requirements, separate original-file rendering, and scanner token ownership/status.
- **Validation status:** no PHP test suite, frontend build, .NET publish, or physical scanner test was run from this editing session. These changes require `php artisan test --filter=CorrespondenceHttpTest`, `npm run build`, and a Windows `dotnet publish` followed by testing against the actual secretariat scanner model. Confirm the deployed app origin in the helper's `appsettings.json` before installation.
- **Branch:** `development` only. `master` remains unchanged.


## 2026-10-24 — Fix incoming correspondence validation order

- Updated the store action to check whether the signed-in user may create the requested correspondence type before validating fields specific to that type. This preserves the expected 403 response for users who are not allowed to create incoming correspondence, rather than redirecting them for a missing recipient department.
- Updated incoming-correspondence feature tests to supply the required recipient department when their purpose is to test the missing-original-file validation or successful incoming-letter creation.
- **Validation status:** changes committed; rerun `php artisan test --filter=CorrespondenceHttpTest` and report the actual result. No passing result is claimed yet.


## 2026-10-24 — Incoming scan multi-page review and confirmation flow

- Changed the Windows scanner bridge to open the NAPS2 desktop interface instead of a one-shot CLI scan. Secretariat staff can repeatedly scan pages on a flatbed, review/reorder/correct pages in NAPS2, save one PDF, close NAPS2, and select that PDF for upload.
- Added an authenticated, owner-bound inline PDF preview endpoint for staged scanner uploads. The browser now waits up to 20 minutes for the desktop scan workflow, opens a PDF preview, and does not set the form's scanner upload token until the user explicitly confirms the scan. Rejected scans can be retried.
- Extended the staged scanner-token lifetime to 30 minutes to cover longer manual multi-page scans.
- Added inline preview behavior for PDF/JPEG/PNG documents through the existing authorized document route, and added “مشاهده” actions beside incoming original letters and attachments on the correspondence detail page. Other file types remain downloadable.
- Updated Windows scanner setup documentation and sample configuration to use `Naps2GuiPath` pointing to `NAPS2.exe`.
- **Validation pending:** these changes have not been built or tested in this environment. Check the C# publish and GUI close/selection flow on the secretariat workstation, verify multi-page flatbed scanning and the browser preview/confirm/reject path, then run the correspondence feature tests and frontend production build.
- **Branch:** `development` only; `master` unchanged.

- Follow-up: scanner staging cache expiry is 30 minutes for both pending and ready states; inline document previews stream from the configured storage disk.


## 2026-10-27 — Cartable TypeScript build fix

- Fixed the casing mismatch in `resources/js/Pages/Cartable/Index.vue`: the template now reads `letter.current_department.name`, matching the declared `CorrespondenceItem` property.
- Commit is on `development` only; `master` is unchanged. Validation pending: run `git pull origin development` and `npm run build`; report the actual result before proceeding.


## 2026-10-27 — Correspondence numbering and date display backlog

- Pending UI correction in `resources/js/Pages/Correspondence/Index.vue`: display `registered_at` as a localized Persian/Jalali date and Tehran local time, label it `ثبت نهایی`, and do not show the raw ISO UTC timestamp.
- Numbering requirements to design and implement after inspecting the existing correspondence settings and registration flow:
  - `شماره نامه` and `شماره ثبت` are distinct fields with distinct purposes; registering a letter must not conflate or overwrite the letter's own number.
  - In correspondence settings, a custom letter-number template must support either two or three fields (not force exactly three).
  - Add a required, user-configurable code field for each organizational department/unit, entered by an authorized user when configuring that unit (e.g. IT department code `IT`). Use that code in the letter-number template instead of exposing the numeric database department ID.
- Keep the existing workflow and historical data compatible; inspect schema, settings UI, number-generation logic, and tests before choosing migration/compatibility behavior. No implementation of these backlog items is claimed yet.

## 2026-10-27 — Correspondence number separation and department codes (validation pending)

- Added a dedicated letter_number field. New drafts receive a letter number immediately using the organization-configured template; the Secretariat assigns a separate registration_number at final registration (REG + configured separator + sequence). Existing registration numbers are preserved; legacy drafts without a letter number receive one at final registration.
- Added a separate counter table for letter numbers while retaining the existing counter for Secretariat registration numbers.
- Custom letter-number templates now accept two or three distinct components. The settings form exposes a two/three-part selector and persists only the selected components.
- Department creation/editing now requires a unique short alphanumeric code (hyphens/underscores allowed), persists it, and uses it in letter-number templates instead of the database ID. Existing department codes are preserved.
- Correspondence list/detail surfaces display letter number and registration number separately. The list formats registered_at as a Jalali date and Tehran local time and labels it «ثبت نهایی»; search includes both numbers.
- Added/adjusted regression coverage for two-part templates, distinct registration numbers, draft letter-number assignment, and department code persistence.
- Validation pending: pull development, run focused correspondence numbering/service and department tests, then full php artisan test and npm run build. No tests or build have been run by the assistant. master unchanged.

2026-10-27 — Correspondence copy UX and workflow additions (validation pending)

- Moved optional copy-recipient selection to the end of the correspondence creation form and replaced the native multi-select with a checkbox list that clearly supports selecting multiple departments.
- Correspondence creation date fields now use the shared JalaliDatePicker component so the input follows the application font styling; the copy section no longer occupies a row near the top of the form.
- Added an in-workflow copy endpoint. Only the manager of the correspondence's current responsible department may add one or more additional copy recipients, and only before the workflow is completed. Each newly attached copy continues through the existing workflow service and audit/action history; duplicates are ignored by the existing service.
- Added HTTP regression tests for manager success with multiple copy recipients and denial for a non-manager.
- Validation pending on the local Windows/SQL Server environment: pull development, run the focused CorrespondenceHttpTest, then run npm run build. No tests or build have been run by the assistant. No migration is required for this change. master unchanged.

2026-10-27 — Related correspondence search and required dates (validation pending)

- Renamed the optional relation field to «در ارتباط با مکاتبات قبلی» to cover both continuation and reply relationships.
- Replaced the static top-100 dropdown with a debounced server-side search endpoint. Results are limited to 20 per query and include subject, letter number, registration number, letter date, internal department names/codes, external organization/department names/codes, and external tracking registration number. Results remain constrained by the existing correspondence visibility service.
- Outgoing correspondence now requires sent_at; incoming correspondence requires received_at, with required markers in the form. Backend validation enforces the requirement.
- Added HTTP regression coverage for required dates and search result metadata. Validation pending locally: php artisan test --filter=CorrespondenceHttpTest, then npm run build. No migration required; development only, master unchanged.

2026-10-27 — Related correspondence selector UI alignment (validation pending)

- Expanded both «ارتباط با مکاتبات قبلی» and «رونوشت به» to `sm:col-span-2` so the two controls have matching widths in the two-column form grid.
- Removed the separate empty-state panel that said «برای جستجو حداقل دو نویسه وارد کنید.»; the minimum query length is now communicated in the search input placeholder. Search results appear in a bordered, scrollable list only after at least two characters are entered.
- Selected related letters now use a single removable list styled like the selected copy-recipient list, rather than nested bordered boxes. Backend behavior is unchanged.
- Validation pending locally: `git pull origin development`, `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. No tests/build have been run by the assistant; `master` unchanged.

2026-10-27 — Related correspondence dropdown overlay correction (validation pending)

- Restored the outer rounded border and padding around «ارتباط با مکاتبات قبلی» to match «رونوشت به».
- Search results now render as an absolutely positioned, white, shadowed dropdown anchored to the search input. They overlay the form instead of pushing the selected-letter list or lower fields down. The selected letters remain in their own list beneath the input.
- Validation pending locally: `git pull origin development`, `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. Assistant has not run tests/build; `master` unchanged.


## 2026-10-27 — شماره نامه وارده و قالب مستقل شماره ثبت (در انتظار اعتبارسنجی)

- شماره نامه وارده باید عیناً از روی اصل نامه وارد شود و خودکار تولید نمی‌شود؛ شماره ثبت دبیرخانه جداست و هنگام ثبت نهایی با قالب مستقل قابل تنظیم تولید می‌شود.
- تنظیمات شماره ثبت شامل قالب ساده/سال/کد واحد/سفارشی، دو یا سه جزء، جداکننده و طول بخش ترتیبی است. شماره‌های تاریخی تغییر نمی‌کنند.
- دبیرخانه فقط تا پیش از اقدام گردش می‌تواند پیش‌نویس را ویرایش یا حذف کند؛ بررسی مجوز در سرور و تراکنش انجام می‌شود. اعتبارسنجی محلی هنوز انجام نشده است.


### اصلاح بر اساس گزارش تست 2026-10-27

- خطای نحوی آرایه اعتبارسنجی در `CorrespondenceController::update` شناسایی و اصلاح شد؛ این خطا علت بیشتر خطاهای 500 در گزارش قبلی بود.
- قالب پیش‌فرض شماره ثبت به «شماره ترتیبی» تغییر کرد تا با قالب پیش‌فرض شماره نامه تداخل نداشته باشد. تست انتظار شماره ثبت نیز به‌روزرسانی شد.
- تست‌های محلی هنوز پس از این اصلاحات دوباره اجرا نشده‌اند؛ نتیجه قبلی دیگر معیار وضعیت فعلی نیست.

### پیگیری تست‌های 2026-10-27

- اجرای مجدد سه فایل تست: 41 تست موفق و یک تست ناموفق بود. علت تنها شکست، قدیمی‌بودن داده ورودی تست «الزام فایل اصل نامه» بود؛ تست شماره نامه وارده را ارسال نمی‌کرد و پیش از رسیدن به اعتبارسنجی `original_letters` با خطای `letter_number` متوقف می‌شد.
- داده تست با افزودن `letter_number` اصلاح شد تا تست صرفاً الزام فایل اصل نامه را بررسی کند. اجرای دوباره تست پس از این commit هنوز در محیط محلی تأیید نشده است.

## 2026-10-28 — Draft correspondence privacy and edit/delete audit

- Audit confirmed that edit/delete endpoints exist, but are authorized only for Secretariat users while a correspondence is still a draft and has no workflow action. Final-registered correspondence cannot currently be edited or deleted; direct endpoint requests are denied by the same server-side guard.
- Tightened draft visibility: drafts are now visible only to their creator and the Secretariat, regardless of department membership, current workflow relationships, or the all-scope filter. Direct detail access follows the same rule. Registered ordinary and registered confidential correspondence retain their existing visibility rules.
- Added HTTP regression coverage for creator-only draft visibility. UI edit/delete controls remain conditional on the backend-provided can_manage_before_action flag; if absent for a Secretariat user, verify that the user's configured Secretariat department is active and correctly assigned.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. No test/build result is claimed. master unchanged.

## 2026-10-28 — Correspondence edit/delete business rule clarified

- Final registration alone does not block editing/deletion. It only communicates the letter to the target department.
- Incoming correspondence can be edited/deleted by Secretariat; outgoing and internal correspondence can be edited/deleted by their creator, provided no workflow/action record, reply, or linked task/request exists.
- Edit/delete authorization remains enforced server-side and the UI uses the same capability flag. Draft visibility remains restricted to creator and Secretariat.
- Added/updated HTTP coverage for editing and deleting registered incoming correspondence by Secretariat, registered outgoing correspondence by creator, denial to another creator, and denial after a workflow action. Validation pending in local environment.
- master unchanged.

## 2026-10-28 — Fix correspondence edit/delete capability after registration bookkeeping

- Root cause found: `canManageBeforeAction()` treated any `correspondence_actions` row as a workflow action. The registration lifecycle itself writes `registered` and, for incoming mail, `secretariat_initial_referral`; adding a copy recipient writes `copy_added`. Therefore these non-user-workflow records could suppress edit/delete controls immediately, even though the business rule allows editing after registration until a real workflow action.
- Updated the guard to ignore only `registered`, `secretariat_initial_referral`, and `copy_added` when deciding whether a workflow action has occurred. Actual workflow actions, replies, and linked tasks still block edit/delete. Both UI capability flags and update/delete endpoints use this shared guard.
- Extended `CorrespondenceHttpTest` so the registered incoming Secretariat edit/delete test includes all three bookkeeping action types and still verifies that a real `manager_approve` action blocks deletion.
- Commits on `development`: `ca19d62e664af9c504c02ae4892f8d74794e0ea7` (service fix), `eb014380ff67df01792c466c65a4066bc91087b1` (regression test).
- Validation is pending in the local Windows/SQL Server environment. No tests or frontend build have been run by the assistant. `master` unchanged.

## 2026-10-28 — Fix SQL Server delete failure for correspondence bookkeeping actions

- After the editability guard was corrected to ignore registration bookkeeping actions, the regression test exposed a second issue: deleting an eligible correspondence attempted to delete its parent row while `correspondence_actions` still referenced it, causing SQL Server FK error `correspondence_actions_correspondence_id_foreign`.
- Updated `CorrespondenceController::destroy()` to delete the correspondence's action-history rows inside the same transaction before deleting the correspondence. This endpoint is already guarded by `canManageBeforeAction()`, so real workflow actions, replies, and linked tasks continue to block deletion; only letters eligible for pre-action deletion reach this cleanup.
- Commit on `development`: `1cc1d7da270da6c5d7c8820ce1b3fb6a9ed6691b`.
- Validation is pending; the targeted feature test has not yet been rerun after this fix. `master` unchanged.

## 2026-10-28 — Unified correspondence create/edit form (validation pending)

- The incoming letter-number label is now «شماره نامه وارده» with a question-mark tooltip explaining: «شماره نامه درج‌شده روی اصل نامه وارده را وارد کنید.» The field shows one required marker.
- Edit now renders the same Vue form used for creation, with existing correspondence values, linked letters, copy departments and original-letter filenames preloaded. Correspondence type remains fixed during editing; existing original files are preserved and additional files can be attached.
- Expanded the update endpoint to validate and persist editable metadata/body, linked correspondence, copy departments, and newly uploaded original/attachment files. Existing files are retained. Authorization still uses the shared pre-action guard and is rechecked inside the transaction.
- Local validation pending: php artisan test --filter=CorrespondenceHttpTest, then npm run build. No test/build result is claimed. master unchanged.

## 2026-10-28 — Required-field asterisk convention

- Fixed the incoming letter-number label by removing its manually rendered `<span>*</span>`; `resources/css/app.css` already adds the red asterisk with `label:has(+ :is(input, select, textarea):required)::after` when the label is followed by a native required control. Keeping both creates two stars.
- Permanent UI rule: for controls covered by the global `::after` selectors, mark the control `required` and do not add a literal/manual asterisk to the label. The global CSS is the single source of the visual marker. For custom controls not covered by the selectors, either add exactly one explicit marker or extend the CSS selector—never both. Required validation must still be enforced independently.
- The rule is documented beside the CSS selectors and in project context documents to prevent recurrence. Validation pending: after `git pull origin development`, run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`; no test/build result is claimed.


## 2026-10-28 — Favicon aligned with application logo

- Replaced the unrelated teal document/checkmark favicon with the same purple gradient mark used by `resources/js/Components/ApplicationLogo.vue`, keeping the browser tab icon consistent with the in-app logo.
- Validation pending: pull `development`, run `npm run build`, then hard-refresh the browser or clear its favicon cache if the old icon remains.


## 2026-10-28 — Incoming correspondence edit test fixture

- Fixed the registered incoming-correspondence edit/delete regression test fixture: it now includes a valid linked `correspondence_original` document, matching the business rule that an incoming letter must always retain its original scan/PDF. The production validation remains unchanged; the earlier failure was caused by an invalid fixture without an original attachment.
- Validation pending: run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build` after pulling `development`.


## 2026-10-28 — Restore incoming letter number required marker

- The required asterisk disappeared because the incoming letter number label sits inside a flex wrapper with a help tooltip, so the generic adjacent-label CSS selector did not match it.
- Added the reusable `required-field-label` class selector and applied it to the incoming letter number label. The input remains natively `required`; this restores the red visual marker without adding a duplicate manual asterisk.
- Validation pending: run `npm run build` and confirm the red asterisk appears once beside «شماره نامه وارده».

## 2026-10-28 — System administrator correspondence deletion

- System administrators can now delete any correspondence from the list, including registered letters and letters that already have workflow actions. This is separate from ordinary edit/delete eligibility; normal users retain the existing restrictions.
- The delete endpoint rechecks authorization inside the transaction, records who deleted the letter in Core Audit metadata, removes related action/link rows, and clears replies' pointer to the deleted correspondence so they remain intact.
- Added a feature test for a system administrator deleting a correspondence after a workflow action. Validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. No tests/build have been run by the assistant; `master` remains unchanged.

## 2026-10-28 — Incoming correspondence description

- Added a dedicated “توضیحات نامه” text area for incoming correspondence. It stores a concise description/summary independently of the required scanned original letter.
- Existing `body` storage is reused for this field; incoming descriptions are preserved on edit and displayed as plain text on the correspondence details page. Other correspondence types retain the rich-text body behavior.
- Added a feature test proving that the description is saved alongside the original-letter upload. Validation pending: `php artisan test --filter=CorrespondenceHttpTest` and `npm run build` after pulling `development`.

## 2026-10-28 — Correspondence form grid alignment

- Adjusted the create/edit form grid in `resources/js/Pages/Correspondence/Create.vue`: for outgoing and internal correspondence, “واحد ایجادکننده” spans two columns; outgoing “موضوع” spans three columns so it shares its row with the send date; internal “موضوع” spans two columns so it can share its row with the related-correspondence section. No backend behavior changed.
- Validation pending: pull `development` and run `npm run build`. `master` remains unchanged.

## 2026-10-28 — Internal correspondence subject placement correction

- Moved the internal-correspondence “موضوع” field into the first details grid beside “واحد دریافت‌کننده”, with both fields spanning two columns. The internal subject is no longer rendered in the following grid; outgoing and incoming subject placement is unchanged.
- Validation pending: pull `development` and run `npm run build`. `master` remains unchanged.

## 2026-10-28 — Correspondence details page layout

- Reorganized `resources/js/Pages/Correspondence/Show.vue` into a two-column desktop layout: correspondence metadata and letter attachments in the left column; letter body/description and workflow actions in the wider right column.
- Consolidated duplicated metadata into one “اطلاعات مکاتبه” section, including type, status, letter/registration numbers, creation/registration dates, departments, creator/registrar, sender/recipient, and relevant external organization fields.
- Added a separate two-column section below for “لیست ارجاعات” and “لیست رونوشت‌ها”. The referral list is derived from workflow actions with a destination or referral/request action type. Copy management remains available in the copies column when permitted.
- The outgoing external-tracking section and related-replies section remain available. Validation pending: pull `development`, run `npm run build`, and inspect incoming, outgoing, and internal correspondence details. No tests/build have been run by the assistant; `master` remains unchanged.

## 2026-10-28 — Correspondence detail refinements and outgoing file mode

- Removed the type/letter-number/registration-number subtitle below the correspondence subject; those details now appear in the consolidated metadata section only.
- Widened the correspondence detail page and explicitly set the desktop grid direction so metadata and attachments occupy the left column while the letter content/actions occupy the wider right column. Each column retains RTL text direction.
- Renamed incoming original-letter files to “فایل نامه” and placed that section directly below the letter description. Separate accompanying attachments remain in their own section.
- Simplified composition choices in the create/edit UI to “ویرایشگر داخلی” or “پیوست فایل”. For outgoing file mode, a required “توضیحات فایل نامه” is collected and stored as plain text in the existing body field; the uploaded letter file is labeled “فایل نامه” on the detail page. Editing detects plain-text file descriptions and preserves file mode.
- Made “سازمان مخاطب” required for outgoing correspondence in both client-side and server-side validation, including updates.
- Validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`, then inspect incoming/outgoing/internal details and create/edit outgoing correspondence in both modes. No tests/build have been run by the assistant; `master` remains unchanged.

## 2026-10-28 — Outgoing organization requirement test fixtures

- Updated the correspondence HTTP test fixtures to supply a valid active external organization wherever an outgoing correspondence is expected to pass validation. This preserves the production rule that «سازمان مخاطب» is required, while allowing the file-mode missing-attachment test and registered-outgoing edit test to reach the specific behavior they assert.
- Validation pending in the local environment: run `php artisan test --filter=CorrespondenceHttpTest`. No test result is claimed; `master` remains unchanged.

## 2026-10-28 — Follow-up correspondence test payloads

- Updated the file-mode attachment validation test to include the required plain-text file description, so the request reaches the missing-attachment assertion instead of failing earlier on the description rule.
- Updated the registered outgoing correspondence edit fixture/request to use editor mode with a body, so the test exercises edit permissions and metadata updates rather than triggering the file-mode description validation.
- Validation pending: run `php artisan test --filter=CorrespondenceHttpTest` locally. No test result is claimed; `master` remains unchanged.


## 2026-10-28 — Correspondence details layout and incoming registrar permissions

- Reduced the correspondence metadata column width by changing the desktop grid ratio from 1:1.65 to 0.7:1.65. Metadata is now a single vertical list with one label/value pair per row.
- For incoming correspondence, the creator department is set to the configured active Secretariat department rather than inferred from the registering user's other department memberships.
- Historical rule superseded on 2026-10-31: the blanket view-only restriction for a Secretariat registrar who also manages the current department was removed. Workflow permissions are now evaluated by the current department/action context so overlapping roles can be used without switching roles.
- Validation pending in the local environment: run `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`, then verify a Secretariat registrar who also manages the recipient department can view but cannot act or add copies. No test/build result is claimed; `master` remains unchanged.


## 2026-10-31 — Correspondence workflow actions and role fusion (validation pending)

- Manager referral now distinguishes the target type: another active department or an active employee who belongs to the current department. User-level referrals preserve the department and assign the correspondence to the selected user.
- Removed the blanket rule that blocked a Secretariat member from acting on an incoming letter they registered. Authorization is evaluated in the current department/workflow context, so multiple roles can coexist instead of forcing a manual role switch.
- Added a dedicated outgoing-letter return path: Secretariat can return a registered outgoing letter while it is awaiting dispatch, with a required correction note; the letter returns to its originating department/creator. The creator or origin-department manager can resubmit it to the configured Secretariat, restoring the awaiting-dispatch state. Final-dispatched letters cannot use this return action.
- Removed the task/project/new-letter request options from the workflow action selector because the existing handlers only changed routing/state and did not create the corresponding business object. These options must return only when their real task/project/letter creation flows are integrated.
- Added focused regression tests for employee referral and the Secretariat return/resubmission lifecycle. Validation remains pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build and manually inspect all three correspondence types. master unchanged.
