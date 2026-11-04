# Office Automation Enhancements — Approved Scope

Last updated: 2026-11-04
Branch: development

## Approved product decisions

### 1. Manager self-assignment / accepting responsibility

A department manager must be able to personally take responsibility for an actionable correspondence item and a task. This is a distinct business action (accept responsibility), not a referral to the manager themselves. It must not create a self-referral. The operation must update the current responsible user/assignee, preserve assignment/workflow history, and create an explicit audit event. Existing authorization, department-manager scope, workflow-state, finalized-task and pending-review protections remain enforced.

### 2. External correspondence recipient

When an external organization or department is selected, its configured default contact is selected automatically. The user may change the recipient for an individual letter. A recipient may also be an organizational addressee without a named person.

Recipient addressing data is modeled as separate parts:
- Organizational title prefix: e.g. «مدیریت»، «مدیرعامل»، «معاونت»، «ریاست»
- Organizational position: e.g. «مدیر واحد مالی»
- Personal salutation: e.g. «جناب آقای»، «سرکار خانم»
- Professional title: e.g. «مهندس»، «دکتر»
- Contact full name

The organizational title prefix is an additional independent field, not a replacement for position. At most one active default contact should exist per external department (and an organization-level default where no department is selected). The selected addressing text must be snapshotted on correspondence finalization so later contact edits do not rewrite historical letters.

### 3. Letterhead, PDF, pagination and physical printing

One shared correspondence document template and coordinate system must support both PDF and physical printing. For each output/print operation, the operator can select:
- With letterhead
- Without letterhead

The choice applies both to generated PDF and to physical print output. Letterhead elements are configurable independently, including image/background, logo, organization name, address, telephone, email, website and footer. Contact information such as address and phone is part of the configurable letterhead composition, but each element can be enabled/disabled independently. Page size, margins, content start position and registration number/date/attachment coordinates are configurable. The no-letterhead output suppresses only letterhead layers; body text and coordinates remain unchanged. Long letters must flow naturally across as many A4 pages as required. Pagination must preserve paragraph/line order, avoid clipping and overlapping, keep appropriate margins and letterhead/content areas, and support page numbering (e.g. page X of Y) in a configurable footer/header position. Avoid splitting short logical blocks (such as a heading with its following paragraph or signature block) across pages where practical. Preview is required before output.

### 5. Creator identity, organizational stamp and department-manager signature

The finalized outgoing letter must include a creator/issuer identity block at the bottom of the letter body, for example:
- Organizational unit/title (e.g. «مدیریت واحد مالی»)
- Creator's full name (e.g. «مهدی جلیلی»)

The identity values must be snapshotted when the letter is finalized, so later changes to a user's name, department membership or position do not rewrite historical letters. The block's placement and alignment are part of the shared document template and must remain consistent in PDF and physical print output.

Two separate image assets are required:
- **Organization stamp:** one organization-level image, maintained in the existing Core organization settings area (proposed entry point: «تنظیمات سازمان»). It must be independently replaceable and activatable/deactivatable.
- **Department manager signature:** a separate signature image for each department, maintained with that department's configuration in Core department management (proposed entry point: department create/edit). It must be independently replaceable and activatable/deactivatable. The signature used by a finalized letter must be the signature configured for the relevant issuing/creator department and must be snapshotted or versioned with the finalized document so later replacement does not alter old letters.

The document template must provide independent placement/visibility controls for the creator identity block, organization stamp and department-manager signature. The stamp and signature are not the same asset and must never be stored as one combined image. Their inclusion must follow the configured letter output/authorization rules; do not silently place them on every document. Preview must show their actual positions before PDF generation/printing. Validate uploaded image type, size, dimensions and access; store with generated safe names and controlled private/public delivery according to the existing file-storage policy.

### Proposed configuration ownership (to verify against the actual Core UI before implementation)
- Organization stamp and organization-wide letterhead assets/details: Core → Organization Settings.
- Department manager signature and department-specific issuer metadata: Core → Departments → Create/Edit Department.
- Letter layout, creator block position, stamp/signature coordinates and output visibility: Correspondence document-template/print settings, using the single shared PDF/print coordinate system.

## Engineering constraints

- Implement only on `development`; do not change `master` or deploy to the server.
- Inspect current module contracts and authorization before implementation.
- Keep assignment separate from review/approval.
- Do not model manager acceptance as assigning a task/letter to oneself through a referral action.
- Add regression coverage for authorization, history, no-self-referral and output variants.
- Update this document and the authoritative status/context documents after each completed stage.
- Local PHP tests and frontend build are run by the project owner; never report them as passed until the owner supplies results.

### 4. Reusable outgoing-letter text templates

In Correspondence > outgoing letter composition, specifically the «روش تهیه نامه» / letter editor path, users can save an authored letter body as a reusable template and later select it to prefill the editor. A template is a starting point, not a locked document: after insertion, its text remains fully editable for the current letter. Templates should have a name/title, body content, active state and appropriate ownership/availability scope (organization-wide versus creator-only) governed by permissions. Selecting a template must not overwrite already-entered body text without explicit confirmation. The saved template is separate from final correspondence records and must not silently alter previously registered letters.

### 6. Implementation notes\n\nThe creator identity block is distinct from the external recipient addressee block and from the document's approval/audit trail. It identifies who prepared/issued the letter and the issuing unit; it does not imply that the creator is the authorized signatory. The department manager signature is a separate organizational asset and should only be rendered when the letter's workflow and configured document policy authorize it.\n\n## Five implementation phases and status

| Phase | Scope | Status |
|---|---|---|
| 1 | Manager accepts responsibility in Correspondence and Tasks, with authorization, audit/history and regression tests | Implemented; focused tests passed (owner-reported) |
| 2 | External recipient defaults, organizational title prefix and addressing snapshot | Backend/form implementation added; focused regression suites passed (owner-reported); frontend build and end-to-end acceptance remain pending |
| 3 | Shared letterhead/template, creator identity block, organization stamp, per-department manager signature, configurable fields, multipage layout and page numbering for PDF/print | Initial implementation committed; tests/build/manual print and remaining layout controls pending |
| 4 | Reusable outgoing-letter body templates in the letter editor, editable after insertion | Not started |
| 5 | End-to-end regression, build and manual acceptance of all five feature areas | Not started |


## مرحله ۱ — پذیرش مسئولیت توسط مدیر (تست‌های متمرکز پاس شد)
- مکاتبات: action مستقل `manager_accept_responsibility` اضافه شد؛ فقط مدیر واحد جاری، برای نامه ثبت‌شده در وضعیت `in_progress` و وقتی مسئول جاری خالی یا خود مدیر است، می‌تواند مسئولیت را بپذیرد. ارجاع به خود انجام نمی‌شود و اقدام در تاریخچه و audit موجود ثبت می‌شود.
- تسک‌ها: endpoint مستقل پذیرش مسئولیت اضافه شد. فقط مدیر واحد مرتبط با تسک، با مجوز `tasks.assign` و خارج از وضعیت نهایی/بررسی معلق، می‌تواند مسئول شود. نوع تخصیص `manager_accept` در تاریخچه و audit ثبت می‌شود.
- برای هر دو ماژول دکمه مستقل «پذیرش مسئولیت» افزوده شد. تست‌های HTTP برای موفقیت، عدم اختیار مدیر و وضعیت‌های قفل‌شده تسک و نیز موفقیت/عدم اختیار مدیر در مکاتبات اضافه شدند.
- مالک پروژه اجرای تست‌های متمرکز `TaskReferralHttpTest` و `CorrespondenceHttpTest` را تأیید کرد؛ وضعیت تست‌های این مرحله: **پاس‌شده**. اجرای build و پذیرش end-to-end/دستی در مرحله ۵ باقی است.

## وضعیت پنج مرحله اجرایی
| مرحله | شرح | وضعیت |
|---|---|---|
| ۱ | پذیرش مسئولیت مدیر در مکاتبات و تسک‌ها، همراه با مجوز، history/audit و regression tests | پیاده‌سازی انجام شد؛ تست‌های متمرکز پاس شد |
| ۲ | مخاطب پیش‌فرض، ساختار خطاب و snapshot تاریخی | پیاده‌سازی backend و فرم‌ها انجام شد؛ تست‌های متمرکز `ExternalPartyControllerTest` و `CorrespondenceHttpTest` پاس شد؛ build و پذیرش end-to-end باقی است |
| ۳ | قالب مشترک سربرگ، PDF/چاپ، مشخصات ایجادکننده، مهر/امضای مدیر، صفحه‌بندی و شماره صفحات | پیاده‌سازی اولیه؛ تست، build، پذیرش چاپ و تکمیل تنظیمات چیدمان باقی است |
| ۴ | قالب‌های متنی قابل استفاده مجدد در نامه صادره | شروع نشده |
| ۵ | تست end-to-end، build و پذیرش دستی همه موارد | شروع نشده |

## مرحله ۲ — مخاطب بیرونی و snapshot خطاب (پیاده‌سازی اولیه)
- مدل موجود `ExternalContact` گسترش یافت؛ فیلدهای مستقل «پیشوند عنوان سازمانی»، «سمت سازمانی»، «پیشوند خطاب شخصی» و «عنوان حرفه‌ای» اضافه شدند.
- برای مخاطب، وضعیت «پیش‌فرض» اضافه شد. هنگام ثبت مخاطب پیش‌فرض، پیش‌فرض قبلی همان سازمان و همان محدوده (واحد مشخص یا سطح سازمان بدون واحد) غیرفعال می‌شود؛ پیش‌فرض سطح سازمان و پیش‌فرض واحد می‌توانند مستقل باشند. مخاطب غیرفعال از حالت پیش‌فرض خارج می‌شود.
- فرم مدیریت افراد بیرونی برای ثبت/ویرایش اجزای خطاب و انتخاب مخاطب پیش‌فرض توسعه یافت.
- فرم نامه صادره اکنون مخاطب شخصی را به‌صورت اختیاری از افراد فعال همان سازمان/واحد انتخاب می‌کند و مخاطب پیش‌فرض همان محدوده را خودکار انتخاب می‌کند. انتخاب شخص اجباری نیست و خطاب سازمانی/واحدی بدون نام شخص همچنان مجاز است.
- رابطه `external_contact_id` به مکاتبه و snapshot خطاب به مکاتبه اضافه شد. هنگام ثبت نهایی نامه صادره، اجزای خطاب مخاطب و نام سازمان/واحد در snapshot ذخیره می‌شوند؛ تغییرات بعدی اطلاعات مخاطب نباید snapshot نامه ثبت‌شده را تغییر دهد.
- Migration جدید: `2026_11_04_120000_add_external_contact_addressing_and_defaults`.
- فرم ایجاد و ویرایش مخاطب هر دو اجزای خطاب و وضعیت پیش‌فرض را اعتبارسنجی و ذخیره می‌کنند.
- حذف مخاطبی که به مکاتبه یا تسک ثبت‌شده متصل است مسدود می‌شود؛ برای حفظ سوابق باید آن را غیرفعال کرد. تغییر مخاطب پیش‌فرض در تراکنش با قفل سازمان انجام می‌شود.
- تست‌های regression برای یکتایی پیش‌فرض در هر محدوده و حفظ snapshot بعد از تغییر مخاطب اضافه/به‌روزرسانی شدند. مالک پروژه پس از دریافت اصلاح نحوی، پاس شدن `ExternalPartyControllerTest` و `CorrespondenceHttpTest` را اعلام کرد. این دو مجموعه تست regression مرحله ۲ را نیز اجرا می‌کنند؛ build فرانت‌اند و پذیرش دستی همچنان باقی است.



## 2026-11-04 — Phase 3 repository audit checkpoint

Verified implementation facts on `development`:
- Core organization settings already persist organization name, address, phone, email and website. Phase 3 should reuse these values as independently configurable letterhead elements rather than duplicate them.
- `Department` already has a `manager_id` relationship, but no manager-signature image field, upload action or UI exists.
- Organization settings have no stamp image field or upload flow.
- Correspondence detail has no print/PDF route or dedicated print template; the current Vue show page is an operational workflow/detail screen.
- No PDF renderer is currently declared in `composer.json`.
- The Documents module uses a storage contract and disk/path/version metadata, and its download endpoint authorizes access. Existing public avatar storage must not be treated as the default access policy for official stamp/signature files.

No application code, migration, dependency, master/release branch or server change was made during this audit. The remaining architecture decision is whether PDF output means browser-native Print / Save as PDF from the same HTML/CSS A4 template used for physical printing, or a server-generated downloadable PDF using an added renderer. This choice affects preview, pagination and image delivery, so it must be settled before the shared output pipeline is implemented.


## 2026-11-04 — Phase 3 implementation slice 1

- Output architecture selected by the owner: one shared HTML/CSS A4 template, using browser-native Print / Save as PDF. No server-side PDF renderer/package was introduced.
- Unit manager signature is configured on the Department, not per user. A unit still supports multiple members and one manager; changing the manager deactivates the previous signature until replaced.
- Organization stamp is configured separately under Core → Organization Settings. Stamp and manager signature are separate private assets, independently replaceable and activatable.
- Added authenticated image delivery, upload validation (PNG/JPG/WEBP, 2 MB maximum, dimension constraints), and private storage.
- Outgoing-letter finalization snapshots creator name, issuing unit title, organization contact details, stamp and manager signature. The signature is included only for the approved-for-registration workflow state.
- Added registered-letter A4 preview/print page, per-operation letterhead toggle, independent creator/stamp/signature visibility controls and CSS page counters.
- Focused tests were added for asset uploads and finalization snapshots. Local execution is pending.
- Remaining Phase 3 work: verify the migration, tests and build; manually validate long multi-page letters and printer output; complete detailed placement/coordinate controls, logo/background support and configurable page-number placement.
