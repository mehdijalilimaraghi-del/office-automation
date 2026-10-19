# Office Automation — Current Status

> This is the concise handoff/checkpoint file for continuing development in a new conversation.

## Last Updated
2026-10-19

## 2026-10-19 — Fix user department membership modal

- Fixed the Vue template nesting in `resources/js/Pages/Core/Users/Index.vue`: the membership dialog was rendered inside the user create/edit dialog, so clicking «واحدها» alone could not display it and a subsequent click on «ویرایش» could reveal the stale membership dialog.
- The membership dialog is now a sibling of the user form dialog. Opening «واحدها» displays membership management independently; opening «ویرایش» displays the user edit form without the membership dialog.
- No routes, authorization rules, or membership persistence logic were changed.
- **Branch:** `development`. **Validation pending:** run frontend production build and manually verify both actions on the user list.

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
- **Next slice:** expose authorized correspondence list/draft creation/final-registration HTTP flows; implement the selected internal editor and Word upload route; then connect attachments, audit, notifications and Cartable actions.


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

