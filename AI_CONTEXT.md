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

## 2026-11-09 — Preserve full A4 coordinate plane in browser print

- Follow-up analysis found a second coordinate mismatch: `@page` margins correctly reserve the configured top/bottom and left/right print areas, but the `.paper` containing block in Chromium becomes only the remaining printable rectangle. The absolute template plane was still `width:auto; right:0; left:0`, so all percentage-based x/width coordinates were recalculated against the narrower printable rectangle. This made side margins and element positions appear ignored or shifted even after the vertical origin correction.
- Print CSS now restores the absolute template plane to physical A4 dimensions (210 × 297 mm), extending it through the configured right margin and shifting it upward by the effective top margin. `@page` continues to own the configured print margins; flowing letter content remains inside that printable area. This aligns percentage-based repeating/header/footer and positioned elements with the designer's physical A4 coordinate system.
- Browser print-dialog margin interaction still requires Chromium acceptance testing. Verify with A4 paper, scale 100%, headers/footers disabled, and browser margins set to the default CSS-controlled setting; do not apply a second custom margin on top of the template margins. Local build, PHP tests, and PDF acceptance remain pending. Development only; master/release/server unchanged.

## 2026-11-09 — Align first-page template elements between screen preview and print

- Root cause: the screen preview's A4 coordinate plane starts at the physical page top, but print `@page` margins move the `.paper` containing block to the printable content edge (e.g. 70mm below the physical top). The first-page absolute meta/heading elements therefore shifted down by the content-top margin in PDF while the body still began at the printable edge, causing overlap and apparent spacing drift.
- Print now offsets only the absolute template coordinate plane upward by the effective top content margin (`max(page_layout.top, header_height)`). The flowing body remains inside the `@page` printable content area, and its measured spacing from the positioned elements is retained. Screen preview geometry is unchanged.
- Browser print/PDF comparison and local build/PHP tests are pending owner verification. Development only; master, release and server unchanged.

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

## 2026-11-08 — Complete issuer identity variables in correspondence templates

- Expanded the issuer text-element variable set with explicit identity fields: creator full name, issuing-unit title, issuing department name, department manager full name, manager organizational position, and the clearer `{{manager_title}}` alias.
- The template designer preview supplies readable sample values for every new variable. The print resolver maps them to the actual correspondence creator, its created department, the department's configured manager, and the manager's active primary position assignment in that same department.
- Added Inertia print props for manager title and creator department name, retained `{{manager_position}}` for compatibility, and made explicit issuer identity detection recognize the added variables so the legacy automatic footer is not duplicated.
- Extended the protected print-page regression fixture to include the new variables and assert manager title and creator department values.
- Implementation and test changes are committed on development. Owner-run focused correspondence HTTP test and frontend production build remain pending; master, release branch and server are unchanged.

## 2026-11-08 — Fix multiple user creation with blank profile identifiers

- Root cause: SQL Server unique indexes treat NULL as an indexed value for uniqueness, so the original unique indexes on nullable user_profiles.employee_code and national_id allowed only one row with NULL. Creating a second user without these optional identifiers failed with SQLSTATE 23000.
- Added a forward-only migration replacing both indexes with filtered unique indexes (WHERE column IS NOT NULL) on SQL Server and SQLite. Other supported drivers retain ordinary unique indexes, which already allow multiple NULL values. Non-null employee codes and national IDs remain unique.
- Added a user-controller regression test creating two users without employee_code or national_id and asserting both profile rows persist with NULL identifiers.
- Migration and focused regression test are committed on development. Local migration/test execution against the project's SQL Server database is pending; master, release branch and server are unchanged.

## 2026-11-08 — Restore missing stamp/signature images in print output

- Root cause: the print controller exclusively used asset paths frozen in `document_identity_snapshot` whenever a snapshot array existed. Letters registered before the stamp/signature was configured, or whose snapshot omitted an asset path, therefore received a null image data URI even when a current active image was configured. Vue then fell through to the text-template branch for non-text elements, allowing creator identity text to appear in stamp/signature slots.
- Print controller now prefers a still-existing snapshot asset, but falls back to the currently active organization stamp and the created department's active manager signature when the snapshot path is missing or its file no longer exists. Existing valid snapshot assets remain preferred.
- Print rendering now renders template text only for `kind=text`; stamp/signature elements never substitute their content/creator identity for a missing image.
- Extended the print feature test with local fake stamp/signature images and an existing identity snapshot that omits asset paths; the test asserts both image data URIs are passed to the print page.
- Focused test and production build are pending local execution. Development only; master/release/server unchanged.

## 2026-11-08 — Align designer preview with final issuer flow

- Updated the template designer's sample preview to render the body placeholder and issuer elements in one vertical flow region. Issuer spacing is measured after the sample body, rather than from an independent absolute Y coordinate; horizontal offset and width remain configurable.
- Updated print issuer width to use the printable content area (matching the A4 inner region) while retaining the configured right offset and spacing-before.
- Regression fixture now includes a separate issuer identity text element after stamp/signature assets and asserts its configured spacing/content are passed to the protected print page.
- Local focused test and production build are still required; no test/build success is claimed. Development only.

## 2026-11-08 — Print issuer assets and spacing follow-up

- Root cause: the print page rendered every issuer item (stamp, signature and issuer text) inside the absolutely positioned template-coordinate plane. Consequently, issuer `spacing` was never applied, and multiple issuer elements sharing similar template coordinates visually overlapped, making creator/unit identity appear attached to stamp/signature assets.
- Moved issuer elements into the normal document flow immediately after the actual letter body and now apply the existing flow box styling (configured width, right offset, typography and spacing-before). Each issuer element is laid out sequentially; the actual body height determines where the issuer section begins, including when the body spans pages.
- Stamp/signature images remain image-only elements. Creator/unit identity is rendered only by an explicitly configured text issuer element; the legacy creator footer remains suppressed when explicit issuer assets/text are present, avoiding an additional automatic identity block.
- Extended the protected print-page feature fixture to include stamp, signature and a separate creator-identity text element with a nonzero spacing value, asserting the saved spacing/content contract.
- Owner-run focused PHP test, production build and browser print acceptance remain pending. Development only; master, release branch and server unchanged.

## 2026-11-08 — Fix PHPUnit parse error in print regression test

- Corrected the over-escaped fully-qualified `SystemSettingsService` class name in `CorrespondenceHttpTest.php` (line 136), which caused PHPUnit to stop during test-file loading with `unexpected token \\`.
- Commit: `15a409b37b735c086da599e05235e1adf09c3f38`. The test has not yet been executed in the local environment; rerun the focused test after pulling development.

## 2026-11-08 — Correspondence print preview layout and page-number correction

- Rich-text HTML in the print body now uses normal document whitespace rather than preserving indentation/newlines from the HTML source as visible whitespace. Preformatted blocks retain pre-wrap behavior; line-height and table-cell wrapping are normalized to reduce broken layout.
- The on-screen page-number preview now shows a complete one-page sample («۱ از ۱», or the selected slash/prefix format). Print media replaces sample values with actual paged-media counters.
- The legacy creator-identity footer is suppressed when stamp/signature image elements are explicitly present, preventing creator details from appearing as an unintended companion to those image elements. Creator identity can still be placed with its own text issuer element.
- Added HTTP regression coverage for the saved page-number, stamp and signature element definitions passed to the protected print page.
- GitHub commits: a983fce, 9994ff6, a3e6e6e. Owner-run focused PHP test, frontend build and Chromium one-/multi-page print acceptance remain pending. Development only; master, release branch and server unchanged.

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

## 2026-11-04 — Flow-based template preview

- The A4 designer preview now renders metadata, addressee/subject, the automatic body placeholder, issuer/signature and page-number indicator as a document-flow sequence; non-repeating roles no longer use hard-coded Y positions in the preview.
- Repeating elements remain independently positioned. The preview indicates order and approximate occupied regions; actual body length, pagination and final issuer position depend on the real letter HTML.
- The current development feature-test fixture excludes obsolete `height` fields. The reported local failure still expects them, indicating the local test file/revision is stale; pull before rerunning.
- Local tests, frontend checks and multi-page print acceptance remain pending. Development only; master/server unchanged.

## 2026-11-04 — Template preview uses semantic document flow

- Corrected the A4 designer preview: metadata, addressee/subject, automatic body placeholder, issuer/signature and page-number indicator are now displayed as a vertical document-flow sequence, not placed at hard-coded Y percentages. Only repeating elements retain independent page coordinates.
- This makes the designer show the order and approximate occupied regions of automatically rendered content against the actual letterhead background. It does not claim exact pagination: final body length and issuer position depend on the saved letter HTML and may flow onto later pages.
- The earlier local test output still showed removed `height` fields in its expected fixture, while the current `development` test file no longer includes those fields. The owner should pull the latest branch before rerunning.
- Local test/build and physical multi-page print acceptance remain pending. Development only; master/server unchanged.

## 2026-11-04 — Role-based Persian letter template and automatic body flow

- Reworked the A4 template editor around semantic roles: letter number/date, addressee/subject, page number, issuer/signature, and repeating text. Variable choices are filtered by the selected role.
- The organization letterhead is no longer a configurable element; it is loaded automatically as a page background in the editor and print preview.
- The letter body remains automatic, is not a template element, and is rendered from the stored HTML in normal multi-page document flow. RTL direction/alignment and whitespace/rich HTML formatting are preserved in the print body.
- Metadata and addressee/subject render once before the body; issuer/signature elements follow the body; repeating elements use fixed page coordinates; page numbering is controlled by the page-number role. Flow elements expose width and spacing, not arbitrary page Y coordinates.
- Existing stored template elements are normalized for compatibility; legacy letterhead image elements are removed from the element list because the image is now automatic. No migration or PDF dependency was added.
- Validation and physical multi-page print acceptance remain pending owner execution. Development only; master/server unchanged.

## 2026-11-04 — Template token and asset binding completion

- Template variables include recipient organization/unit/position/title, recipient name, subject, letter type, creator name and creator unit title, in addition to organization contact details and letter numbering/date. Variables without a reliable source are not offered as selectable tokens.
- Stamp and signature image elements bind to protected correspondence-specific snapshot assets. Legacy footer rendering remains for stamp/signature/creator identity unless the template explicitly places the corresponding element/token, avoiding accidental disappearance or duplicate output.
- Feature coverage includes persisting a text element and an image asset element, plus denial without `settings.manage`. Local execution is still pending.

## 2026-11-04 — Template designer image elements follow-up

- Added selectable template element kinds: text, organization stamp, and issuing-unit manager signature. Stamp/signature elements use the existing protected, snapshotted image assets; their position and dimensions are configured in the A4 editor. Text elements support creator identity tokens as well.
- Image-only elements may have empty text content; the server normalizes empty values safely. Print preview suppresses legacy footer duplicates when a corresponding stamp/signature is placed through the template.
- Remaining limitation: body and recipient remain normal document-flow regions; repeating page headers/footers and pagination-aware drag zones are still future work. Validation remains pending.

## 2026-11-04 — Visual letter template designer (first implementation slice)

- Added a dedicated settings-managed page at `correspondence.template.edit` / `correspondence.template.update`, separate from Organization Settings. Access requires `settings.manage`.
- The A4 editor supports adding/removing text boxes, selecting boxes, pointer drag to move, resize handle, content editing, visibility, font size/weight, alignment, and insertion of approved variable tokens. Template JSON is stored in `correspondence.print.template` through SystemSettingsService; input is bounded and validated server-side, including page bounds and element count.
- Print preview loads the saved template and renders visible text boxes on the first A4 page. Supported text tokens include organization name/short name/address/telephone/email/website, letter number/registration number/date and recipient name. Unknown tokens remain literal. Existing print output remains the fallback when no template has been saved.
- Removed legacy print-visibility fields from Organization Settings UI, request validation and update persistence. The stamp upload/activation remains in Organization Settings as an organization asset; its location/size is to be controlled by the template designer in a later slice.
- Current editor slice deliberately keeps the letter body in normal multi-page flow; stamp/signature image elements, recipient/body/footer zones, snapping/guides, zoom, and richer pagination-aware layout are not yet implemented. Do not describe the template designer as complete.
- Regression tests added for template persistence and settings permission. Migration/tests/type-check/build/manual print verification remain pending owner execution. Development only; master/server untouched.

## 2026-11-04 — External addressee display and letter-template boundary

- Outgoing correspondence contact options display only the person's name. Addressing components are sourced from the selected external unit and its configured manager; the old per-contact default fallback is not used for new outgoing selection.
- Organization Settings no longer presents the fixed print-visibility/letterhead checkbox panel. Organization identity and stamp asset management remain there; layout/visibility belongs to the letter-template designer.
- The letter-template designer is not yet implemented. Its planned model is a shared A4 layout with movable/resizable content boxes, editable text, standard variable tokens such as `{{address}}`, `{{tel}}`, `{{email}}`, `{{website}}`, `{{organization_name}}`, `{{letter_number}}`, and `{{date}}`, preview, and a single rendering model for print/PDF. Header, recipient, body, stamp and signature are separate configurable regions.
- Local migration/tests/type-check/build/manual acceptance remain pending. Development only; master and server untouched.

## 2026-11-04 — External unit owns manager and addressee data (implementation follow-up)

- The four addressee components are owned by `ExternalDepartment` only: organizational title prefix, organizational position, personal salutation, and professional title. This applies only to external organizational units; internal Core Department manager selection and behavior are unchanged.
- External contact create/edit UI and request validation now collect only the person's identity/contact details. Legacy contact-level addressing/default columns are retained for historical compatibility and migration fallback, but are no longer exposed as current input or list metadata.
- An external unit selects one active manager from contacts belonging to that same unit. Service-level guards prevent deactivating or moving an assigned manager until the unit manager is changed; unit update validates manager ownership.
- Outgoing finalization snapshots the unit's addressing fields and selected manager's name/ID, without requiring an individual contact selection. Legacy contact values remain a fallback for older data.
- Regression coverage added for manager assignment integrity and outgoing recipient snapshot. Local migration, focused tests, TypeScript/build and manual acceptance are still pending; do not claim validation until owner reports results. Development only; master and server untouched.

## 2026-11-04 — External-unit addressing ownership correction

The approved business rule is that external organizational units own their manager/addressee and addressing components. Do not confuse external departments with internal Core Departments. Internal Department.manager_id and its membership/approval model remain unchanged. ExternalDepartment now has manager_contact_id plus organizational_title_prefix, position, personal_salutation and professional_title. The manager is an ExternalContact assigned to that same external unit; individual contacts are not required to carry these addressing fields. Existing legacy contact fields remain in the schema for compatibility, but new UI entry is at the external-unit level. Migration 2026_11_04_140000_move_external_addressing_to_departments promotes each active per-unit default contact and copies its addressing values. Outgoing-letter finalization snapshots unit addressing and manager identity; external_contact_id is optional. Preserve old contact data and legacy snapshot fallback. Validation of migration/tests/build is pending owner run. Development only; do not touch master or server.

## 2026-11-04 — Fix correspondence controller parse error — validated

- Owner confirmed PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` pass after pulling the fix; full-suite/build/manual acceptance remain pending.
- Root cause of the widespread HTTP 500/test failures: the `createDraft()` attribute array in `CorrespondenceController::store()` had a malformed key (`'reply_to_id => ...`) introduced while wiring multi-letter relations. PHP could not parse the controller, so unrelated routes/tests also failed when Laravel attempted to load routes/controllers.
- Corrected the key to `'reply_to_id' => ...`; retained the existing first-related-letter fallback and source-project correspondence fallback. No workflow, validation, authorization, or database behavior was changed.
- Existing `CorrespondenceHttpTest` and `ExternalPartyControllerTest` cover the affected route-loading and create/update behavior. Validation completed by the project owner: PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` passed.
- Development only. `master`, release branch, migrations, and server are untouched.

## 2026-11-04 — Direct correspondence registration follow-up

- Fixed the create-page contract: `Correspondence/Create` now receives the per-type `approvalSettings` used by its submit label and explanatory text. Previously this prop was omitted on the create action (although it was supplied by the edit action), so a type configured for direct registration could still display «ذخیره پیش‌نویس».
- Made the server's immediate-registration decision explicitly use the same `CorrespondenceCompositionSettings::requiresApproval(type)` policy instead of inferring the decision from the created record's workflow state. When approval is disabled for the selected type, the create request invokes numbering/final registration immediately; approval-enabled types remain drafts assigned for approval. Reply/request-generated correspondence remains on its dedicated workflow.
- Added an HTTP regression test asserting the create page receives the direct setting and a direct internal letter is persisted as registered in the initial create request, not as a draft.
- Validation pending on the project owner's machine. Development only; master and server untouched.

## 2026-11-04 — Correspondence approval test department-manager setup

- The two HTTP draft-persistence tests now create an active manager and assign that manager to the creator's primary department. This is required because the approval workflow must resolve an active manager for the creating department; merely setting internal approval to `approval` is not a complete fixture.
- The previous rerun exposed this missing fixture: both requests were correctly rejected with the Persian validation message that no active approval manager was configured. Product validation and authorization remain unchanged. Validation pending; development only; master and server untouched.

## 2026-11-04 — Correspondence test alignment with direct-registration policy

- Local correspondence test run exposed four failures caused by stale test assumptions, not four distinct runtime regressions: two HTTP tests still expected internal letters to remain drafts although the default internal policy is direct registration; two numbering-service tests attempted to register an internal letter as a separate Secretariat registrar although direct internal registration is creator-only.
- Updated the two draft-specific HTTP tests to explicitly set internal approval to `approval`, preserving the purpose of those tests (draft persistence and attachment handling).
- Updated the two numbering tests to register internal letters as their creator, matching the documented direct-registration authorization policy.
- The previous run had 71 passing tests and 4 failing assertions/exceptions. Rerun the focused correspondence suites locally; validation remains pending. Development only; master and server untouched.

## 2026-11-03 — Dropdown and correspondence registration corrections

- Both shared selectors now measure their rendered popup height before choosing above/below placement, preventing incorrect offsets near the bottom of the viewport.
- Drafts requiring approval are assigned to the active manager of the creating department and enter awaiting_manager_approval, so they appear in that manager's needs-action list.
- After approval, outgoing drafts move to the configured Secretariat manager for final registration. The needs-action filter includes this approved-for-registration state.
- With the direct setting, normal correspondence is finalized in the create request, including numbering and the normal post-registration workflow. The create form displays a final-registration action instead of a draft-save action. Special reply drafts retain their dedicated workflow.
- Registration authorization is aligned with the configured policy and workflow state. Regression tests cover manager assignment/visibility, direct internal finalization, and outgoing approval handoff. Local validation is pending. Development only; master and server are unchanged; no migrations or dependencies were added.

## 2026-11-03 — AppSelect change-event typing correction

- Updated `CustomFieldsForm.vue` so its select change handler accepts the documented AppSelect payload shape (`{ target: { value: string } }`) instead of a native DOM `Event`. This resolves the TypeScript incompatibility introduced when the native select was replaced.
- Runtime behavior remains unchanged: the selected string is stored, and an empty value is normalized to `null`.
- Validation pending: rerun `npm run build`. Development only; master and server remain untouched.

## 2026-11-03 — Shared application select rollout

- Added `resources/js/Components/AppSelect.vue` as the common application-rendered select control. It renders a consistent RTL option panel with a rounded surface, subtle border/shadow, selected-state indicator, viewport-aware positioning, outside-click and Escape dismissal, keyboard navigation, and automatic search for longer option lists.
- Replaced native `<select>` controls in the identified form/filter surfaces across Correspondence, Tasks, Projects, Core users/departments/settings/roles/custom fields/external parties, the reusable custom-fields form, and the Jalali date-time picker. The role permission-scope selector's controlled `:value` pattern and selects without `v-model` remain supported.
- Preserved `v-model`, controlled `:value`, `@change` target-value handling, disabled states, option conditions/loops, and required-field markers. Department selection continues to use `DepartmentTreeSelect.vue`; this component is for ordinary single-choice lists.
- Validation pending: run `npm run build`, then manually verify short and long lists, search, selection updates, disabled options, required markers, change handlers, keyboard navigation, outside click, Escape, and the Jalali hour/minute controls. `development` only; `master` remains at its prior revision. No backend, database, migration, or dependency changes.

## 2026-11-03 — Shared dropdown and popover UX correction

- Updated the shared `Dropdown.vue` used by the authenticated user menu: consistent rounded surface, border and shadow; document-level outside-pointer dismissal; Escape dismissal; and no longer closes merely because an inner content area receives a click.
- Updated `DepartmentTreeSelect.vue`: outside-pointer dismissal works with its teleported panel, Escape closes and restores focus to the trigger, and the popup uses a minimum usable width while remaining within the viewport. Multi-select remains open while options are selected and closes on outside click or explicit confirmation.
- Updated `AuthenticatedLayout.vue`: desktop navigation menus and notification panels close when the pointer goes outside their own popover; opening a navigation menu closes notifications and vice versa.
- Scope is shared controls/layout behavior, so all screens using these shared components inherit the correction without per-form overrides. Native browser select controls remain browser-rendered; this change targets application-rendered dropdowns and unit selectors.
- Validation pending: run `npm run build`, then manually verify the user profile menu, notifications, desktop menus, and single/multiple department selectors in forms. `development` only; `master` unchanged. No backend, database, migration, or dependency changes.

## 2026-11-03 — Fix needs-action index closure scope

- Root cause of the reported HTTP 500: the nested `needs_action` query closure referenced `$isSecretariatManager` and `$secretariatDepartmentId` without capturing them in its enclosing closures. PHP raised `Undefined variable $isSecretariatManager` at `CorrespondenceController.php` during correspondence index requests.
- Captured both values in the `when(needs_action)` callback and its `$pending` callback. No workflow states, visibility rules, secretariat authorization, assignment rules, or query semantics were changed.
- Existing `CorrespondenceHttpTest` cases exercise the affected index/filter path, including manager and department routing scenarios. Validation is pending on the project owner's local environment; run the focused command provided in the handoff. `development` only; `master` unchanged. No migration or dependency changes.
## 2026-10-31 — Regression fix: needs-action after direct referral

- Restored the direct-assignment rule that excludes a correspondence from `needs_action` when the assigned user has already recorded an action after the latest referral to them. Current assignments with no referral-history row remain actionable for legacy-data compatibility.
- Department-manager pending logic remains based on the current department manager and actionable workflow states, and excludes `assigned_to_employee`; this keeps manager routing independent from potentially incomplete historical referral rows.
- Regression source: `correspondence action filter distinguishes pending and previously acted referrals` had exposed a direct assignment with a later `REQUEST_TASK` being counted as pending. Validation pending: rerun `php artisan test --filter=CorrespondenceHttpTest`; `master` unchanged.
## 2026-10-31 — Correspondence action and department filters

- Reworked «نیاز به اقدام» to use the current assignment as the source of truth: direct current_user_id assignment, or an actionable current department whose manager_id is the user. The manager branch includes in_progress, result_pending_manager, and awaiting_manager_approval; assigned_to_employee is intentionally excluded. This avoids depending on complete historical referral/action rows and does not grant action rights to ordinary department members.
- Added a department filter to the correspondence index. In «مکاتبات من» / «مکاتبات واحد من», the default «من» leaves the existing scope unchanged and selectable departments are limited to the user's memberships plus managed units. In «همه مکاتبات مجاز», the default is «همه واحدها» and the options (including inactive units for historical correspondence filtering) come from the Core Departments module's DepartmentServiceInterface::list().
- A selected unit narrows only correspondence-to-department associations (created, recipient, current, copy, or workflow action). It is always applied after CorrespondenceAccessService::visibleToQuery; confidential visibility and all existing scopes remain enforced. Invalid or out-of-scope unit IDs are normalized to the scope default.
- Reused the shared DepartmentTreeSelect.vue and added support for non-department sentinel options («من» / «همه واحدها») without changing existing selector behavior.
- Added regression tests for manager-owned current assignments without referral history, employee assignment exclusion, result return to manager, department option scoping, and confidential-letter visibility under unit filtering. Validation pending: run the focused correspondence tests and npm run build; master unchanged.
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

## 2026-10-26 — Correspondence registration and tracking audit

- Owner confirmed `php artisan test --filter=CorrespondenceHttpTest` passes for registration audit: final registration creates both a `registered` action and `correspondence.action.registered` Core Audit event.
- Outgoing external tracking now runs under a DB transaction and row lock. Changed values produce `correspondence.external_tracking.updated` with changed field names only; tracking note contents and other values are deliberately excluded from audit payloads. No-op updates do not create audit noise.
- Added a privacy regression test covering stored tracking fields and ensuring the private note is absent from the audit record. This latest grouped change is pending user validation. Recommended validation: full `php artisan test` and `npm run build` once after pulling `development`.
- Branch remains `development`; do not modify `master`.

## 2026-10-26 — Correspondence copy audit

- `CorrespondenceWorkflowService::addCopies()` now routes newly attached copy recipients through the common action recorder, writing the business action and Core Audit event together in a transaction.
- Duplicate attachment attempts do not emit duplicate action/audit records.
- New test: `tests/Feature/Modules/Correspondence/CorrespondenceCopyAuditTest.php`.
- Next validation step: pull `development` and run `CorrespondenceCopyAuditTest` plus `CorrespondenceHttpTest`. Do not start a second test step before the owner reports the result.
- Next implementation review after successful validation: assess audit/notification/Cartable coverage for final registration and outgoing dispatch, then continue the V2 acceptance gate. `master` remains unchanged.

## 2026-10-21 — Correspondence authority correction

- V2 rule: registered ordinary letters are visible organization-wide; confidential letters are visible to their creator/registrar/explicit participants and the configured secretariat.
- All active users may create internal letters and outgoing drafts. Incoming-letter creation belongs to the department configured as `organization.department_roles.secretariat`.
- Final registration/numbering of incoming and outgoing letters belongs to that configured secretariat. A creator may register their own internal letter.
- Resolve secretariat through `OrganizationDepartmentRoleService`; do not replace it with a generic `correspondence.register` role check.
- Backend authorization is enforced in HTTP handlers and `CorrespondenceNumberingService`; frontend type/action visibility is UX only.
- Changes are on `development`; focused HTTP and numbering tests are pending. Keep `master` unchanged.


## 2026-10-21 — Baseline access for correspondence menu
- Active staff now receive organization-wide baseline `correspondence.view` and `correspondence.create` through `User::responsibilityDerivedScopes()`, so the correspondence menu and draft form are available without a bespoke role grant.
- Final registration/numbering is enforced against the configured secretariat; the creator may register their own internal draft. Confidential visibility is enforced by `CorrespondenceAccessService` for related users and the configured secretariat.
- Updated `CorrespondenceHttpTest` to cover index and draft-form access for an active user without explicit correspondence role permissions.
- Local validation pending: pull `development` and run `php artisan test --filter=CorrespondenceHttpTest`. Keep `master` unchanged.



## 2026-10-21 — Correspondence visibility test fixture fix
- The three new visibility tests initially failed because different permission codes used the same unique `permissions.name` value. The test helper now suffixes the permission code to the name; lookup remains keyed by permission code.
- Pull `development` and rerun `php artisan test --filter=CorrespondenceHttpTest`; frontend build remains a later separate validation step. No production policy behavior changed. Keep `master` unchanged.
## 2026-10-21 — Full test suite follow-up

- The user ran the full Laravel suite: 401 tests passed; two tests failed.
- `CorePermissionSeederTest` expected 53 permissions, but the current seeder correctly creates 56 after the three V2 correspondence permissions were added. Updated the expected count to 56.
- `ExampleTest` expected HTTP 200 for `/`, but the intended guest route redirects to the login page. Updated the test to assert the named login redirect; application routing was not changed.
- These are test expectation corrections only. Changes are on `development`; keep `master` unchanged.
- Local verification pending: run the full test suite after pulling the branch.

## Latest handoff — 2026-10-21 V2 correspondence HTTP foundation

- Added CorrespondenceController with index/create/store/register actions. Registration delegates to the existing CorrespondenceNumberingService, preserving the draft-only rule and configured approval requirement.
- Added resources/js/Pages/Correspondence/Index.vue and Create.vue for Persian RTL list/filter/pagination and draft creation for incoming, outgoing and internal correspondence.
- Added correspondence.view, correspondence.create, and correspondence.register permissions, Inertia ability exposure, and a navigation link.
- Added tests/Feature/Modules/Correspondence/CorrespondenceHttpTest.php for authorized listing, draft creation, and denied index access.
- Local validation is pending. The first focused test run exposed an incorrect `PermissionScope` import in the new test fixture; fixed to `App\\Core\\Permissions\\Enums\\PermissionScope` in commit `d7afe98ffcb270bf2bcb9c0a03a4c309feaa47a3`. Rerun the focused correspondence test and frontend production build before continuing. Composition modes (internal editor, file upload and combined) are implemented; next: correspondence detail/edit, referral/instruction, workflow/Cartable integration, notifications/audit.
- Branch remains development; keep master unchanged. Development checkout: D:\\KPMM\\Office-automation.

## Latest handoff — 2026-10-21 frontend notification build fix

- Fixed the TypeScript error in `resources/js/Layouts/AuthenticatedLayout.vue`: the mobile notification dropdown referenced undefined `markNotificationRead`; it now calls the existing `openNotification(item)` handler, matching desktop behavior.
- The existing handler closes the dropdown, marks unread notifications through `notifications.read`, then navigates to the notification URL (or notification index fallback).
- User reports `php artisan test --filter=NotificationTest` passed. Frontend build verification is pending.
- User's development checkout path is `D:\\KPMM\\Office-automation`; do not use the release checkout path for development validation. Keep `master` unchanged.

## Latest handoff — 2026-10-21 notification test correction

- The comment notification contract is current department manager, falling back to project manager only when no department manager is assigned, plus the current assignee; the comment author is excluded.
- `NotificationTest` had a stale fixture expecting a non-manager task creator to be notified. The regression fixture is aligned by making the creator the department manager; production behavior is unchanged.
- After pulling `development`, validate with `php artisan test --filter=NotificationTest`. Do not merge to `master` until the user validates.

# AI Context — Office Automation

> Read this file before making code changes. It is a compact handoff for any AI/engineer continuing the project.

## Project
- Repository: `mehdijalilimaraghi-del/office-automation`
- Active development branch: `development`
- Stable branch: `master`
- Never develop on `master`.
- The application is a Persian, RTL, Modular Monolith office-automation system for a single internal company.
- Initial deployment target: company LAN. Future target may include VPS/cloud.
- Target organization size: approximately 10–300 users.
- No accounting module.

## Technology
- Laravel 12.x
- PHP 8.5.x
- Vue 3 + TypeScript
- Inertia.js
- Tailwind CSS 4
- Vite
- shadcn-vue
- Microsoft SQL Server
- Persian RTL
- Jalali presentation where dates are user-facing
- Vazirmatn

## Authoritative project documents
Read these before implementation when the task touches their scope:
1. `ROADMAP.md` — product versions, scope and acceptance gates
2. `CURRENT_STATUS.md` — current checkpoint, blockers and immediate next work
3. `PROJECT_CONTEXT.md` — detailed persistent context and domain decisions
4. `ARCHITECTURE.md` — architecture and module boundaries
5. `PROJECT_SPEC.md` — product/system specification
6. `DEVELOPMENT.md` — engineering workflow

## Current version
**V1 — Foundation & Work Management: closed by project owner.** Future fixes may still be made if needed, with regression coverage.

**V2 — Office Automation: started and authorized.** Current focus is correspondence composition (internal editor, file upload or both), followed by correspondence detail/edit, referrals/instructions, workflow/Cartable integration, and request forms.

## V1 capabilities already implemented
- Authentication and username-based login
- Invitation-based onboarding
- Users, profiles, activation and avatar handling
- Roles and permissions
- Standalone `SYSTEM_ADMIN` bootstrap account
- Internal departments and unlimited hierarchy
- Department membership and managers
- Organizational positions and assignments
- Projects and project roles/members
- Tasks, assignment, referrals, comments, work logs, reminders, timeline
- Task review/approval and resubmission flow
- Task dependencies
- Documents/attachments foundation
- Workflow V1
- Unified Cartable V1
- Notifications V1
- Audit V1
- Organization Settings
- Custom Fields foundation/integration
- System-generated business codes
- External organizations, departments and contacts as a separate domain
- Persian RTL/Jalali UI foundations

## Critical domain decisions
- Role = reusable set of application permissions.
- A normal user has at most one application Role.
- Responsibilities such as Project Manager or Department Manager are contextual assignments, not generic global Roles.
- Internal users/departments and external organizations/contacts are separate domains and must not be merged.
- Workflow, Documents, Cartable, Notifications and Audit are shared capabilities and must not be duplicated in business modules.
- Backend authorization is mandatory; frontend checks are UX only.
- Business/system codes are generated by the application. Users should not maintain them manually.
- Custom-field technical keys are system-only data. The user enters a field label, not a technical key.
- Preserve Modular Monolith boundaries. Do not introduce cross-module database coupling.

## Current repository checkpoint
The two blockers previously recorded in the handoff were re-inspected on `development`:

- `app/Core/ExternalParties/Http/Controllers/ExternalPartyController.php`: the current file is syntactically complete and contains valid `custom_fields` validation for all create/update operations. The previously documented malformed edit is not present.
- `app/Models/Concerns/HasSystemCode.php`: the current implementation performs a query-builder update of only `code` after creation, avoiding the prior Eloquent `updateQuietly()` path associated with the SQL Server identity-column error.
- `tests/Feature/Core/SystemCodeGenerationTest.php` exists and covers generated system codes across Core, Projects and External Parties.

These are therefore not new implementation blockers on the current branch. The remaining requirement is local SQL Server validation.

## Working protocol — mandatory
- The AI has repository access and is responsible for writing/editing the code and tests in GitHub.
- The user does not manually edit project code.
- After every code change, give the exact PowerShell command the user should run locally to pull the change and validate it.
- Use `git pull origin development` at the beginning of the PowerShell validation command **only when code changes were made immediately before that validation**.
- Do not include `git pull origin development` when no code change was made.
- Prefer one validation step at a time.
- Do not ask unnecessary confirmation questions.
- When a normal implementation/debugging decision does not change the approved project direction, make the decision and implement it directly.
- Ask/wait for user approval only when proposing a change to the approved project path, architecture, scope, module/version boundary, or another decision that materially changes direction.
- Fix root causes. Do not weaken tests, bypass authorization, remove safeguards, or use temporary hacks just to make tests pass.
- Inspect the actual repository and relevant persistent documents before changing code.
- Add/update regression tests for meaningful behavior and defects.
- When frontend code changes, include frontend build validation at the appropriate stage.
- UI/UX must follow the latest available Google Material Design principles at implementation time, adapted correctly for Persian RTL, accessibility, readability, spacing, hierarchy and responsive behavior.
- Keep explanations concise; focus on what changed, why, and the exact next validation step.
- Update `ROADMAP.md`, `CURRENT_STATUS.md`, `PROJECT_CONTEXT.md` or other authoritative documents when implementation/status/architecture materially changes.

## Normal execution loop
**Inspect → Root Cause/Boundary Review → Smallest Coherent Change → Tests → Build → User Manual Acceptance → Documentation → Next Item**

## New conversation startup
Before making the first code change in a new conversation:
1. Read this file.
2. Read `CURRENT_STATUS.md`.
3. Read `ROADMAP.md`.
4. Inspect the repository state and the relevant implementation.
5. Continue from the documented checkpoint; do not redesign or restart the project.

## Immediate next work
1. Validate V2 correspondence tests against the configured SQL Server database.
2. Run the frontend production build.
3. Manually verify editor, file-only and combined composition modes, including download access to attached documents.
4. Fix any defects found at root cause and retain regression coverage.
5. Continue V2 correspondence detail/edit, referrals/instructions and Workflow/Cartable integration.


## Current task activity model — 2026-10-10
- Use `task_activities` as the single storage table for planned next activities and completed activity reports.
- `activity_type=planned,status=pending` appears under «فعالیت‌های بعدی»; completing it updates the same row to `activity_type=logged,status=completed` and records `completed_by/completed_at`.
- Manual completed activity reports are also `logged/completed` rows. `started_at` and `completed_at` live on `task_activities`.
- Migration `2026_10_10_120000_unify_task_activities_and_work_logs.php` moves historical `task_work_logs` into `task_activities` before dropping the old table. Validate migration and rollback behavior through the focused task tests.
- Task detail UI uses «ثبت فعالیت انجام‌شده» and a persistent guide explaining the difference between planned and completed activities.


## Deferred UI acceptance item — task activity guide (2026-10-10)
- Do not re-add or reposition the persistent task-activity guide during admin-account checks.
- During V1 manual acceptance with a normal user account, review the task detail page and, if the guide is still needed, place it in a dedicated full-width section below the two-column layout containing «فعالیت‌های بعدی» and the «ثبت فعالیت بعدی» section/button. Do not place the paragraph inside either column.
- The user explicitly deferred this UI decision until normal-user acceptance; leave it pending rather than making further changes now.


## Authorization correction — 2026-10-12
- The active `SYSTEM_ADMIN` role is a global system capability: `User::hasPermission()` returns true and `permissionScopes()` returns organization scope for it, while inactive users remain denied.
- User capabilities are additive: actual position/context responsibilities derive operational access, and an application Role can explicitly grant additional permissions. Role grants must not be silently removed by `CorePermissionSeeder`.
- The role permission UI exposes active operational permissions and retains per-permission scope selection. `SyncRolePermissionsAction` validates scopes but does not prohibit operational grants.
- Regression tests were added for system-admin access and operational role grants. Focused tests and frontend build are pending; remain in V1 until manual acceptance is complete.


## PowerShell validation output — 2026-10-12
- Keep validation commands friendly to Windows PowerShell: avoid Persian status/error strings that may render incorrectly in the user's console.
- Do not use `exit` to propagate a failing test status because it closes the user's PowerShell session. Capture and display `$LASTEXITCODE` instead, leaving the shell open.
- Current blocker: `tests/Feature/Core/CorePermissionSeederTest.php` had a PHP parse error caused by doubled backslashes in a fully qualified class name. The fix is committed; rerun the focused authorization tests before proceeding.


## Authorization test fixture correction — 2026-10-12
- `UserAuthorizationTest`'s ordinary role fixture must never use code `SYSTEM_ADMIN`, because that code intentionally triggers the global administrator bypass. Use a non-system test role; dedicated admin tests should create `SYSTEM_ADMIN` explicitly.
- Tests that need a permission already seeded/granted in setup should retrieve or `firstOrCreate` the permission by unique code rather than inserting a duplicate row (SQL Server enforces `permissions_code_unique`).
- Focused authorization rerun is pending after these fixes.


## 2026-10-21 — Correspondence visibility and detail page

- Correspondence navigation now appears when the user has `correspondence.view` or `correspondence.create`; the destination respects the available permission.
- Added `correspondence.show` and the `Correspondence/Show.vue` detail view. Keep attachment authorization delegated to the existing document download policy and do not expose storage paths.
- Added HTTP coverage for opening correspondence details. Local validation is pending; keep `master` unchanged.


## 2026-10-21 — V2 correspondence visibility baseline

- Implemented the first security slice on `development` only; `master` remains unchanged.
- Migration `2026_10_21_120000_add_classification_and_user_access_to_correspondences.php` adds `classification` (`ordinary` default) and `correspondence_user_access`. The `granted_by` column intentionally has no FK to avoid SQL Server multiple-cascade-path issues.
- `CorrespondenceAccessService` is the current source of truth for list/detail visibility. Registered ordinary letters are visible to active users with `correspondence.view`; drafts are scoped to creator/participant/secretariat; confidential letters are scoped to creator/registrar/participant/secretariat.
- `CorrespondenceController` uses this service for index, detail, and visible reply choices; linked reply metadata is filtered as well.
- `DocumentPolicy` delegates correspondence attachment access to the same service before applying generic document-level access, to prevent bypassing confidentiality.
- `CorrespondenceHttpTest` now covers registered ordinary visibility, denial for unrelated confidential viewers, and access for explicit participants.
- Validation is pending: pull the branch locally, run `php artisan migrate`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. Do not proceed to referral UI until those pass.

## Latest handoff — 2026-10-26 Core Audit integration for correspondence workflow

- Added Core Audit recording to CorrespondenceWorkflowService::record() using AuditServiceInterface. Each business action remains stored in correspondence_actions; the Core Audit record stores the action event, action row ID, and source/target department/user IDs as metadata.
- Extended the existing manager-approval HTTP regression test to assert the matching audit_logs row.
- Code is committed to development; master is unchanged. Local test execution is pending. Run the exact focused PowerShell command in CURRENT_STATUS.md after pulling.
- Next: validate this slice, then audit registration/copy/dispatch transitions and the Cartable/notification integration contract. Do not start Forms & Requests until the remaining V2 acceptance gate is reviewed.


## 2026-10-27 — Cartable TypeScript build fix

- Corrected the Cartable template to use `letter.current_department.name`, consistent with the `CorrespondenceItem` TypeScript interface. No application behavior or backend logic was changed.
- Committed to `development` only. Run `npm run build` after pulling and confirm the result; do not claim validation until reported.


## 2026-10-27 — Open correspondence UX and numbering requirements

- Open UI fix: correspondence index should render `registered_at` in Persian/Jalali date and Tehran local time, labeled `ثبت نهایی`, rather than exposing raw ISO UTC.
- Numbering design requirements: keep `شماره نامه` separate from the secretariat-assigned `شماره ثبت`; custom letter-number formats should allow two or three fields; each organizational department needs a required configurable short code (e.g. `IT`) to use in the letter-number format instead of its numeric database ID.
- Before implementation, inspect existing settings, department model/form, number generation, database constraints, and feature tests. Preserve compatibility with existing records. These are backlog requirements, not completed changes.

## 2026-10-27 — Correspondence number separation and department codes (validation pending)

- Added a dedicated letter_number field. New drafts receive a letter number immediately using the organization-configured template; the Secretariat assigns a separate registration_number at final registration (REG + configured separator + sequence). Existing registration numbers are preserved; legacy drafts without a letter number receive one at final registration.
- Added a separate counter table for letter numbers while retaining the existing counter for Secretariat registration numbers.
- Custom letter-number templates now accept two or three distinct components. The settings form exposes a two/three-part selector and persists only the selected components.
- Department creation/editing now requires a unique short alphanumeric code (hyphens/underscores allowed), persists it, and uses it in letter-number templates instead of the database ID. Existing department codes are preserved.
- Correspondence list/detail surfaces display letter number and registration number separately. The list formats registered_at as a Jalali date and Tehran local time and labels it «ثبت نهایی»; search includes both numbers.
- Added/adjusted regression coverage for two-part templates, distinct registration numbers, draft letter-number assignment, and department code persistence.
- Validation pending: pull development, run focused correspondence numbering/service and department tests, then full php artisan test and npm run build. No tests or build have been run by the assistant. master unchanged.

2026-10-27 — Correspondence copy recipient UX and ongoing workflow

- The creation form now places optional copy recipients at the bottom and uses a checkbox multi-select list; users can select multiple departments.
- Correspondence create date inputs now use the shared JalaliDatePicker component for consistent Persian font styling.
- Added POST `correspondence.copies.store`: an active user must be able to view the letter and be the manager of its current responsible department; the workflow must not be completed. One or more active departments may be added as copies. Existing `CorrespondenceWorkflowService::addCopies()` handles idempotent attachment and action/audit recording.
- Added focused HTTP coverage for manager authorization and multiple recipients. Local validation is pending: `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. No database migration is required; `master` remains unchanged.

2026-10-27 — Related correspondence field and required dates

- Form label changed to «در ارتباط با مکاتبات قبلی (اختیاری)» to reflect reply and continuation relations.
- Related correspondence is searched remotely via correspondence.related-search; query matches subject, letter number, registration number, external registration number, internal department name/code, and external organization/department name/code. Search is visibility-filtered and returns up to 20 results with subject, letter/registration numbers, date, and party details.
- sent_at is required for outgoing and received_at is required for incoming, with matching UI markers and backend validation.
- Focused tests added; local test/build validation pending. No database migration required. Do not modify master.

## 2026-10-27 — Related correspondence selector UI alignment (validation pending)

- Both related correspondence and copy-recipient controls now use `sm:col-span-2` for matching widths.
- Removed the separate «برای جستجو حداقل دو نویسه وارد کنید.» panel; the minimum length is shown in the search placeholder. Search result rows appear in a bordered scroll region only after two characters are entered.
- Selected related letters now display in a single removable list styled consistently with selected copy recipients; nested borders were removed. No backend behavior changed.
- Local validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. `master` unchanged.

## 2026-10-27 — Related correspondence dropdown overlay correction (validation pending)

- Restored the outer border/padding around the related-correspondence selector.
- Search matches now appear in an absolute-positioned dropdown with a white background and shadow, overlaying following content instead of changing page layout. Selected letters remain in a separate list below the search input.
- Local validation pending: pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then `npm run build`. `master` unchanged.


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


## 2026-10-31 — Same-department employee referral state regression

- Confirmed the manager-to-employee referral implementation correctly sets `workflow_state=assigned_to_employee` while retaining the current department and assigning `current_user_id` to the selected active department member.
- This state is required by the employee-result guard: only the specifically assigned employee may submit the documented result; another member of the same department must be denied. After submission, the state advances to `result_pending_manager`.
- The failing HTTP test had a stale expectation of `in_progress` after employee referral. Corrected the regression assertion to `assigned_to_employee`; no production workflow behavior or schema changed.
- Validation pending: owner to pull `development` and run the focused `CorrespondenceHttpTest` once. `master` remains unchanged.

## 2026-10-31 — Correspondence department assignment audience

- Department-level correspondence routing belongs to the current department manager until that manager explicitly assigns it to an employee. Do not surface actionable correspondence to every member of the department.
- Cartable includes correspondence assigned to the current user and manager-owned items; it must not use current-department membership as an actionable audience. Add `assigned_to_employee` to its supported states so the specifically assigned employee receives the item.
- Backend `submit_result` authorization and the Show-page action flag must both require `workflow_state=assigned_to_employee` and `current_user_id=actor.id`. Department membership alone is never enough.
- The shared department tree selector is teleported to body; keep its viewport positioning clamped and responsive to scroll/resize.
- Implementation committed on `development`; focused correspondence/Cartable tests and frontend build pending owner validation. `master` unchanged.


## 2026-10-31 — Correspondence personal ownership and action filters

- Added a second independent filter to the correspondence list: «همه مکاتبات»، «نیاز به اقدام»، and «اقدام قبلی ثبت‌شده توسط من». It composes with the existing «مکاتبات من / مکاتبات واحد من / همه مکاتبات مجاز» scope and preserves other search filters and pagination query parameters.
- «نیاز به اقدام» is based on the current assignment (current_user_id equals the signed-in user). «اقدام قبلی ثبت‌شده توسط من» requires both a historical referral addressed to that user (correspondence_actions.to_user_id) and an action authored by that user (actor_id), and excludes letters currently assigned back to that user so pending work takes precedence.
- Corrected the «مکاتبات من» ownership scope: an action authored by the user is no longer sufficient by itself. Historical referrals addressed to the user remain included; merely referring a letter to another user/department does not make it the referrer's personal correspondence.
- Added feature regression coverage for pending referrals, previously acted-on referrals, and letters merely referred onward by the current user. No database migration or workflow authorization changes.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master remains unchanged.


## 2026-10-31 — Correspondence list action-filter semantics

- The second correspondence filter defaults to «نیاز به اقدام» and offers «اقدام قبلی ثبت‌شده توسط من»، «ثبت‌شده توسط من»، and «همه مکاتبات». It composes with the first scope selector (personal, department, or all visible).
- Pending action is based on the latest action referral addressed to the user: the correspondence must still be assigned to that user, and the user must not have recorded a later action. This handles actions that leave current assignment unchanged and repeated referrals to the same user.
- Previously acted-on means the user recorded an action after the latest referral to them. A newer referral resets the correspondence to pending until another action is recorded.
- Created/registered by me includes both created_by and registered_by. All correspondence retains the existing personal-relevance scope.
- Regression coverage added for default pending, prior actions, assignment retained after action, creator/registrar, and all-related mode. Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master unchanged.


## 2026-10-31 — Draft visibility test and independent correspondence filters

- Tests that assert draft visibility through the correspondence index must pass both `scope=all` and `action_filter=all`. The default action filter is `needs_action`, which intentionally excludes drafts that are not currently assigned to the viewer.
- Keep authorization/visibility assertions separate from action-filter assertions; do not change production draft visibility rules to satisfy a test that omitted the independent action filter.
- Test fixture alignment committed on `development`; focused `CorrespondenceHttpTest` rerun pending. `master` unchanged.


## 2026-10-31 — Multi-department manager pending filter

- «نیاز به اقدام» must support both direct user referrals and department-level assignments to any department currently managed by the user.
- For department routing, use current_department_id matched against the user's managedDepartments, and confirm the latest routing action targets that current department. Include manager-actionable states (in_progress, result_pending_manager, awaiting_manager_approval); exclude assigned_to_employee so department membership/management does not override explicit employee assignment.
- The «مکاتبات من» scope includes current correspondence in departments the user manages. Do not broaden this to ordinary department members.
- Regression test added for managers of multiple departments, null to_user_id department referral, and employee assignment exclusion. Focused test pending; no frontend change. master unchanged.


## 2026-10-31 — Correspondence index closure fix (validation pending)

- `CorrespondenceController::index()` now captures `$managedDepartmentIds` in the `scope=mine` query closure, fixing the undefined-variable error raised when the correspondence list was requested.
- This is a closure-scope correction only; personal scope and manager pending-action semantics are unchanged. The focused `CorrespondenceHttpTest` run is pending on the project owner's SQL Server environment. Branch: `development`; `master` unchanged.

## 2026-10-31 — Nested correspondence scope closure correction

- Corrected the inner `where(function ($mine) ...)` closure in `CorrespondenceController::index()` to capture `$managedDepartmentIds` as well. The earlier fix captured the variable only in the surrounding `when(scope=mine)` closure; PHP closures do not implicitly inherit variables from an enclosing closure, so the nested query still raised `Undefined variable $managedDepartmentIds` at the `orWhereIn` condition.
- No query semantics or authorization rules changed. Validation pending: pull `development` and run `php artisan test --filter=CorrespondenceHttpTest`. PHP/documentation-only change; no frontend build required. `master` unchanged.


## 2026-10-31 — Correspondence list filter interaction

- The action filter is shown only for the `mine` scope. For `department` and `all`, it is normalized to `all` server-side and does not narrow the result set.
- Renamed the action option to «اقدام قبلی ثبت‌شده» (removed redundant «توسط من» wording).
- All select filters now apply automatically; text search applies after a 350 ms debounce. Removed the manual «اعمال فیلتر» button.
- Added a regression assertion that `scope=department` ignores `action_filter=needs_action` and returns the full department-scope set. Validation pending: `php artisan test --filter=CorrespondenceHttpTest` and `npm run build`. `master` unchanged.

## 2026-10-31 — Project-requested correspondence in manager action list

- Included `project_requested` in the actionable workflow states for the manager of the current department. A letter routed to a project department must appear in that department manager's `needs_action` list until it is assigned to an employee or transitions to another state.
- Added a Correspondence HTTP regression test for a registered incoming letter in `project_requested`, with `current_user_id = NULL`, confirming it appears in the current manager's default list. Existing access filtering and the `assigned_to_employee` exclusion are unchanged.
- Validation pending: run `php artisan test --filter=CorrespondenceHttpTest`; `master` unchanged.


## 2026-11-01 — Task-to-correspondence entry point and project workflow correction

- Added an «ایجاد نامه جدید» entry point on the task details page for the current task assignee when the user also has correspondence-create permission. The correspondence composition form receives the task context, validates the task reference, authorizes the current assignee, and stores the new correspondence `task_id` link.
- Removed the legacy `project_requested` workflow state from the manager `needs_action` filter. A state label alone must not represent a completed project-request process; the project request flow must be implemented with explicit actions and project creation/linkage. Updated the regression test to assert that the legacy state is not actionable.
- This is an incremental first slice only. The project-specific approval flow, project creation with a selected response department/section/task, and source-letter linkage are not yet implemented.
- Validation has not been run in this editing environment. Pull `development`, run `php artisan test --filter=CorrespondenceHttpTest`, then run `npm run build` before accepting this slice. `master` unchanged.


## 2026-11-01 — مرجع الزامی گردش کار درخواست پروژه و اتصال تسک/مکاتبه

- قبل از هر تغییر مرتبط، `CORRESPONDENCE_PROJECT_WORKFLOW.md` را بخوان؛ این سند مرجع نیازمندی‌ها و معیارهای پذیرش است.
- چهار اقدام کارمند واحد پروژه‌ها باید صرفاً «درخواست» باشند و هر چهار مورد بازخورد اجباری داشته باشند. تصمیم مدیر مرحله‌ای مستقل است؛ رد درخواست کارمند باید به همان کارمند برای بررسی مجدد برگردد و با رد درخواست پروژه اشتباه نشود.
- ایجاد پروژه فقط پس از تأیید مدیر مجاز است. مسئول پاسخ‌گویی در هر پروژه انتخاب می‌شود؛ تنظیمات فقط واحد پیش‌فرض قابل‌تغییر را فراهم می‌کند. در پروژه جدید فقط Section واحد پاسخ‌گو و تسک تهیه پاسخ ساخته می‌شود؛ برای واحد درخواست‌کننده Section/تسک خودکار نساز.
- تسک پاسخ به نامه مبدأ و پروژه مرتبط است. نامه‌ای که از داخل تسک ایجاد می‌شود باید ارتباط تسک، پروژه و نامه مبدأ را حفظ کند و روابط از جزئیات تسک و مکاتبه قابل پیمایش باشند.
- وضعیت واقعی در تاریخ 2026-11-01: ورودی ایجاد نامه از جزئیات تسک و ذخیره `task_id` برای مسئول فعلی دارای مجوز موجود است؛ مراحل ۱ تا ۳ و تست انتها‌به‌انتها انجام‌نشده‌اند؛ مرحله ۴ اتصال دوسویه/انتقال روابط ناقص است. جدول مرحله‌ها در سند مرجع نگهداری می‌شود.
- پس از هر مرحله، وضعیت را در سند مرجع و فایل‌های وضعیت پروژه ثبت کن. مستندسازی به‌تنهایی هرگز به معنی تکمیل قابلیت نیست. `master` تغییر نکند.


## 2026-11-01 — Project Management review workflow implementation checkpoint

- Initial implementation committed on `development`: four employee request actions (`pm_request_refer/project/reject/return`) and two manager decisions (`pm_manager_approve_request`, `pm_manager_reject_request`).
- Employee requests require a non-empty note, are restricted to the currently assigned employee in the configured Project Management department, and route only to that department's manager. Employee referral target is stored in action metadata; it is not routed until manager approval.
- Manager rejection returns the letter to the same requesting employee. Manager approval executes the requested branch: referral to selected department, return/reject to the original recipient department, or `project_request_approved` as the handoff to the project-creation phase. The approved-project state does not create a project yet.
- New pending states are included in the manager cartable and correspondence needs-action list. UI actions are shown from server-computed assignment/department-role conditions.
- Two focused HTTP tests were added. **Owner validation received 2026-11-01: tests passed and frontend build succeeded; Stage 1 is accepted.** The next work after validation is stage 2: create project with editable response-department selection.


## 2026-11-01 — Validation and next step

- Owner confirmed focused tests pass and frontend build succeeds for Stage 1; treat it as accepted.
- Next: implement Stage 2 project creation from `project_request_approved`, editable response-department selection with configurable organization default, and source-letter linkage. Then Stage 3 provisions exactly one response Section and response task for that department; Stage 4 adds bidirectional detail navigation.


## 2026-11-01 — Stage 2/3 implementation checkpoint

- Initial code path exists for creating a project from a registered incoming correspondence in project_request_approved. It is restricted to the Project Management department manager with projects.create.
- The transaction creates a project, exactly one response Section, and a response task using existing CreateProjectAction and CreateTaskAction; the source letter receives project_id and task_id, and a project_created history action is recorded.
- Explicit response department selection is in the correspondence detail UI. Configurable organization default is wired through Organization Settings and pre-fills the form, while remaining editable. No local migration/test/build validation has been performed for this slice; do not mark stages 2/3 accepted.
- Remaining: configurable default and prefill; show correspondence/project/task links from both project and task details; show correspondence/project/task links from both project and task details; add focused authorization/rollback/SQL Server tests.


## 2026-11-01 — تأیید build و تست مکاتبات برای مرحله ۴

- مالک پروژه اعلام کرد پس از دریافت آخرین تغییرات مرحله ۴، دستور `php artisan test --filter=CorrespondenceHttpTest` پاس شده و `npm run build` نیز موفق بوده است.
- این تأیید، صحت تست‌های موجود مکاتبات و build فرانت‌اند را پوشش می‌دهد؛ اما به معنی اجرای تست اختصاصی کنترل محرمانگی در صفحات جزئیات پروژه/تسک، تست مسیر ایجاد پروژه یا اعتبارسنجی migration روی SQL Server نیست.
- مرحله ۴ از نظر پیاده‌سازی نمایش ارتباط نامه مبدأ در جزئیات پروژه و مکاتبات مرتبط در جزئیات تسک، و انتقال ارتباط پروژه/نامه مبدأ به نامه پاسخ، پیاده‌سازی شده است؛ **اعتبارسنجی اختصاصی روابط و محرمانگی هنوز در انتظار است**.
- مراحل ۲ و ۳ نیز همچنان به تست مسیر ایجاد پروژه، انتخاب واحد پاسخ‌گو، ساخت Section/Task و اعتبارسنجی SQL Server نیاز دارند. مرحله ۵ (سناریوی انتها‌به‌انتها و پذیرش دستی) باز است.
- شاخه `development`؛ `master` بدون تغییر.


## 2026-11-01 — General correspondence workflow implementation checkpoint

- Read `CORRESPONDENCE_GENERAL_WORKFLOW.md` before changing ordinary incoming correspondence; it is distinct from the Project Management department's project-request workflow.
- Initial general employee requests now exist for no request, letter, workflow and project. Feedback is mandatory; the letter returns to the current department manager in `general_review_pending_manager`. Manager rejection returns it to the same employee with a required note. Project requests can be referred by the manager to the configured active Project Management department.
- Existing task creation is reused: correspondence detail opens `tasks.index` with the general project, matching department Section and correspondence context preselected. Task store validates the manager/current department/default project/Section and links the created task to the source letter. No parallel task feature.
- Still incomplete: manager handling for letter requests (manager creates a letter or delegates draft to the same employee), real workflow-request execution, and full acceptance tests. The task-link feature has not yet been test-run. Do not claim build/tests passed.
- Keep `master` unchanged.


## 2026-11-01 — General correspondence workflow follow-up

- General employee request types now route to the current department manager with mandatory feedback. Manager rejection returns to the same employee; project request can be routed to the configured active Project Management department.
- Letter request: manager may initiate letter creation or delegate draft preparation to the same employee. The existing Correspondence create form is opened with the source letter preselected; the new draft's `reply_to_id` links it to the source and the source returns to the manager after draft creation. Employee delegation requires `correspondence.create`.
- Workflow request: manager selects an active `WorkflowDefinition`; existing `WorkflowService::start` creates the real `WorkflowInstance` and step tasks. The instance ID is attached to the correspondence and visible step tasks are linked from its detail page.
- Manager task instruction reuses the existing Tasks form in the organization's general project and matching department Section; backend validates context and attaches the created task to the correspondence.
- Focused Feature tests were added for general request/rejection, delegated letter draft, and workflow instance creation. They have **not** been run; no build validation has been run. The task-instruction linkage test, full regression, SQL Server and build validation remain pending.


- Workflow task navigation is bidirectional: correspondence detail lists only workflow tasks the current user can view; task detail resolves linked correspondence through WorkflowInstance and filters each letter with `CorrespondenceAccessService::canView`.


- Workflow completion updates linked source correspondence to `workflow_completed`. Confidential correspondence visibility includes the creator/current assignee of directly linked tasks and the assignees/creators of tasks belonging to its WorkflowInstance; both direct checks and visibility queries enforce this.
- Feature coverage was added for confidential workflow-task access and completion propagation; tests have not been run.


- Once the requested reply letter is finally registered, the source correspondence is marked `letter_registered` and locked from further workflow actions. `workflow_completed`, `letter_draft_pending_manager`, and `letter_registered` are backend-protected from new actions.


- Regression tests added for correspondence-linked task creation in the organization's general project/department Section, request/rejection, delegated letter draft and final registration, real WorkflowInstance start/completion, and confidential workflow task-assignee access. None have been run; build/SQL Server validation remains pending.


- Task form options now include the shared general project and matching Sections for department managers with `tasks.create`, including Project-scoped permission assignments. The integration test asserts this option and the resulting task-letter link; it has not been run.


- A delegated employee's draft is visible to the source-letter manager while the source is in `letter_draft_pending_manager`, using the explicit reply relationship; ordinary draft privacy remains unchanged for unrelated users. A focused test was added but not run.


## 2026-11-01 — Final implementation checkpoint for general correspondence workflow

- State map is documented in `CORRESPONDENCE_GENERAL_WORKFLOW.md`: `general_review_pending_manager`, `letter_creation_by_manager`, `letter_draft_pending_employee`, `letter_draft_pending_manager`, `letter_registered`, `workflow_started`, and `workflow_completed`.
- Reply drafts must remain in the source department, are visible to the responsible source manager through the reply link, and close the source request only when the exact requested draft is finally registered.
- Active workflow choices are filtered to definitions with active steps and no project-only/unsupported actors because this correspondence workflow starts without a project.
- Task form options include the shared general project/department Section for managers with project-scoped task-create permission. Task and workflow correspondence links are bidirectional and confidentiality-filtered.
- Latest code and docs are committed to `development`; tests/build have not been executed. `master` remains untouched.

## 2026-11-01 — Fix incoming correspondence service test fixture

- `CorrespondenceService::createDraft()` intentionally requires an incoming letter's original `letter_number`; the failing test created an incoming draft without that required field. Updated the test fixture to provide `IN-1405-001` and assert that exact source number is persisted. Production validation is unchanged.
- User-reported suite result before this fix: 1 failed, 454 passed (1,921 assertions). The fix has been committed to `development`; rerun the targeted test and full suite to verify. No claim that tests were run by the assistant. `master` unchanged.

## 2026-11-01 — Fix manager access to shared task project form

- Root cause: `TaskController@index` only supplies `formOptions` when `TaskPolicy::create($user, null)` succeeds. Project-scoped department managers were denied at the null-project check even though `TaskFormOptionsService` deliberately exposes the shared default project and their enabled department sections.
- Updated `TaskPolicy::create`: project-scoped managers can open the task form only when they manage an active department with `manager_can_create_tasks=true` in the active shared system-default project. For a specified project ID, creation is permitted only for that shared default project under the same department-section condition; all other project scope checks remain unchanged. Correspondence store still validates the exact selected section against the correspondence's current department.
- Added regression coverage via the existing `manager creates general task from correspondence and links it` feature test. User-reported pre-fix result: 1 failed, 459 passed (2,002 assertions). Assistant has not executed tests/build; verification remains pending. `master` unchanged.


## یادداشت ادامه توسعه — مسیرهای مکاتبات (2026-11-01)

آخرین تغییرات روی development:
- action جدید employee_request_task برای درخواست دستورکار توسط کارمند؛ این action با employee_request_workflow متفاوت است.
- action جدید manager_create_task تصمیم مدیر برای پذیرش درخواست دستورکار است. نامه در in_progress و در اختیار مدیر می‌ماند تا لینک فرم موجود Tasks در پروژه عمومی سازمان و Section واحد فعال شود. ذخیره Task همچنان از TaskController و CorrespondenceWorkflowService::attachTaskInstruction انجام می‌شود.
- general_review_pending_manager در شاخه مدیرِ فیلتر needs_action اضافه شد.
- manager_recall_referral برای پس گرفتن ارجاع کارمند پیش از هر اقدام او اضافه شد. ارجاع فعال باید آخرین ارجاع مدیر به همان کارمند در همان واحد باشد؛ پس گرفتن با دلیل اجباری و ثبت تاریخچه انجام می‌شود.
- ارجاع مجدد عمومی در state assigned_to_employee ممنوع شد؛ مدیر باید ابتدا ارجاع را پس بگیرد یا منتظر ثبت نتیجه کارمند بماند.
- مدیر مدیریت پروژه‌ها اجازه ارجاع عمومی بین‌واحدی ندارد؛ این مسیر فقط از PM employee request + PM manager approval عبور می‌کند. ارجاع مدیر به کارمند داخل همان واحد مجاز است.
- تست‌های جدید: test_manager_can_recall_an_unacted_employee_referral, test_employee_can_request_a_shared_project_task_and_manager_can_open_existing_task_form, test_manager_sees_employee_request_in_needs_action_filter.
- **عدم ادعای اعتبارسنجی:** هیچ‌یک از تست‌ها و build توسط دستیار اجرا نشده‌اند؛ پس از pull باید اجرا شوند. وضعیت SQL Server نیز pending است.
- فیلتر scope=mine برای مدیر واحد علاوه بر ثبت‌های شخصی، نامه‌های ثبت‌شده توسط اعضای واحد تحت مدیریت او را نیز نگه می‌دارد؛ پس از ارجاع نامه به واحد گیرنده، این مورد در action_filter=all قابل مشاهده است اما نباید صرفاً به دلیل ثبت توسط تیم دبیرخانه در needs_action مدیر دبیرخانه ظاهر شود.
- پذیرش employee_request_task در UI و backend به مجوزهای tasks.create/tasks.view و وجود پروژه عمومی فعال بدون department_id و Section فعال واحد با manager_can_create_tasks=true وابسته است؛ در غیر این صورت پیام تنظیمات ناقص نمایش داده می‌شود و state نامه تغییر نمی‌کند.
- برای مدیر واحد مدیریت پروژه‌ها، target_type ارجاع عمومی به صورت پیش‌فرض user است و گزینه واحد دیگر پنهان شده؛ انتقال بین‌واحدی فقط با PM_REQUEST_REFER + PM_MANAGER_APPROVE_REQUEST ممکن است.

## 2026-11-02 — Incoming approval and approved project request queue fix

- Incoming draft creation previously ignored `correspondence.approval.incoming`; it always created `awaiting_registration` with no current user. Updated the service to use `awaiting_manager_approval` and the configured Secretariat manager when approval is required; a missing manager is now a clear validation error.
- The assigned manager can see the draft and the UI selects `manager_approve` by default. Backend manager approval accepts incoming letters only when the organization setting requires approval. Once approved, the draft returns to its creator for final Secretariat registration. Numbering now rejects final registration of any type requiring approval until state is `approved_for_registration`.
- Added `project_request_approved` to the manager branch of the `needs_action` filter so the manager responsible for creating the approved project receives the item in their action queue.
- Feature test coverage added/updated. No tests or build were executed by the assistant; local verification remains pending. Work is on `development`; `master` unchanged.


## اصلاح نهایی ثبت دبیرخانه — 2026-11-02

- برای پیش‌نویس‌های مکاتبات، مرحله تأیید مدیر قبل از ثبت نهایی وجود ندارد؛ وضعیت اولیه همه پیش‌نویس‌ها `awaiting_registration` است.
- کارمند دبیرخانه پیش‌نویس را ایجاد می‌کند. فقط مدیر واحد دبیرخانه (یا مدیرکل سیستم) مجاز به ثبت نهایی آن است؛ عضویت عادی در دبیرخانه برای ثبت نهایی کافی نیست.
- دکمه ثبت نهایی در صفحه جزئیات بر اساس همین مجوز سرور نمایش داده می‌شود و درخواست POST ثبت نهایی مستقیماً شماره ثبت را تخصیص می‌دهد و وضعیت نامه را به ثبت‌شده تغییر می‌دهد.
- گزینه اقدام جداگانه `manager_approve` از رابط کاربری حذف شده است. ثبت داخلی توسط ایجادکننده مطابق رفتار قبلی حفظ می‌شود.
- تست HTTP مسیر وارده اکنون بررسی می‌کند که کارمند دبیرخانه نتواند ثبت نهایی کند و مدیر دبیرخانه بتواند همان پیش‌نویس را ثبت کند.


## 2026-11-02 — اصلاح واژگان درخواست دستورکار

- در سناریوی مکاتبات، درخواست کارمند برای ایجاد کار قابل پیگیری باید «درخواست ایجاد دستورکار» باشد؛ این سناریو به تعریف یا انتخاب WorkflowDefinition نیاز ندارد.
- گزینه «درخواست ثبت گردش کار»، اقدام مدیر «شروع گردش کار درخواستی»، فهرست انتخاب Workflow و پیام نبود Workflow از فرم جزئیات مکاتبه حذف شدند.
- اقدام مدیر برای درخواست دستورکار به همان قابلیت Tasks و پروژه عمومی سازمان/بخش واحد متصل است. مدیر پس از بررسی درخواست، گزینه ایجاد دستورکار را انتخاب می‌کند و از فرم موجود Tasks دستورکار را ایجاد و به نامه متصل می‌کند.
- برای سازگاری با داده‌های قبلی، رکوردهای تاریخی `employee_request_workflow` نیز در بررسی مدیر به‌عنوان درخواست دستورکار شناخته می‌شوند؛ دیگر مسیر شروع Workflow از این درخواست در دسترس نیست.
- Workflowهای واقعی ماژول مربوطه و اتصال‌های مستقل آن‌ها به‌عنوان قابلیت جداگانه دست‌کاری نشده‌اند.
- تست قدیمی‌ای که درخواست نامه را به شروع Workflow واقعی وصل می‌کرد حذف شد؛ تست‌های مسیر دستورکار و بررسی کامل مجموعه تست‌ها/build باید در محیط توسعه اجرا شوند. وضعیت اعتبارسنجی: در انتظار.

### 2026-11-02 — حذف پروژه‌های عمومی قدیمی واحدها

- تصمیم نهایی معماری: فقط یک «پروژه عمومی سازمان» با `department_id = NULL` و بخش مجزای هر واحد در `project_sections` باقی می‌ماند.
- چهار پروژه قدیمی عمومیِ واحدی (طراحی، تولید، دبیرخانه و مالی) در دیتابیس توسعه، شناسه‌های ۲ تا ۵، غیرفعال و بدون دستورکار و بدون بخش گزارش شدند.
- Migration جدید `2026_11_02_120000_remove_legacy_department_default_projects` برای حذف پروژه‌های عمومی سیستمی دارای `department_id` اضافه شد. قبل از حذف، ارجاع‌های دستورکار، بخش، گردش‌کار، مکاتبه، سند و فیلد سفارشی را بررسی می‌کند؛ در صورت وجود وابستگی، عملیات را با خطای روشن متوقف می‌کند. عضویت‌ها و نقش‌های وابسته نیز پیش از حذف پروژه پاک می‌شوند.
- این Migration هنوز در محیط کاربر اجرا نشده است؛ اجرای آن با `php artisan migrate` انجام می‌شود. تست و build پس از pull باید در محیط توسعه اجرا و نتیجه گزارش شود.
- پروژه عمومی سازمان و بخش‌های واحدهای آن هدف این پاک‌سازی نیستند.


## 2026-11-03 — همگام‌سازی چرخه عمر واحدها با بخش پروژه عمومی

- ایجاد هر واحد همچنان در همان تراکنش، بخش متناظر آن را در تنها پروژه عمومی سازمان ایجاد می‌کند.
- عملیات Ensure اکنون بخش موجود را نیز همگام می‌کند: نام و توضیح بخش از نام جاری واحد گرفته می‌شود و قابلیت ایجاد دستورکار فقط وقتی فعال است که واحد و مدیر آن فعال باشند.
- پس از ویرایش واحد، از جمله تغییر نام یا مدیر، بخش پروژه عمومی همگام می‌شود. مسئول بخش از رابطه مستقیم با واحد و مدیر جاری آن به دست می‌آید؛ فیلد مدیر تکراری در ProjectSection وجود ندارد.
- با غیرفعال‌شدن واحد، امکان ایجاد دستورکار از بخش آن غیرفعال می‌شود؛ با فعال‌سازی مجدد، در صورت داشتن مدیر فعال دوباره فعال خواهد شد.
- حذف واحد: اگر بخش متناظر در پروژه عمومی دستورکار یا سند داشته باشد، حذف واحد با پیام روشن متوقف می‌شود تا سوابق از بین نرود. اگر بخش خالی باشد، همان بخش پیش از حذف واحد پاک می‌شود. محدودیت‌های قبلی حذف واحد (از جمله داشتن مدیر یا زیرواحد) همچنان برقرارند.
- اعتبارسنجی تست و build توسط دستیار اجرا نشده؛ پس از pull باید تست‌های واحدها/پروژه‌ها و build اجرا شوند. شاخه development؛ master بدون تغییر.


## 2026-11-03 — مدیریت پروژه عمومی و بخش‌های سازمانی

- ادمین اصلی مجاز به ویرایش پروژه عمومی سازمان است؛ حذف پروژه عمومی برای همه نقش‌ها ممنوع باقی می‌ماند. کاربران غیرادمین نمی‌توانند پروژه عمومی را ویرایش کنند.
- مسیرهای edit/update پروژه و CRUD بخش‌ها به‌جای middleware مجوز ثابت، به کنترل Policy در کنترلر متکی‌اند؛ کنترل دسترسی سرور حذف نشده است.
- در پروژه عمومی، ایجاد/حذف دستی بخش و تغییر واحد متصل به بخش ممنوع است. بخش‌ها از واحدهای سازمانی مشتق می‌شوند؛ نام بخش از نام واحد می‌آید.
- تنظیمات توضیحات و manager_can_create_tasks برای بخش موجود قابل ویرایش است. Ensure نام را sync می‌کند و توضیحات دستی را حفظ می‌کند. غیرفعال بودن واحد/مدیر، قابلیت ایجاد دستورکار را خاموش می‌کند؛ پس از فعال‌سازی مجدد، ادمین می‌تواند آن را دوباره فعال کند.
- برای داده‌های قبلی: php artisan projects:sync-organization-sections را اجرا کنید. فرمان برای همه واحدها بخش متناظر را ایجاد/همگام می‌کند و بخش‌های تکراری را فقط گزارش می‌کند؛ حذف خودکار ندارد.
- تست و build هنوز اجرا نشده‌اند؛ نتیجه باید پس از pull و اجرای محلی ثبت شود. شاخه development، master دست‌نخورده.


## 2026-11-03 — کنترل مرحله ایجاد دستورکار در گردش نامه

- در سناریوی درخواست دستورکار، پس از تأیید مدیر، فرم اقدامات عادی مدیر تا زمان ایجاد و اتصال دستورکار پنهان و در سمت سرور مسدود است.
- پس از اتصال دستورکار تأییدشده، مدیر می‌تواند نامه را ارجاع دهد یا با اقدام مستقل «خاتمه گردش نامه» و ثبت توضیح، گردش را با وضعیت `workflow_completed` ببندد.
- خاتمه فقط وقتی مجاز است که شناسه تسک متصل با metadata اقدام ایجاد دستورکار مدیر مطابقت داشته باشد.
- تست Feature برای این توالی توسعه داده شده؛ نتیجه اجرای واقعی تست و build هنوز گزارش نشده است.

## 2026-11-03 — Launch task creation form from correspondence

- Fixed the approved correspondence «ایجاد دستورکار» destination: the Tasks index now resolves and validates the correspondence, latest employee task request, manager approval action, current department manager, active organization project section, and section task-creation setting on the server.
- The validated creation context is sent as an Inertia prop; the Vue page opens the create modal and preselects correspondence, shared project, section, and department from that prop instead of relying on client-side URL parsing.
- If the manager's normal task-form options omit the authorized shared project/section, the server adds only the validated target project/section to the form options. Unauthorized or stale query parameters do not open the modal. Existing task-store authorization and correspondence-linking checks remain in place.
- Extended the correspondence feature test to request the Tasks page with the three launch query parameters and assert the validated launch context and target section are present. Validation pending: run `php artisan test --filter=test_manager_creates_general_task_from_correspondence_and_links_it` and `npm run build`; `master` unchanged.


## 2026-11-03 — اصلاح مجوز ایجاد دستورکار از مسیر نامه

- علت ریشه‌ای باز نشدن مودال مشخص شد: صفحه Tasks مجوز `tasks.create` را فقط بدون شناسه پروژه بررسی می‌کرد. در `TaskPolicy` این بررسی عمومی برای برخی مدیران واحد با scope سطح واحد false است، در حالی که همان مدیر برای بخش مجاز پروژه عمومی مجوز ایجاد دارد.
- در `TaskController::index` اکنون علاوه بر مجوز عمومی، مجوز ایجاد با شناسه پروژه مقصد نیز ارزیابی می‌شود. بنابراین مدیر مجاز می‌تواند فرم را دریافت کند و prop مربوط به نامه به Vue برسد؛ اعتبارسنجی نامه، تأیید مدیر و بخش مقصد همچنان برقرار است.
- تست و build در محیط کاربر پیش‌تر پاس شده‌اند؛ این اصلاح جدید هنوز باید پس از pull با همان تست Feature و build مجدداً اعتبارسنجی شود. شاخه development؛ master بدون تغییر.

## Incoming correspondence validation
- Source organization is mandatory for incoming letters in create/update validation and UI.
- Incoming letter number uniqueness is scoped to incoming correspondence plus source organization: duplicates within one organization are rejected; same number across different organizations is allowed.
- Tests/build for this change are pending.

- اصلاح تست‌های Feature برای الزام سازمان مبدأ در نامه‌های وارده: داده تست‌های قدیمی تکمیل شد؛ تست مستقل نیز اجباری بودن سازمان، رد شماره تکراری در همان سازمان و پذیرش همان شماره در سازمان دیگر را پوشش می‌دهد. اجرای تست‌ها پس از دریافت این commit لازم است.

## 2026-11-03 — Correspondence list and validation refinements

- Incoming duplicate letter-number validation now returns a Persian message in both create and edit flows.
- Required-field markers follow the shared CSS convention: do not add a manual asterisk when the required control is already detected by the label selector. The external organization field uses only the shared marker.
- The department sentinel in the “my correspondence” scope is labeled “همه واحدهای من”. After deletion, the current list filters are re-applied.
- Secretariat managers must see incoming Secretariat drafts awaiting registration/approval in “my correspondence” and “needs action”; this is limited to the active configured Secretariat manager and does not broaden access for other users.
- Validation pending: focused correspondence Feature tests and frontend build must be run after pulling `development`. `master` unchanged.
- Root cause follow-up: Secretariat draft visibility now recognizes the configured active Secretariat department manager even when the manager is not separately attached as a department member. This aligns `isSecretariat()` visibility with `canRegister()` authorization.



## 2026-11-04 — Phase 3 technical audit checkpoint

Audited branch `development` before implementation. Existing Core organization settings already contain organization name, short name, identifiers, address, phone, email and website, backed by `SystemSettingsService`; reuse these settings as independently enabled/disabled letterhead data. `Department` has `manager_id` but no signature asset/path or upload flow. Organization settings have no stamp asset/path or upload flow. Correspondence detail has no print/PDF route or print-specific template; `Correspondence/Show.vue` is an operational detail/workflow page. The composer manifest contains no PDF renderer. The Documents module has a storage contract and disk/path version metadata, and its download controller authorizes file access. Profile avatars use the public disk; do not copy that access model blindly to stamp/signature assets.

No application code, migrations, dependencies, master/release branch or server were changed during this audit. Before implementing the shared A4 output, obtain the product owner's decision between browser-native Print/Save as PDF using a shared HTML/CSS template (no server-side renderer) and a server-generated downloadable PDF (requires selecting/adding a renderer). Preserve the requirement that the stamp and department signature remain separate assets and that finalized documents retain immutable identity/asset snapshots.


## 2026-11-04 — Phase 3 implementation slice 1

Selected output architecture: shared HTML/CSS A4 print view using browser-native Print / Save as PDF; no server PDF package. Department manager signature belongs to the Department, not User; a department manager change deactivates the previous signature until replaced. Organization stamp is an independent Core organization setting. Both images are validated, stored on the private local disk, delivered through authenticated endpoints and snapshotted at outgoing-letter finalization. Creator name, issuing unit title and organization contact details are also snapshotted. The department signature is copied into the immutable snapshot only for the `approved_for_registration` workflow state, so direct registration does not imply manager approval.

Added `CorrespondencePrintController`, `Correspondence/Print.vue`, protected print route, department signature upload/configuration, organization stamp upload/configuration, independent output visibility settings and regression tests. The print page offers a per-operation letterhead toggle and CSS A4 page counters. Phase 3 remains partial: local tests/build and manual pagination checks are pending; logo/background assets, detailed coordinate controls and configurable page-number placement remain outstanding. No new PDF dependency; development branch only; no master/deploy.


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


## 2026-11-08 — Print preview department signature loading fix

- Root cause of the failing `signatureDataUri` assertion: `createdDepartment` was eager-loaded with a restricted projection (`id,name`), so the controller's later fallback could not read `manager_signature_path` or `manager_signature_active` from the already-loaded model.
- Expanded the relation projection to include both signature fields. Snapshot-first behavior and fallback conditions are unchanged. The existing protected A4 print-preview feature test covers both stamp and signature data URIs.
- Local focused test/build verification is pending. Development only; master and production server untouched.


## 2026-11-08 — Print asset sizing, overlap spacing, and manager position tokens

- Stamp/signature image rendering now follows the configured element width and uses automatic height with the source aspect ratio preserved; fixed max-width/max-height caps were removed from the print asset style and the stamp designer preview.
- Issuer-flow spacing accepts -80..80 mm, allowing deliberate overlap between the stamp/signature and issuer identity. The same margin-based spacing is used in designer preview and print output.
- Added `{{manager_name}}` and `{{manager_position}}` template tokens. The print controller resolves the manager's active position assignment scoped to the issuing department, preferring the primary assignment. Missing position data resolves to an empty value rather than an inferred title.
- Extended the protected print-preview regression test to assert manager name and department-specific position. Local PHP tests and frontend build remain pending; development only, master/server untouched.

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

## 2026-11-09 — Single-origin correspondence print layout

- Reworked the print page so the CSS paged-media box owns only the effective vertical safe zones: top = max(text top margin, header height), bottom = max(text bottom margin, footer height). Browser/page CSS no longer adds a second left/right margin layer.
- The print page now uses a full-width 210 mm A4 coordinate plane from the physical horizontal origin. The body flow box is also 210 mm wide and applies the configured left/right text margins as padding, keeping the physical x-coordinate system consistent with the screen A4 preview.
- The positioned header/footer/repeat plane is shifted vertically from the @page content origin to the physical A4 top. The body remains in normal document flow inside the vertical safe area, preserving natural browser pagination and first-page measured spacing after metadata/heading.
- Removed the previous print-only right-offset compensation. The page layout's left/right values are now applied once as body padding; header/footer heights are represented once by @page top/bottom margins.
- This is an implementation step, not acceptance: run frontend build, focused CorrespondenceHttpTest, then compare the same letter in screen preview and Chromium PDF using non-symmetric margins and distinct header/footer heights. Confirm first and subsequent pages, repeated elements, body start and page counters. Development only; master/release/server unchanged; all validation pending.

## 2026-11-09 — Outgoing dispatch stage and recipient title prefix

- The existing external tracking columns are reused; no duplicate schema was introduced. Outgoing letters in `awaiting_dispatch` are now included in the `needs_action` state filter for all active Secretariat members, fixing the hand-off that previously made the newly registered outgoing letter disappear from the actionable list.
- Final dispatch now requires both a dispatch method and a sent date while the letter is in `awaiting_dispatch`. The workflow service independently enforces the same invariant before recording `dispatched`; external registration number/date remain optional tracking metadata. While awaiting dispatch, only Secretariat members/system administrators may edit this stage; creator/task-owner tracking permissions resume outside that state.
- Added `{{recipient_organizational_title_prefix}}` to the addressing element token allow-list, designer token catalog/sample values, and print resolver. It reads the immutable `organizational_title_prefix` captured from the selected external contact at registration.
- Frontend build, focused correspondence tests, and manual Secretariat workflow verification are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Recipient personal title prefix token

- Added `{{recipient_personal_title_prefix}}` to the addressing/heading element token catalog. It resolves from the external contact's immutable `recipient_addressing_snapshot.personal_salutation` value in print, independently of `{{recipient_title}}` (which continues to combine personal salutation and professional title for backward compatibility).
- No database migration is needed; the personal salutation is already captured in the recipient addressing snapshot. Build and correspondence regression tests are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Word-like correspondence editor and structured appendices (implementation in progress)

- Added a reusable Vue rich-text editor component with Home and Insert tabs plus contextual Table, Picture, and Shape tabs. Formatting and insertion use DOM Selection/Range APIs; the legacy `document.execCommand` toolbar has been removed.
- The editor supports font family/size, bold/italic/underline/strike, text and highlight colors, paragraph alignment/direction, line height, paragraph spacing, indentation, lists, undo/redo, clear formatting, tables with row/column operations, raster image insertion/resizing/alignment, basic shapes, and safe hyperlinks.
- Inline images are limited to PNG/JPEG/WEBP and 180 KB per image. The server sanitizer uses a tag/attribute/style allowlist, accepts only raster data URLs for images, and restricts hyperlinks to HTTP(S)/mailto. Main and appendix rich-text request limits are 500 KB.
- Added `appendix_mode` (`none|editor|file|both`) and `appendix_body` to correspondence records. Supporting appendix uploads use the distinct document purpose `correspondence_appendix`; existing main-letter uploads and incoming originals retain their existing purposes. The legacy `attachment_pages` field remains for backward compatibility but is no longer an input in the form or the source of the print summary.
- Print preview now separates main-letter pages from text-appendix pages. Main-letter issuer/signature assets remain after the main body and before the appendix. Appendix sheets use independent numbering, while existing A4 sheet printing, letterhead, and repeating elements remain shared.
- The print template token `{{attachment_pages}}` now resolves to an automatic summary such as “پیوست: ۳ برگ + ۲ فایل”. Text page count is measured from rendered appendix content; file count is based on linked appendix documents.
- Added feature regression tests for text-only and combined text/file appendix creation. Build, PHP tests, and browser/PDF acceptance have not yet been run. Continue to validate the pagination estimator against actual Chromium PDF output; do not restore browser-native repagination.


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

## 2026-11-11 — Word-like editor refinement (code-level, unverified)

- Replaced the always-visible text/highlight swatch strips with compact dropdown palettes containing theme colors, standard colors, custom color input, and clear/no-color where applicable. Palette trigger previews the current effective color; table border, image border, shape fill and shape outline now use palette controls rather than browser-native color inputs alone.
- Added pointer-drag rectangular table-cell selection without requiring Shift-click, while retaining the existing Shift-click path. Added table alignment controls (left/center/right) and icon-first controls for table selection/merge/split operations.
- Added explicit image/shape selected-state outlines, image aspect-ratio metadata and paired width/height behavior, rotation and horizontal/vertical flip controls. Added shape outline-width input and a custom right-click menu for text, table, image and shape contexts.
- Raised the inline image client-side cap from 180 KB to 350 KB. This remains constrained by the correspondence request/body limits and sanitizer; verify the full encoded data URL round-trip before increasing further.
- Important: these are code-level changes only. Transform preservation through server sanitization, selection behavior, context-menu clipboard actions, image dimensions after load, editor toolbar appearance, save/reopen, and A4 preview/print remain unverified. No build, automated tests or browser acceptance has been run. Development only; master/release/server unchanged.

## 2026-11-11 — TypeScript correction
Fixed Vue `ref` access in table pointer-drag selection: assignments and reads now consistently use `dragSelection.value`. This addresses the reported TS2588/TS2339 errors at the drag-selection handlers. The correction is committed to `development`; build has not yet been rerun and no build success is claimed.


## 2026-11-11 — Editor controls follow-up from owner feedback

- Added document-level outside-pointer handling to close an open color palette when the user clicks outside it; clicks inside the palette do not close it prematurely.
- Changed clear/no-color behavior to remove the corresponding inline fill/border declarations where appropriate; table border no-color also disables the selected border side style, and shape outline exposes a no-color action.
- Replaced the vague paragraph-spacing choices with explicit numeric controls: line-height multiplier (1.00–3.00, step 0.05) and paragraph-after spacing in pixels (0–60).
- Dimension fields now read the selected element's rendered width/height in millimetres rather than showing unrelated hard-coded defaults. Table height is now separately editable. Image ratio preservation is an explicit checkbox and defaults off; when enabled, changing one dimension calculates the other. Intrinsic image ratio is refreshed after the image loads.
- These are implementation changes only. Production build, browser interactions, no-color behavior, dimensions, and persistence remain unverified. Development only; master/release/server unchanged.


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
