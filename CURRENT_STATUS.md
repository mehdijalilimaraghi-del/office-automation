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

## 2026-11-09 — Preserve full A4 coordinate plane in browser print

- Found that Chromium's print `.paper` box represents only the printable area after `@page` margins, while the template's percentage-based absolute elements must remain relative to the full physical A4 page. The previous print CSS left the coordinate plane at `width:auto`, compressing its width to the printable area and distorting x/width positions when left/right margins were nonzero.
- Print CSS now restores a 210 × 297 mm coordinate plane and offsets it by the configured right and effective top margins. The flowing body remains inside the `@page` printable rectangle.
- Pending frontend build, focused PHP tests, and Chromium PDF validation including browser margin setting behavior. Development only; master/release/server unchanged.

## 2026-11-09 — Align first-page template elements between screen preview and print

- Root cause: screen A4 coordinates start at the physical page top; in print, the `.paper` containing block starts at the `@page` content edge after the top margin. Absolute first-page meta/heading elements were consequently shifted down by the reserved top margin while the body stayed at the printable content edge, causing overlap and spacing mismatch.
- Print CSS now shifts the absolute coordinate plane upward by the effective content-top margin without moving the flowing body. The screen preview is unchanged. Build, focused PHP tests, and Chromium print/PDF acceptance remain pending.
- Development only; master, release and server unchanged.

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

## 2026-11-07 — Template positioning and preview follow-up

- Designer now exposes top and right coordinates for all element roles, not only repeating text; the A4 canvas renders elements at their selected coordinates and supports pointer dragging.
- Added an explicit preview mode that hides the settings sidebar while showing the A4 template canvas.
- Print rendering uses template coordinates for first-page metadata and heading elements; issuer/signature/stamp remain in document flow after the body and use configured horizontal/vertical offsets, so they are not repeated on subsequent pages.
- Page-number element uses the configured position and CSS paged-media page counters (counter(page) and counter(pages)) rather than fixed literal numbering. Browser-specific correctness across multi-page print output still requires Chromium print/PDF acceptance.
- Added Persian translation for Laravel image dimensions validation failures and Persian field labels for letterhead/stamp uploads.
- Template update validation now checks position/width bounds for every element role.
- Local PHP tests, TypeScript check, frontend build, and real multi-page print acceptance have not been run in this GitHub-only edit session. Development branch only; master/server unchanged.

## 2026-11-04 — Flow-based template preview

- A4 designer preview now shows metadata, addressee/subject, automatic body placeholder, issuer/signature and page-number indicator in document flow rather than hard-coded Y positions. Only repeating elements retain independent page coordinates.
- Preview shows order and approximate occupied regions; actual body length and signature position depend on letter content and pagination.
- Current development test fixture excludes obsolete `height` fields. The reported local failure still expects them, indicating a stale local test revision. Pull before rerunning.
- Local tests, TypeScript, build and multi-page print acceptance remain pending. Development only; master/server unchanged.

## 2026-11-04 — Fix semantic template persistence test

- Updated the visual-template feature fixture to match the new role-based schema: flow elements use width and spacing rather than a fixed height. The previous failure was an outdated test expectation; request validation correctly excludes the removed `height` property from persisted data.
- No production behavior change. Local focused test rerun remains pending owner confirmation. Development only; master/server unchanged.

## 2026-11-04 — Role-based Persian letter template and automatic body flow

- Reworked the A4 template editor around semantic roles: letter number/date, addressee/subject, page number, issuer/signature, and repeating text. Variable choices are filtered by the selected role.
- The organization letterhead is no longer a configurable element; it is loaded automatically as a page background in the editor and print preview.
- The letter body remains automatic, is not a template element, and is rendered from the stored HTML in normal multi-page document flow. RTL direction/alignment and whitespace/rich HTML formatting are preserved in the print body.
- Metadata and addressee/subject render once before the body; issuer/signature elements follow the body; repeating elements use fixed page coordinates; page numbering is controlled by the page-number role. Flow elements expose width and spacing, not arbitrary page Y coordinates.
- Existing stored template elements are normalized for compatibility; legacy letterhead image elements are removed from the element list because the image is now automatic. No migration or PDF dependency was added.
- Validation and physical multi-page print acceptance remain pending owner execution. Development only; master/server unchanged.

## 2026-11-04 — Template token and asset binding completion

- Template variables now include recipient organization/unit/position/title, recipient name, subject, letter type, creator name and creator unit title, in addition to organization contact details and letter numbering/date. Variables without a reliable source are not offered as selectable tokens.
- Stamp and signature image elements bind to the protected correspondence-specific snapshot assets. Legacy footer rendering remains for stamp/signature/creator identity unless the template explicitly places the corresponding element/token, avoiding accidental disappearance or duplicate output.
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

## 2026-11-04 — اصلاح نمایش عنوان مخاطب بیرونی و تفکیک تنظیمات قالب

- در فرم ایجاد/ویرایش نامه صادره، گزینه مخاطب شخصی فقط نام فرد را نشان می‌دهد؛ عناوین سازمانی، سمت، پیشوند شخصی و عنوان حرفه‌ای از مشخصات فرد خوانده یا نمایش داده نمی‌شوند. انتخاب خودکار شخص نیز فقط از مدیر تعیین‌شده در همان واحد انجام می‌شود؛ منطق قدیمی مخاطب پیش‌فرض فردی از این مسیر حذف شد.
- در فرم و فهرست اشخاص بیرونی، اطلاعات خطاب در سطح شخص وجود ندارد؛ محل تعریف چهار جزء خطاب و انتخاب مدیر، فرم واحد بیرونی است.
- بخش «اجزای قابل نمایش در خروجی نامه» از صفحه تنظیمات سازمان برداشته شد. تنظیمات عمومی همچنان محل بارگذاری/فعال‌سازی مهر و اطلاعات پایه سازمان است. کنترل چیدمان و نمایش اجزای خروجی به طراح قالب نامه تعلق دارد.
- طراحی طراح قالب هنوز پیاده‌سازی نشده است. جهت اجرا: قالب A4 با کادرهای قابل افزودن/تغییر اندازه/جابجایی، ویرایش متن داخل کادر، درج متغیرهای استاندارد با شناسه‌هایی مانند {{address}}، {{tel}}، {{email}}، {{website}}، {{organization_name}}، {{letter_number}} و {{date}}؛ پشتیبانی جداگانه از سربرگ، بدنه، گیرنده، مهر و امضا؛ پیش‌نمایش و چاپ/PDF از یک مدل واحد.
- اعتبارسنجی محلی و build پس از دریافت نتیجه از مالک پروژه باقی است. توسعه فقط روی development؛ master و سرور دست‌نخورده‌اند.

## 2026-11-04 — External unit owns manager and addressee data (implementation follow-up)

- The four addressee components are owned by `ExternalDepartment` only: organizational title prefix, organizational position, personal salutation, and professional title. This applies only to external organizational units; internal Core Department manager selection and behavior are unchanged.
- External contact create/edit UI and request validation now collect only the person's identity/contact details. Legacy contact-level addressing/default columns are retained for historical compatibility and migration fallback, but are no longer exposed as current input or list metadata.
- An external unit selects one active manager from contacts belonging to that same unit. Service-level guards prevent deactivating or moving an assigned manager until the unit manager is changed; unit update validates manager ownership.
- Outgoing finalization snapshots the unit's addressing fields and selected manager's name/ID, without requiring an individual contact selection. Legacy contact values remain a fallback for older data.
- Regression coverage added for manager assignment integrity and outgoing recipient snapshot. Local migration, focused tests, TypeScript/build and manual acceptance are still pending; do not claim validation until owner reports results. Development only; master and server untouched.

## 2026-11-04 — اصلاح مدل مخاطب بیرونی: مالکیت خطاب در سطح واحد

- برداشت قبلی اصلاح شد: اطلاعات خطاب و مدیر موردنظر کاربر برای «واحدهای سازمان بیرونی» است، نه واحدهای داخلی و نه پروفایل تک‌تک اشخاص بیرونی.
- Migration جدید `2026_11_04_140000_move_external_addressing_to_departments` به `external_departments` فیلدهای مدیر/مخاطب اصلی (`manager_contact_id`) و پیشوند عنوان سازمانی، سمت سازمانی، پیشوند شخصی و عنوان حرفه‌ای را اضافه می‌کند.
- برای حفظ داده‌های قبلی، مخاطب پیش‌فرض فعال هر واحد به‌عنوان مدیر واحد منتقل می‌شود و فیلدهای خطاب قدیمی او در ستون‌های واحد کپی می‌شوند. ستون‌های قدیمی اشخاص حذف نشده‌اند تا داده و سازگاری تاریخی از بین نرود.
- فرم واحد بیرونی محل تعریف اطلاعات خطاب و انتخاب مدیر از میان اشخاص همان واحد شده است. ورودی‌های خطاب و پیش‌فرض از فرم ثبت/ویرایش شخص برداشته شده‌اند؛ مشخصات تماس شخص همچنان در سطح شخص باقی می‌ماند.
- ثبت نهایی نامه صادره اکنون اطلاعات خطاب را ابتدا از واحد بیرونی و مدیر منتخب همان واحد snapshot می‌کند؛ انتخاب شخص مستقل اجباری نیست. اطلاعات قدیمی شخص فقط fallback سازگاری برای رکوردهای قدیمی است.
- حذف شخصی که مدیر یک واحد بیرونی است مسدود می‌ماند. پیش از تغییر وضعیت/انتقال چنین شخصی باید مدیر واحد جایگزین شود.
- کد در `development` است. Migration، تست‌های Feature، TypeScript/build و پذیرش دستی هنوز توسط مالک پروژه اجرا/تأیید نشده‌اند. `master` و سرور دست‌نخورده‌اند.
- وضعیت پنج مرحله: ۱ تست‌های متمرکز پاس؛ ۲ اصلاح مدل در حال پیاده‌سازی و اعتبارسنجی محلی در انتظار؛ ۳ پیاده‌سازی اولیه چاپ انجام شده و تکمیل چیدمان/تست باقی است؛ ۴ شروع نشده؛ ۵ در انتظار تست کامل، build و پذیرش دستی.

## 2026-11-04 — Fix correspondence controller parse error — validated

- Owner confirmed PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` all pass after pulling the fix. This validates the syntax correction and the focused correspondence/external-party regression suites; it does not represent a full-suite run, frontend build, or end-to-end acceptance.
- Root cause of the widespread HTTP 500/test failures: the `createDraft()` attribute array in `CorrespondenceController::store()` had a malformed key (`'reply_to_id => ...`) introduced while wiring multi-letter relations. PHP could not parse the controller, so unrelated routes/tests also failed when Laravel attempted to load routes/controllers.
- Corrected the key to `'reply_to_id' => ...`; retained the existing first-related-letter fallback and source-project correspondence fallback. No workflow, validation, authorization, or database behavior was changed.
- Existing `CorrespondenceHttpTest` and `ExternalPartyControllerTest` cover the affected route-loading and create/update behavior. Validation completed by the project owner: PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` passed.
- Development only. `master`, release branch, migrations, and server are untouched.

## 2026-11-04 — مرحله ۲: مخاطب بیرونی و snapshot خطاب — تست‌های متمرکز پاس شد

- در `ExternalContact` فیلدهای مستقل پیشوند عنوان سازمانی، سمت سازمانی، پیشوند خطاب شخصی و عنوان حرفه‌ای اضافه شدند.
- فیلد `is_default` و منطق سرویس برای نگهداری یک مخاطب پیش‌فرض در هر سازمان/واحد اضافه شد؛ مخاطب پیش‌فرض سازمان (بدون واحد) از پیش‌فرض واحد مستقل است. مخاطب غیرفعال نمی‌تواند پیش‌فرض باقی بماند.
- فرم مدیریت مخاطبان برای ورود اجزای خطاب و تعیین پیش‌فرض توسعه یافت.
- فرم مکاتبه صادره امکان انتخاب مخاطب شخصی اختیاری را دارد و با انتخاب سازمان/واحد، مخاطب پیش‌فرض همان محدوده را خودکار انتخاب می‌کند. مخاطب سازمانی/واحدی بدون شخص همچنان مجاز است.
- به مکاتبه رابطه مخاطب و فیلد snapshot خطاب اضافه شد. در زمان ثبت نهایی نامه صادره، اطلاعات خطاب به‌همراه نام سازمان و واحد snapshot می‌شود.
- Migration: `2026_11_04_120000_add_external_contact_addressing_and_defaults`.
- فرم ایجاد و ویرایش مخاطب هر دو اجزای خطاب و وضعیت پیش‌فرض را اعتبارسنجی و ذخیره می‌کنند.
- حذف مخاطب متصل به مکاتبه یا تسک ثبت‌شده مسدود شد تا ارجاعات تاریخی نشکنند؛ غیرفعال‌سازی همچنان ممکن است. تغییر مخاطب پیش‌فرض با قفل تراکنشی سازمان serialize می‌شود.
- تست‌های regression در `ExternalPartyControllerTest` و `CorrespondenceHttpTest` اضافه/توسعه یافتند. مالک پروژه اجرای تست‌های متمرکز `ExternalPartyControllerTest` و `CorrespondenceHttpTest` را پس از دریافت اصلاح نحوی تأیید کرد؛ تست‌های این دو مجموعه پاس شدند. این نتیجه، build فرانت‌اند یا پذیرش end-to-end را تأیید نمی‌کند.
- وضعیت پنج مرحله: ۱ تست‌های متمرکز پاس؛ ۲ پیاده‌سازی اولیه و تست‌های متمرکز پاس؛ ۳ مستند شده/شروع نشده؛ ۴ شروع نشده؛ ۵ در انتظار build و پذیرش end-to-end.
- فقط شاخه `development` تغییر کرده؛ `master` و سرور دست‌نخورده‌اند.

## 2026-11-04 — تأیید تست‌های متمرکز مرحله ۱

- مالک پروژه اعلام کرد تست‌های متمرکز `TaskReferralHttpTest` و `CorrespondenceHttpTest` پس از اصلاح مقدار نوع تخصیص تسک (`manager_accept`) پاس شده‌اند.
- مرحله ۱ از نظر تست‌های Feature متمرکز تأیید شد؛ این تأیید به معنی اجرای کل مجموعه تست‌ها، build یا پذیرش دستی end-to-end نیست. موارد اخیر در مرحله ۵ باقی می‌مانند.
- وضعیت پنج مرحله: ۱ پیاده‌سازی و تست‌های متمرکز پاس؛ ۲ شروع نشده؛ ۳ مستند شده/شروع نشده؛ ۴ شروع نشده؛ ۵ شروع نشده.
- شاخه `development`؛ `master` و سرور دست‌نخورده‌اند.

## 2026-11-04 — Creator identity, organization stamp and department signature requirements

- Added the creator/issuer identity block to the bottom of the finalized letter (organizational unit/title plus creator full name), with a finalization-time snapshot so historical letters remain unchanged.
- Added two distinct image assets: one organization-level stamp, configured under Core → Organization Settings; and one manager signature per department, configured under Core → Departments → Create/Edit Department. These are proposed configuration entry points based on the existing Core module boundaries and must be verified against the detailed settings UI before implementation.
- Added independent document-template controls for identity block, stamp and signature visibility/placement, using the shared PDF/print coordinate system. The department signature and organization stamp must not be combined into one asset; finalization must preserve the exact versions used.
- Phase 3 scope now includes the creator identity block, stamp and per-department manager signature. No application code changed; only the enhancement plan and this status document are being updated. Validation is not applicable to this documentation-only checkpoint. Development only; master/server untouched.

## 2026-11-04 — Expanded requirements and five-phase execution tracking

- User requested explicit progress reporting and documentation updates for all five implementation phases at each checkpoint.
- Expanded the shared letterhead/PDF/print requirements to include robust multipage A4 text flow, stable layout/margins, page numbering (including X of Y), and sensible pagination around headings and signature blocks.
- Added reusable outgoing-letter body templates specifically to the «روش تهیه نامه» / letter-editor path. Saved text is selectable as a starting point and remains editable for each letter; inserting a template must not silently overwrite existing text.
- Execution is tracked as five phases: (1) manager self-acceptance in correspondence/tasks, (2) external recipient defaults and addressing snapshot, (3) shared letterhead + multipage PDF/print, (4) reusable outgoing letter templates, (5) end-to-end verification.
- Phase 1 is in progress at code-path review. Phases 2–5 are not started. No new application-code changes in this documentation update; local tests/build remain unrun.

## 2026-11-04 — Approved office-automation enhancements plan

- Approved manager self-acceptance of responsibility in both Correspondence and Tasks as a distinct action, without self-referral; history and audit must be preserved.
- Approved external-recipient defaults: the department's default contact is auto-selected but remains changeable. Addressing is split into organizational title prefix (e.g. مدیریت/مدیرعامل/معاونت/ریاست), organizational position, personal salutation, professional title and name. Organizational-only addressees remain supported. Finalized letters must retain an addressing snapshot.
- Approved one shared correspondence template/coordinate system for PDF and physical printing, with per-output choice of letterhead or no letterhead. Address, phone, email, website and other company details are optional independently configurable letterhead elements.
- Detailed scope and staged acceptance criteria recorded in `docs/OFFICE_AUTOMATION_ENHANCEMENTS.md`. Stage 0 complete; implementation stages 1–4 not started. Development only; master/server untouched. No application code changed in this planning commit.

## 2026-11-04 — Direct correspondence registration follow-up

- Fixed the create-page contract: `Correspondence/Create` now receives the per-type `approvalSettings` used by its submit label and explanatory text. Previously this prop was omitted on the create action (although it was supplied by the edit action), so a type configured for direct registration could still display «ذخیره پیش‌نویس».
- Made the server's immediate-registration decision explicitly use the same `CorrespondenceCompositionSettings::requiresApproval(type)` policy instead of inferring the decision from the created record's workflow state. When approval is disabled for the selected type, the create request invokes numbering/final registration immediately; approval-enabled types remain drafts assigned for approval. Reply/request-generated correspondence remains on its dedicated workflow.
- Added an HTTP regression test asserting the create page receives the direct setting and a direct internal letter is persisted as registered in the initial create request, not as a draft.
- Validation pending on the project owner's machine. Development only; master and server untouched.

## 2026-11-04 — Correspondence approval-test fixture correction

- Two remaining `CorrespondenceHttpTest` failures were caused by incomplete approval fixtures: the tests enabled internal approval but their creators had no primary department with an active manager. Both tests now create an active manager, create an active creator department with that manager, and attach the creator as its primary member. Application validation remains intact. Rerun focused correspondence tests; validation pending. Development only; master/server untouched.

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


## 2026-10-31 — Same-department employee referral state regression

- Confirmed the manager-to-employee referral implementation correctly sets `workflow_state=assigned_to_employee` while retaining the current department and assigning `current_user_id` to the selected active department member.
- This state is required by the employee-result guard: only the specifically assigned employee may submit the documented result; another member of the same department must be denied. After submission, the state advances to `result_pending_manager`.
- The failing HTTP test had a stale expectation of `in_progress` after employee referral. Corrected the regression assertion to `assigned_to_employee`; no production workflow behavior or schema changed.
- Validation pending: owner to pull `development` and run the focused `CorrespondenceHttpTest` once. `master` remains unchanged.

## 2026-10-31 — Correspondence department assignment and selector correction

- Fixed the shared Cartable audience for correspondence: a department referral is actionable by the current department manager, not every department member. The explicitly assigned employee becomes eligible once workflow state is `assigned_to_employee`; that state is now included in Cartable.
- Tightened result submission in `CorrespondenceWorkflowService`: a non-manager may submit a result only when the letter is explicitly assigned to that user. Being a member of the current department is not sufficient. The Show page now exposes the result action under the same condition.
- Simplified the manager action label to «ارجاع به» and removed repeated wording from the destination choices.
- Corrected shared `DepartmentTreeSelect.vue` viewport positioning: clamp horizontal placement, choose the side with sufficient vertical space, and recalculate on nested/window scrolling while the trigger remains visible.
- Added regression coverage for Cartable audience and for blocking result submission before explicit employee assignment. Validation pending: pull `development`, run the focused correspondence test and Cartable test, then run `npm run build`. No migration/schema changes. `master` unchanged.

## 2026-10-31 — Follow-up test scenario alignment

- The first focused correspondence test run exposed one remaining stale test scenario: it attempted employee result submission while the letter was still assigned to the department manager (`in_progress`).
- Updated that test to model the complete valid flow: manager explicitly refers to employee (`assigned_to_employee`), employee submits result (`result_pending_manager`), then manager refers to another department.
- No production authorization was weakened or changed for this follow-up. Validation pending rerun of `CorrespondenceHttpTest`; frontend build remains required for the earlier Vue changes.


## 2026-10-31 — Correspondence personal ownership and action filters

- Added a second independent filter to the correspondence list: «همه مکاتبات»، «نیاز به اقدام»، and «اقدام قبلی ثبت‌شده توسط من». It composes with the existing «مکاتبات من / مکاتبات واحد من / همه مکاتبات مجاز» scope and preserves other search filters and pagination query parameters.
- «نیاز به اقدام» is based on the current assignment (current_user_id equals the signed-in user). «اقدام قبلی ثبت‌شده توسط من» requires both a historical referral addressed to that user (correspondence_actions.to_user_id) and an action authored by that user (actor_id), and excludes letters currently assigned back to that user so pending work takes precedence.
- Corrected the «مکاتبات من» ownership scope: an action authored by the user is no longer sufficient by itself. Historical referrals addressed to the user remain included; merely referring a letter to another user/department does not make it the referrer's personal correspondence.
- Added feature regression coverage for pending referrals, previously acted-on referrals, and letters merely referred onward by the current user. No database migration or workflow authorization changes.
- Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master remains unchanged.


## 2026-10-31 — Correspondence list action-filter semantics

- Set the second correspondence filter's default to «نیاز به اقدام»; its options are now pending action, previously acted-on by me, created/registered by me, and all correspondence related to me. The existing first scope filter remains independent.
- «نیاز به اقدام» now requires the latest referral addressed to the user to remain the current assignment and no action by that user after that referral. This prevents an item already acted on from remaining in the pending-action filter even if the workflow retains the same current assignee.
- «اقدام قبلی ثبت‌شده توسط من» matches an action authored by the user after the latest referral to that user. A later referral to the same user resets it to pending until another action is recorded.
- «ثبت‌شده توسط من» includes correspondence created by the user (created_by) or finally registered by the user (registered_by). «همه مکاتبات» preserves the existing personal-relevance scope; the first scope selector still controls personal, department, or all-visible scope.
- Extended HTTP regression coverage for default pending filter, prior actions, assignment retained after an action, creator/registrar filter, and all-related mode. Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest, then npm run build. master unchanged.


## 2026-10-31 — Draft visibility test and independent correspondence filters

- Updated the draft visibility index test to explicitly select `action_filter=all` while retaining `scope=all`. The test concerns draft visibility authorization, not the default «نیاز به اقدام» filter; the explicit filter keeps those concerns independent.
- No production code, visibility policy, or workflow authorization changed. Validation pending: pull `development` and rerun `php artisan test --filter=CorrespondenceHttpTest`. If the suite passes, run `npm run build` because the correspondence index Vue component changed in the preceding work. `master` unchanged.


## 2026-10-31 — Pending correspondence for multi-department managers

- Fixed «نیاز به اقدام» so a user who manages multiple departments also receives active correspondence routed to any of those departments, including legacy/department referrals whose action has a null to_user_id.
- The manager branch is based on current department ownership (departments.manager_id via managedDepartments), current department routing history, and actionable workflow states. It excludes items explicitly assigned to an employee and items on which the manager has already acted after the latest routing to that department.
- The personal «مکاتبات من» scope now includes active correspondence in departments managed by the user. Ordinary department membership does not grant this manager-specific inclusion.
- Added regression coverage for a multi-department manager, a department referral without to_user_id, and exclusion of an employee-assigned item. Validation pending: pull development, run php artisan test --filter=CorrespondenceHttpTest. No frontend files changed; npm build is not required for this fix. master unchanged.


## 2026-10-31 — Managed-department scope closure fix

- Fixed the correspondence index `mine` scope closure to capture `$managedDepartmentIds`, which it uses when including current correspondence owned by the user's managed departments. The missing capture caused an undefined-variable HTTP 500 on correspondence index requests and cascaded into several focused tests.
- No filter semantics or authorization rules changed. The manager multi-department regression test remains in place. Validation pending: run `php artisan test --filter=CorrespondenceHttpTest`; no frontend build is required. `master` unchanged.

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


## 2026-11-01 — ثبت رسمی دامنه گردش کار درخواست پروژه و اتصال تسک/مکاتبه

- سند مرجع جدید `CORRESPONDENCE_PROJECT_WORKFLOW.md` ایجاد شد و نیازمندی‌های چهار اقدام کارمند واحد پروژه‌ها، تصمیم مستقل مدیر، انتخاب واحد پاسخ‌گو هنگام ایجاد پروژه، واحد پیش‌فرض قابل‌تغییر، ساخت Section/تسک پاسخ و ارتباط دوطرفه تسک/مکاتبه را ثبت می‌کند.
- وضعیت قابلیت‌ها شفاف شد: مرحله ۰ (مستندسازی) تکمیل؛ مرحله ۱ گردش کار درخواست/تصمیم مدیر انجام‌نشده؛ مرحله ۲ ایجاد پروژه و انتخاب واحد پاسخ‌گو انجام‌نشده؛ مرحله ۳ ساخت Section/تسک پاسخ انجام‌نشده؛ مرحله ۴ اتصال عمومی تسک/مکاتبه ناقص؛ مرحله ۵ تست و پذیرش انتها‌به‌انتها انجام‌نشده.
- تنها بخش اجرایی موجود در این محدوده، ورودی ایجاد نامه از جزئیات تسک و ذخیره `task_id` برای مسئول فعلی دارای مجوز است. انتقال خودکار ارتباط پروژه/نامه مبدأ و نمایش دوسویه روابط هنوز پیاده‌سازی نشده است.
- این commit مستندسازی است و هیچ کد اجرایی یا تستی تغییر نکرده؛ نتیجه تست/build جدیدی ادعا نمی‌شود. شاخه `master` بدون تغییر است.


## 2026-11-01 — اجرای مرحله اول گردش درخواست پروژه (اعتبارسنجی محلی در انتظار)

- در شاخه `development` شش action مستقل برای چهار درخواست کارمند واحد پروژه‌ها و دو تصمیم مدیر اضافه شد.
- چهار درخواست کارمند فقط برای مسئول فعلیِ کارمند در واحدی که در تنظیمات به‌عنوان واحد پروژه‌ها تعیین شده قابل ثبت‌اند؛ بازخورد در سرویس الزامی است. درخواست ارجاع، واحد مقصد فعال و متفاوت می‌خواهد.
- ثبت درخواست، نامه را به مدیر واحد پروژه‌ها می‌فرستد و وضعیت `project_review_pending_manager` می‌دهد؛ هیچ ارجاع نهایی یا ایجاد پروژه در این مرحله انجام نمی‌شود.
- رد درخواست کارمند توسط مدیر، نامه را برای بررسی مجدد به همان کارمند برمی‌گرداند. تأیید درخواست پروژه به وضعیت `project_request_approved` می‌رود. تأیید رد/برگشت، نامه را به واحد مبدأ (واحد گیرنده اولیه) برمی‌گرداند؛ تأیید ارجاع، نامه را به واحد انتخاب‌شده منتقل می‌کند.
- وضعیت‌های جدید به کارتابل مدیر و فهرست «نیاز به اقدام» اضافه شدند؛ UI جزئیات نامه، چهار درخواست کارمند و دو تصمیم مدیر را نمایش می‌دهد.
- دو تست HTTP برای مسیر درخواست پروژه/بازگشت به کارمند/تأیید و مسیر درخواست ارجاع با تأیید مدیر اضافه شده‌اند.
- **وضعیت مرحله ۱: تکمیل و اعتبارسنجی‌شده توسط مالک پروژه؛ تست‌ها پاس و build موفق اعلام شده است.** مرحله‌های ایجاد پروژه، Section/تسک پاسخ و تکمیل ارتباطات همچنان انجام‌نشده/ناقص هستند. `master` تغییر نکرده است.


## 2026-11-01 — تأیید مالک پروژه: مرحله اول گردش درخواست پروژه

- مالک پروژه اجرای تست‌ها و build را موفق اعلام کرد؛ مرحله ۱ از حالت «در انتظار اعتبارسنجی» به «تکمیل و تأییدشده» ارتقا یافت.
- مرحله ۲ همچنان شامل ایجاد پروژه از نامه وارده تأییدشده، انتخاب واحد پاسخ‌گو با مقدار پیش‌فرض قابل‌تغییر و اتصال پروژه به نامه مبدأ است؛ هنوز پیاده‌سازی نشده است.
- مرحله‌های ۳ تا ۵ نیز باز هستند. `master` بدون تغییر.


## 2026-11-01 — شروع پیاده‌سازی ایجاد پروژه از نامه تأییدشده

- مرحله ۱ گردش درخواست پروژه طبق اعلام مالک، تست پاس و build موفق دارد و پذیرفته‌شده است.
- مسیر backend و فرم ایجاد پروژه از نامه وارده با وضعیت `project_request_approved` اضافه شد؛ فقط مدیر واحد مدیریت پروژه با مجوز ایجاد پروژه می‌تواند آن را اجرا کند.
- در یک تراکنش پروژه، Section واحد پاسخ‌گو، تسک پاسخ، پیوند نامه مبدأ به پروژه/تسک و رکورد تاریخچه ایجاد می‌شود.
- **اعتبارسنجی مرحله ۲/۳ انجام نشده است.** تنظیم واحد پیش‌فرض سازمانی به تنظیمات سازمان متصل و در فرم پیش‌پر می‌شود؛ انتخاب در هر پروژه قابل تغییر است. نمایش دوطرفه روابط و انتقال ارتباط به نامه پاسخ نیز باقی است. قبل از ادعای تکمیل، migration و تست‌های HTTP روی SQL Server و build باید اجرا شوند.


## 2026-11-01 — Fix Vue typing for project creation errors

- Corrected the correspondence project-creation form to render the server's general `project` validation error from shared Inertia page errors (`page.props.errors`) rather than `projectForm.errors`. The form's typed payload contains only `name`, `description`, and `response_department_id`, so accessing `projectForm.errors.project` caused `vue-tsc` TS2339.
- This is a frontend TypeScript correction only; workflow behavior and backend validation are unchanged. Build has not yet been rerun; owner validation remains pending. Branch: `development`; `master` unchanged.


## 2026-11-01 — نمایش امن ارتباط نامه، پروژه و تسک

- در جزئیات پروژه، نامه وارده مبدأ از طریق رابطه `sourceCorrespondence` بازیابی می‌شود؛ قبل از ارسال به Inertia، `CorrespondenceAccessService::canView` روی نامه اعمال می‌شود. اگر کاربر مجاز به مشاهده نامه نباشد، هیچ شناسه یا مشخصات نامه‌ای به صفحه پروژه ارسال نمی‌شود.
- در مدل Task رابطه `correspondences()` اضافه شد. در جزئیات تسک، مکاتبات مرتبط با `task_id` خوانده و فقط موارد مجاز برای کاربر جاری به Vue ارسال می‌شوند.
- در UI جزئیات پروژه کارت «مکاتبه مبدأ» و لینک مشاهده نامه اضافه شد؛ در UI جزئیات تسک نیز فهرست «مکاتبات مرتبط» با لینک جزئیات هر نامه نمایش داده می‌شود.
- وضعیت مرحله ۴: پیوند داده‌ای و نمایش یک‌طرفه در صفحه پروژه و تسک پیاده‌سازی اولیه شد؛ کنترل محرمانگی با سرویس دسترسی مکاتبات اعمال شده است. نمایش پیوند در صفحه خود مکاتبه به پروژه/تسک قبلاً اضافه شده بود. هنوز تست‌های Feature مربوط به محرمانگی و روابط، تست SQL Server و build این برش اجرا نشده‌اند.
- مرحله ۵ همچنان در انتظار تست انتها‌به‌انتها و پذیرش است. شاخه `development`؛ `master` بدون تغییر.


## 2026-11-01 — تأیید build و تست مکاتبات برای مرحله ۴

- مالک پروژه اعلام کرد پس از دریافت آخرین تغییرات مرحله ۴، دستور `php artisan test --filter=CorrespondenceHttpTest` پاس شده و `npm run build` نیز موفق بوده است.
- این تأیید، صحت تست‌های موجود مکاتبات و build فرانت‌اند را پوشش می‌دهد؛ اما به معنی اجرای تست اختصاصی کنترل محرمانگی در صفحات جزئیات پروژه/تسک، تست مسیر ایجاد پروژه یا اعتبارسنجی migration روی SQL Server نیست.
- مرحله ۴ از نظر پیاده‌سازی نمایش ارتباط نامه مبدأ در جزئیات پروژه و مکاتبات مرتبط در جزئیات تسک، و انتقال ارتباط پروژه/نامه مبدأ به نامه پاسخ، پیاده‌سازی شده است؛ **اعتبارسنجی اختصاصی روابط و محرمانگی هنوز در انتظار است**.
- مراحل ۲ و ۳ نیز همچنان به تست مسیر ایجاد پروژه، انتخاب واحد پاسخ‌گو، ساخت Section/Task و اعتبارسنجی SQL Server نیاز دارند. مرحله ۵ (سناریوی انتها‌به‌انتها و پذیرش دستی) باز است.
- شاخه `development`؛ `master` بدون تغییر.


## 2026-11-01 — پیاده‌سازی اولیه گردش عمومی نامه وارده

- سند مرجع `CORRESPONDENCE_GENERAL_WORKFLOW.md` اکنون مبنای گردش عمومی نامه وارده است و از گردش اختصاصی واحد مدیریت پروژه‌ها تفکیک شده است.
- در `CorrespondenceWorkflowService` چهار درخواست کارمند واحدهای عادی با بازخورد اجباری اضافه شد؛ نامه به مدیر همان واحد و وضعیت `general_review_pending_manager` منتقل می‌شود. مدیر می‌تواند درخواست را با کامنت رد و به همان کارمند برگرداند.
- مدیر برای درخواست پروژه می‌تواند نامه را به واحد فعال مدیریت پروژه‌ها که از تنظیمات سازمان خوانده می‌شود ارجاع دهد؛ مقصد به نام ثابت واحد وابسته نیست. درخواست «بدون درخواست» از ارجاع عمومی موجود استفاده می‌کند.
- برای درخواست نامه و گردش کار، مسیر اجرای تأییدشده هنوز کامل نیست؛ در حال حاضر فقط رد درخواست پیاده‌سازی شده و مسیر ایجاد نامه توسط مدیر/تهیه پیش‌نویس کارمند و اتصال گردش کار به موجودیت واقعی باز است.
- از جزئیات نامه، فرم فعلی ایجاد دستورکار باز می‌شود؛ پروژه عمومی و Section واحد از قبل انتخاب می‌شوند. backend مالکیت مدیر، واحد جاری، پروژه عمومی و Section را اعتبارسنجی می‌کند، سپس دستورکار را می‌سازد و به نامه متصل می‌کند. قابلیت جدید دستورکار ایجاد نشده است.
- تست Feature برای درخواست کارمند و رد مدیر اضافه شد، اما در این محیط اجرا نشده است. تست اختصاصی اتصال دستورکار، تست کامل گردش، build و SQL Server در انتظارند.
- شاخه `development`؛ `master` بدون تغییر.


## 2026-11-01 — تکمیل برش‌های اجرایی گردش عمومی مکاتبات

- مسیر درخواست نامه تکمیل اولیه شد: مدیر می‌تواند خودش ایجاد نامه را آغاز کند یا تهیه پیش‌نویس را به همان کارمند دارای مجوز `correspondence.create` واگذار کند. فرم موجود نامه با مبدأ از پیش انتخاب‌شده باز می‌شود؛ نامه جدید با `reply_to_id` به نامه مبدأ متصل می‌شود و پس از ذخیره، نامه مبدأ برای بررسی مدیر برمی‌گردد.
- مسیر درخواست گردش کار به ماژول واقعی Workflows متصل شد: مدیر یک `WorkflowDefinition` فعال انتخاب می‌کند، `WorkflowService::start` یک `WorkflowInstance` و تسک‌های مراحل را ایجاد می‌کند و شناسه instance به نامه متصل می‌شود. جزئیات نامه، تسک‌های قابل‌مشاهده این گردش را لینک می‌کند.
- درخواست عمومی کارمند، رد و بازگشت به همان کارمند، ارجاع درخواست پروژه به واحد تنظیم‌شده، ثبت دستورکار در پروژه عمومی/Section واحد با فرم موجود Tasks و اتصال واقعی task به نامه نیز در برش قبلی اضافه شده‌اند.
- تست‌های Feature برای درخواست/رد عمومی، واگذاری پیش‌نویس نامه و شروع WorkflowInstance اضافه شده‌اند؛ **هیچ تست یا build در این محیط اجرا نشده است**. تست اختصاصی اتصال دستورکار، سناریوهای محرمانگی، اجرای کامل تست‌ها، build و SQL Server همچنان در انتظارند.
- شاخه `development`؛ `master` بدون تغییر.


## 2026-11-01 — اتصال دوطرفه نامه و تسک‌های گردش کار

- مدل WorkflowInstance اکنون رابطه مکاتبات متصل را دارد و Task نیز مراحل WorkflowInstance خود را بازیابی می‌کند.
- در جزئیات نامه، instance گردش کار و تسک‌های مراحلی که کاربر مجاز به مشاهده آن‌هاست نمایش داده می‌شود. در جزئیات تسک نیز نامه مبدأ متصل از طریق WorkflowInstance، پس از اعمال `CorrespondenceAccessService::canView` نمایش داده می‌شود.
- این تغییر فقط روابط و نمایش را تکمیل می‌کند؛ تست محرمانگی این مسیر و build هنوز اجرا نشده‌اند.


## 2026-11-01 — تکمیل گردش کار مرتبط و دسترسی محرمانگی

- پس از تکمیل آخرین تسک WorkflowInstance، وضعیت نامه مرتبط به `workflow_completed` تغییر می‌کند.
- دسترسی به نامه محرمانه اکنون مسئول فعلی و ایجادکننده تسک مستقیم مرتبط و نیز مسئول/ایجادکننده تسک‌های مراحل WorkflowInstance را به‌عنوان افراد مرتبط در نظر می‌گیرد؛ هم `canView` و هم query فهرست مکاتبات این قاعده را اعمال می‌کنند.
- تست Feature برای دسترسی مسئول تسک گردش کار به نامه محرمانه و منع دسترسی کاربر نامرتبط اضافه شد؛ اجرا نشده است.


## 2026-11-01 — بستن درخواست نامه پس از ثبت نهایی پاسخ

- وقتی نامه‌ای که از درخواست کارمند ایجاد شده ثبت نهایی می‌شود، نامه مبدأ نیز با اقدام `requested_letter_registered` به وضعیت `letter_registered` می‌رود و از گردش اقدام‌پذیر خارج می‌شود.
- برای جلوگیری از ارجاع مجدد یا اقدام متناقض، وضعیت‌های `workflow_completed`، `letter_draft_pending_manager` و `letter_registered` در backend از دریافت اقدام جدید مدیر منع شده‌اند.
- تست Feature مسیر ثبت نهایی نامه پاسخ و بسته‌شدن درخواست مبدأ اضافه شد؛ اجرا نشده است.


## 2026-11-01 — تست‌های تکمیلی گردش عمومی

- تست Feature ایجاد دستورکار از نامه اضافه شد: فرم/route، پروژه عمومی، Section واحد، مسئول یگانه، ثبت `task_id` و تاریخچه اقدام بررسی می‌شوند.
- تست‌های قبلی اضافه‌شده نیز مسیر درخواست/رد، واگذاری و ثبت نهایی پیش‌نویس نامه، شروع و تکمیل WorkflowInstance و دسترسی محرمانه مسئول تسک گردش کار را پوشش می‌دهند.
- **این تست‌ها هنوز اجرا نشده‌اند**؛ هیچ نتیجه پاس/فیلدی برای این برش ثبت نشده است. Build، تست کامل، تست SQL Server و پذیرش دستی همچنان در انتظارند.


## 2026-11-01 — دسترسی فرم تسک برای مدیر واحد

- `TaskFormOptionsService` برای مدیران واحد، پروژه عمومی سازمان و Sectionهای همان واحد را در گزینه‌های فرم موجود تسک نمایش می‌دهد؛ این مورد برای مجوز `tasks.create` با scope پروژه نیز لحاظ شده است.
- تست اتصال دستورکار از نامه اکنون در کنار ایجاد واقعی تسک، وجود پروژه/Section عمومی در گزینه‌های فرم مدیر را نیز بررسی می‌کند؛ تست اجرا نشده است.


## 2026-11-01 — دسترسی مدیر به پیش‌نویس واگذارشده

- پیش‌نویس نامه‌ای که کارمند در پاسخ به درخواست ایجاد می‌کند، با وجود وضعیت Draft برای مدیر مسئول نامه مبدأ قابل مشاهده است؛ این دسترسی فقط از طریق رابطه `reply_to` و وقتی نامه مبدأ در وضعیت `letter_draft_pending_manager` و در اختیار همان مدیر است برقرار می‌شود.
- تست Feature مشاهده پیش‌نویس توسط مدیر اضافه شد؛ اجرا نشده است.


## 2026-11-01 — ثبت نگاشت stateهای گردش عمومی

- نگاشت stateهای اجرایی `general_review_pending_manager`، `letter_creation_by_manager`، `letter_draft_pending_employee`، `letter_draft_pending_manager`، `letter_registered`، `workflow_started` و `workflow_completed` در سند مرجع ثبت شد.
- ارجاع به مدیریت پروژه‌ها و ایجاد دستورکار عمومی، state عمومی `in_progress` را حفظ می‌کنند و مقصد/رابطه در current department، action history و شناسه‌های اتصال مشخص می‌شود.

## 2026-11-01 — Fix incoming correspondence service test fixture

- `CorrespondenceService::createDraft()` intentionally requires an incoming letter's original `letter_number`; the failing test created an incoming draft without that required field. Updated the test fixture to provide `IN-1405-001` and assert that exact source number is persisted. Production validation is unchanged.
- User-reported suite result before this fix: 1 failed, 454 passed (1,921 assertions). The fix has been committed to `development`; rerun the targeted test and full suite to verify. No claim that tests were run by the assistant. `master` unchanged.

## 2026-11-01 — Fix manager access to shared task project form

- Root cause: `TaskController@index` only supplies `formOptions` when `TaskPolicy::create($user, null)` succeeds. Project-scoped department managers were denied at the null-project check even though `TaskFormOptionsService` deliberately exposes the shared default project and their enabled department sections.
- Updated `TaskPolicy::create`: project-scoped managers can open the task form only when they manage an active department with `manager_can_create_tasks=true` in the active shared system-default project. For a specified project ID, creation is permitted only for that shared default project under the same department-section condition; all other project scope checks remain unchanged. Correspondence store still validates the exact selected section against the correspondence's current department.
- Added regression coverage via the existing `manager creates general task from correspondence and links it` feature test. User-reported pre-fix result: 1 failed, 459 passed (2,002 assertions). Assistant has not executed tests/build; verification remains pending. `master` unchanged.


## اصلاحات مسیرهای مکاتبات — 2026-11-01

- درخواست «ایجاد دستورکار» از درخواست «گردش کار» جدا شد. کارمند می‌تواند درخواست دستورکار بدهد؛ مدیر پس از تأیید، مالک اقدام باقی می‌ماند و لینک فرم موجود Tasks برای ایجاد دستورکار در پروژه عمومی و Section همان واحد نمایش داده می‌شود. ایجاد Task همچنان از مسیر و اعتبارسنجی ماژول Tasks انجام می‌شود.
- وضعیت general_review_pending_manager به فیلتر «نیاز به اقدام» مدیر واحد اضافه شد تا درخواست‌های برگشتی از کارمند در کارتابل مدیر دیده شوند.
- اقدام «پس گرفتن ارجاع» اضافه شد. مدیر واحد فقط تا زمانی که کارمند مقصد اقدامی ثبت نکرده باشد می‌تواند ارجاع فعال را پس بگیرد؛ عملیات در تاریخچه ثبت و مالک نامه به مدیر برمی‌گردد. ارجاع مجدد در وضعیت assigned_to_employee مسدود شده است.
- مدیر واحد مدیریت پروژه‌ها نمی‌تواند با ارجاع عمومی، درخواست پروژه را مستقیم به واحد دیگری منتقل کند؛ ارجاع بین‌واحدی باید از درخواست صریح کارمند پروژه‌ها و تأیید مدیر عبور کند. ارجاع داخلی مدیر به کارمند همان واحد همچنان مجاز است.
- تست‌های Feature برای پس گرفتن ارجاع، درخواست دستورکار در پروژه عمومی و فیلتر درخواست‌های در انتظار مدیر اضافه شده‌اند.
- **وضعیت اعتبارسنجی:** تست‌ها و build در محیط توسعه اجرا نشده‌اند؛ بررسی نهایی SQL Server و سناریوی UI پس از pull توسط کاربر لازم است.
- دامنه «مکاتبات من» برای مدیر واحد گسترش یافت تا نامه‌هایی را که اعضای واحد او ثبت کرده‌اند نیز پس از ارجاع به واحد گیرنده برای پیگیری/مشاهده نگه دارد؛ این رابطه به‌تنهایی به معنی «نیاز به اقدام» نیست و فیلتر نیاز به اقدام فقط مالک فعلی را نشان می‌دهد.
- گزینه پذیرش درخواست دستورکار فقط زمانی نمایش داده می‌شود که مدیر مجوزهای tasks.create و tasks.view را داشته باشد و پروژه عمومی فعال و Section همان واحد با manager_can_create_tasks فعال باشد؛ backend همین شرایط را هنگام پذیرش دوباره کنترل می‌کند.
- در واحد مدیریت پروژه‌ها، فرم ارجاع عمومی مدیر به کارمند همان واحد محدود شده و گزینه ارجاع مستقیم به واحد دیگر در UI نمایش داده نمی‌شود.

## 2026-11-02 — Incoming letter approval ownership and project-request action queue

- Root cause identified: incoming drafts were always created with `awaiting_registration` and no current assignee, regardless of `correspondence.approval.incoming`. Therefore the Secretariat department manager did not receive an approval task, and an employee could register the draft directly even when approval was configured as required.
- Code updated on `development`: when approval is required, incoming drafts now enter `awaiting_manager_approval`, are assigned to the active configured Secretariat department manager, and fail clearly if that department has no manager. Draft visibility explicitly includes the assigned manager, and the UI defaults the manager action to `manager_approve`.
- `manager_approve` now supports incoming letters only when the incoming approval setting requires it. Final registration remains a Secretariat action; after approval, the draft is assigned back to its creator so it appears as their next actionable item. Numbering service now enforces manager approval for every letter type whose setting requires approval.
- The `needs_action` department-manager state list now includes `project_request_approved`. After a project request is approved, the assigned project department manager can see the letter in «نیاز به اقدام» while it awaits project creation.
- Added/updated Feature coverage for incoming manager approval and the approved-project-request action queue.
- Validation status: **not run in this environment**. Run targeted `CorrespondenceHttpTest`, then the full `php artisan test` suite and frontend build locally. SQL Server compatibility and UI acceptance remain pending. `master` unchanged.


## 2026-11-02 — Follow-up fixes from correspondence test run

- Fixed `CorrespondenceAccessService::canView`: a non-Secretariat department manager assigned as `current_user_id` on an `awaiting_manager_approval` draft can now open it, provided the current department identifies that user as its manager. This closes the gap where list visibility allowed the manager but the detail page returned 403.
- Fixed `CorrespondenceNumberingService::register` validation order: incoming letters now validate the mandatory original letter number before the approval-state guard. This preserves the required original-number error and does not consume a registration number.
- Commit: `9f9f90bb374cb107ce04fb1adb57bafcdb085251` on `development`. Verification pending: rerun both correspondence feature test classes, full suite, and frontend build. `master` unchanged.


## 2026-11-02 — اصلاح گردش ثبت نهایی دبیرخانه

- مرحله مستقل «تأیید مدیر پیش از ثبت نهایی» از مسیر ایجاد پیش‌نویس و ثبت نهایی حذف شد؛ پیش‌نویس‌ها مستقیماً با وضعیت «در انتظار ثبت نهایی» ایجاد می‌شوند.
- دکمه «ثبت نهایی» فقط برای مدیر واحد دبیرخانه (و مدیرکل سیستم) فعال/قابل مشاهده است. کارمند عادی دبیرخانه می‌تواند پیش‌نویس را ایجاد و مشاهده کند، اما امکان ثبت نهایی ندارد.
- مدیر دبیرخانه با انتخاب «ثبت نهایی» مستقیماً شماره ثبت را دریافت و نامه را ثبت می‌کند؛ شرط وضعیت تأیید مدیر از سرویس شماره‌گذاری حذف شد.
- ثبت نهایی نامه داخلی توسط ایجادکننده مطابق قاعده قبلی حفظ شده است.
- رابط کاربری اقدام اضافی «تأیید برای ثبت نهایی» را حذف کرد.
- تست HTTP مسیر ثبت وارده به‌روزرسانی شد تا عدم دسترسی کارمند دبیرخانه و ثبت موفق توسط مدیر دبیرخانه را پوشش دهد.
- وضعیت اجرا: تغییرات کد و تست ثبت شده‌اند؛ اجرای PHPUnit و build در محیط توسعه کاربر هنوز تأیید نشده است.


## 2026-11-02 — همگام‌سازی تست شماره‌گذاری با ثبت مستقیم دبیرخانه

- تست قدیمی `registration_requiring_approval_does_not_consume_a_number_without_completed_workflow` حذف و با `secretariat_manager_can_register_draft_without_intermediate_approval` جایگزین شد.
- تست جدید بررسی می‌کند مدیر واحد دبیرخانه بدون اقدام تأیید جداگانه، پیش‌نویس را ثبت نهایی کند و شمارنده ثبت دقیقاً یک واحد افزایش یابد.
- این تغییر صرفاً اصلاح انتظار تست برای گردش‌کار مصوب جدید است؛ اجرای تست‌ها و build باید در محیط توسعه تأیید شود.


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

- Policy و مسیرهای پروژه اصلاح شدند تا ادمین اصلی بتواند پروژه عمومی را ویرایش کند؛ حذف پروژه سیستمی همچنان ممنوع است. برای کاربران غیرادمین، محدودیت ویرایش پروژه عمومی حفظ شده است.
- middleware مجوز projects.update از مسیرهای ویرایش پروژه/بخش برداشته شد؛ کنترل مجوز در Policy و Gate::authorize کنترلرها انجام می‌شود.
- بخش‌های پروژه عمومی دیگر از مسیر دستی قابل ایجاد یا حذف نیستند و اتصال آن‌ها به واحد سازمانی قابل تغییر نیست. نام بخش در ویرایش از نام واحد جاری گرفته می‌شود.
- ادمین اصلی می‌تواند توضیحات و مجاز بودن ایجاد دستورکار در بخش موجود را تنظیم کند. همگام‌سازی نام واحد، تنظیمات دستی توضیحات را بازنویسی نمی‌کند؛ غیرفعال بودن واحد یا مدیر، ایجاد دستورکار را غیرفعال می‌کند.
- دستور php artisan projects:sync-organization-sections برای تطبیق بخش‌های موجود با تمام واحدها اضافه شد. این دستور بخش مفقود را ایجاد و بخش متناظر را همگام می‌کند؛ بخش‌های تکراری را گزارش می‌کند و به‌صورت خودکار حذف نمی‌کند تا سوابق حفظ شوند.
- تست‌ها، build و اجرای دستور روی دیتابیس واقعی هنوز انجام نشده‌اند. وضعیت اعتبارسنجی: در انتظار اجرای کاربر. شاخه development؛ master بدون تغییر.


## 2026-11-03 — محدودسازی اقدامات مدیر تا ایجاد دستورکار تأییدشده

- پس از تأیید درخواست «ایجاد دستورکار» توسط مدیر، تا زمانی که دستورکار واقعاً ایجاد و به نامه متصل نشده باشد، فرم اقدامات عادی مدیر (از جمله ارجاع) در صفحه نمایش داده نمی‌شود.
- کنترل سمت سرور نیز ارجاع را در این فاصله رد می‌کند؛ بنابراین محدودیت صرفاً ظاهری نیست.
- پس از اتصال دستورکار، اقدامات عادی مدیر دوباره فعال می‌شوند و مدیر می‌تواند نامه را ارجاع دهد یا با اقدام مستقل «خاتمه گردش نامه» آن را مختومه کند.
- خاتمه گردش فقط برای همان دستورکار تأییدشده و متصل به نامه مجاز است؛ ثبت توضیح الزامی است و وضعیت نامه به `workflow_completed` تغییر می‌کند.
- تست Feature سناریوی درخواست کارمند، تأیید مدیر، جلوگیری از ارجاع پیش از ایجاد دستورکار، فعال شدن اقدامات پس از اتصال و خاتمه گردش را پوشش می‌دهد.
- وضعیت اجرا: کد و تست به‌روزرسانی شده‌اند؛ اجرای واقعی تست‌ها و build پس از دریافت تغییرات در محیط توسعه/استقرار باید تأیید شود.

## 2026-11-03 — Launch task creation form from correspondence

- Fixed the approved correspondence «ایجاد دستورکار» destination: the Tasks index now resolves and validates the correspondence, latest employee task request, manager approval action, current department manager, active organization project section, and section task-creation setting on the server.
- The validated creation context is sent as an Inertia prop; the Vue page opens the create modal and preselects correspondence, shared project, section, and department from that prop instead of relying on client-side URL parsing.
- If the manager's normal task-form options omit the authorized shared project/section, the server adds only the validated target project/section to the form options. Unauthorized or stale query parameters do not open the modal. Existing task-store authorization and correspondence-linking checks remain in place.
- Extended the correspondence feature test to request the Tasks page with the three launch query parameters and assert the validated launch context and target section are present. Validation pending: run `php artisan test --filter=test_manager_creates_general_task_from_correspondence_and_links_it` and `npm run build`; `master` unchanged.


## 2026-11-03 — اصلاح مجوز ایجاد دستورکار از مسیر نامه

- علت ریشه‌ای باز نشدن مودال مشخص شد: صفحه Tasks مجوز `tasks.create` را فقط بدون شناسه پروژه بررسی می‌کرد. در `TaskPolicy` این بررسی عمومی برای برخی مدیران واحد با scope سطح واحد false است، در حالی که همان مدیر برای بخش مجاز پروژه عمومی مجوز ایجاد دارد.
- در `TaskController::index` اکنون علاوه بر مجوز عمومی، مجوز ایجاد با شناسه پروژه مقصد نیز ارزیابی می‌شود. بنابراین مدیر مجاز می‌تواند فرم را دریافت کند و prop مربوط به نامه به Vue برسد؛ اعتبارسنجی نامه، تأیید مدیر و بخش مقصد همچنان برقرار است.
- تست و build در محیط کاربر پیش‌تر پاس شده‌اند؛ این اصلاح جدید هنوز باید پس از pull با همان تست Feature و build مجدداً اعتبارسنجی شود. شاخه development؛ master بدون تغییر.

## اصلاح اعتبارسنجی نامه وارده
- سازمان مبدأ برای نامه وارده در ثبت و ویرایش الزامی شد.
- شماره نامه وارده در محدوده هر سازمان یکتا است؛ تکرار در همان سازمان رد و تکرار بین دو سازمان متفاوت مجاز است.
- ستاره و required فرم برای سازمان مبدأ در ایجاد و ویرایش اضافه شد.
- تست و build این تغییرات هنوز باید اجرا شوند.

- اصلاح تست‌های Feature برای الزام سازمان مبدأ در نامه‌های وارده: داده تست‌های قدیمی تکمیل شد؛ تست مستقل نیز اجباری بودن سازمان، رد شماره تکراری در همان سازمان و پذیرش همان شماره در سازمان دیگر را پوشش می‌دهد. اجرای تست‌ها پس از دریافت این commit لازم است.

## 2026-11-03 — اصلاح پیام اعتبارسنجی و فهرست مکاتبات

- پیام تکراری بودن شماره نامه وارده در ثبت و ویرایش فارسی شد: «شماره نامه با این سازمان مبدأ قبلاً ثبت شده است.»
- ستاره دستی از برچسب سازمان مبدأ/مخاطب در فرم ایجاد و ویرایش حذف شد؛ نمایش ستاره فقط از قرارداد عمومی CSS برای کنترل required انجام می‌شود.
- در فیلتر واحدهای «مکاتبات من»، گزینه «من» به «همه واحدهای من» تغییر کرد.
- پس از حذف مکاتبه، فیلترهای فعال دوباره اعمال می‌شوند تا فهرست به حالت بدون فیلتر برنگردد.
- نامه‌های وارده پیش‌نویس دبیرخانه که در انتظار ثبت/تأیید هستند، در دامنه «مکاتبات من» و فیلتر «نیاز به اقدام» مدیر جاری دبیرخانه نیز نمایش داده می‌شوند؛ این قاعده به نقش مدیر دبیرخانه محدود است.
- وضعیت اعتبارسنجی: تست Feature و build برای این اصلاحات هنوز باید در محیط توسعه اجرا شوند. شاخه development؛ master بدون تغییر.
- علت تکمیلی مشکل نمایش پیش‌نویس: `isSecretariat()` عضویت در دبیرخانه را تشخیص می‌داد اما مدیر تنظیم‌شده واحد را لزوماً عضو تلقی نمی‌کرد؛ مدیر جاری دبیرخانه اکنون صریحاً به‌عنوان مسئول دبیرخانه برای مشاهده پیش‌نویس‌ها شناخته می‌شود. این با `canRegister()` هم‌راستا شد.

## 2026-11-04 — اجرای مرحله ۱: پذیرش مسئولیت مدیر
- در شاخه `development` اقدام مستقل پذیرش مسئولیت مکاتبه و endpoint مستقل پذیرش مسئولیت تسک اضافه شد.
- در مکاتبات، اقدام فقط برای مدیر واحد جاری و نامه ثبت‌شده در وضعیت `in_progress` با مسئول خالی یا خود مدیر مجاز است. تاریخچه اقدام و audit از مسیر موجود ثبت می‌شوند.
- در تسک، فقط مدیر واحد مرتبط با تسک و دارنده مجوز `tasks.assign` می‌تواند مسئولیت را بپذیرد؛ تسک نهایی یا در انتظار بررسی قابل تغییر نیست. نوع تخصیص مستقل در تاریخچه و audit ثبت می‌شود.
- دکمه مستقل در UI دو ماژول و تست‌های HTTP مرتبط افزوده شدند.
- **مرحله ۱: پیاده‌سازی در انتظار تست کاربر.** تست/build اجرا و تأیید نشده‌اند.
- وضعیت همه پنج مرحله: ۱ پیاده‌سازی در انتظار تست؛ ۲ شروع نشده؛ ۳ مستند شده/شروع نشده؛ ۴ شروع نشده؛ ۵ شروع نشده.
- `master` و سرور تغییر نکرده‌اند.

## 2026-11-04 — اصلاح خطای طول نوع تخصیص پذیرش مسئولیت تسک
- گزارش تست نشان داد ستون `task_assignments.type` در migration با طول ۲۰ کاراکتر تعریف شده و مقدار `manager_accept_responsibility` باعث خطای SQL Server از نوع truncation می‌شد.
- کد نوع تخصیص تسک به شناسه کوتاه `manager_accept` تغییر کرد که در ظرفیت فعلی ستون جا می‌گیرد؛ action مکاتبات همچنان شناسه مستقل `manager_accept_responsibility` خود را دارد.
- تست مربوطه نیز برای شناسه کوتاه به‌روزرسانی شد. تست مجدد توسط کاربر لازم است.



## 2026-11-04 — Phase 3 repository audit checkpoint (implementation not started)

- Audited the current `development` implementation for organization settings, department configuration, correspondence detail/output, routes, filesystem handling, and the existing Documents module.
- Verified that organization settings already persist organization name, address, phone, email and website through `SystemSettingsService`; these existing values should be reused as independent letterhead elements rather than duplicated.
- Verified that `Department` already stores `manager_id`, but no manager-signature image field or upload flow exists. Organization settings likewise have no stamp-image field/upload flow.
- Verified that correspondence detail currently has no print/PDF endpoint or dedicated document-template view. `CorrespondenceController::show` supplies correspondence metadata/body, while `Correspondence/Show.vue` is an operational detail/workflow screen rather than a print layout.
- Verified that `composer.json` has no PDF-generation dependency. The existing Documents module uses a storage contract and logical disk/path metadata; file downloads are authorization-checked. The profile avatar is stored on the public disk, which is not sufficient precedent for private official stamp/signature assets without a separate access decision.
- No application code, migrations, dependencies, master branch, release branch or server were changed in this audit checkpoint.
- Phase 3 implementation is blocked on one output-architecture decision: use one HTML/CSS A4 template and the browser's native Print / Save as PDF flow (no server-side PDF dependency), or add a server-side PDF renderer for downloadable generated PDFs. The choice determines rendering, preview, pagination and asset-delivery implementation.
- Five-phase status: Phase 1 focused tests passed (owner-reported); Phase 2 implementation and focused tests passed (owner-reported; build/E2E pending); Phase 3 audited, implementation not started; Phase 4 not started; Phase 5 not started.


## 2026-11-04 — Phase 3 implementation slice 1 (code committed; local validation pending)

- Confirmed the selected output architecture: one HTML/CSS A4 document view with browser-native Print / Save as PDF; no PDF Composer package was added.
- Added unit-level manager signature configuration: the signature image is stored on the Department record, not on individual users. The department continues to have one manager and multiple members. Changing the department manager automatically deactivates the previous signature until a new signature is supplied.
- Added organization-level stamp upload in Core → Organization Settings. Stamp and manager signature are separate private files, independently replaceable and activatable. Uploads accept PNG/JPG/WEBP, maximum 2 MB, with dimension limits.
- Added protected authenticated image delivery and hid private storage paths from serialized Department data.
- Added finalization-time outgoing-letter snapshot for creator name, issuing unit title, organization contact details, organization stamp and the issuing department manager signature. The manager signature is snapshotted only when the workflow has reached `approved_for_registration`; direct registration does not imply manager approval.
- Added a dedicated registered-correspondence A4 print-preview page, a per-operation «با سربرگ» toggle, separate stamp/signature rendering, print visibility settings, and browser print / Save as PDF action. Added page counters through CSS page margin boxes.
- Added focused regression tests for department signature upload, organization stamp upload / visibility settings, and immutable outgoing-letter identity/image snapshots.
- No PDF dependency added. No master, release branch or server changes.
- Validation is not yet run in the owner's local environment. Migration, PHP tests, frontend type-check/build, multi-page print pagination and manual preview still require verification. Full coordinate configurability, logo/background assets and configurable page-number placement remain follow-up items within Phase 3.
- This entry supersedes the earlier 2026-11-04 Phase 3 audit note that said implementation had not started.


## 2026-11-04 — Settings interface update

Organization Settings now uses three tabs. A separate organization letterhead image is uploaded in settings, served through a protected preview route, snapshotted per registered letter, and selectable as a positioned image element in the A4 template. Template JSON regression comparison now normalizes object key order. Letterhead upload/preview and snapshot test coverage has been added. Local validation is pending.


## 2026-11-07 — اصلاح محدوده ابعاد تصویر سربرگ سازمان

- محدودیت اعتبارسنجی تصویر سربرگ در سمت سرور اصلاح شد: عرض مجاز ۱۵۰ تا ۲۵۰۰ پیکسل و ارتفاع مجاز ۶۰۰ تا ۵۰۰۰ پیکسل است؛ محدودیت حجم ۵ مگابایت و فرمت‌های PNG/JPG/WEBP بدون تغییر باقی ماند.
- متن راهنمای زیر فیلد آپلود با همین بازه‌های دقیق هماهنگ شد و عبارت «تصویر افقی» که با محدوده ابعاد مورد انتظار تناقض داشت حذف شد.
- تست تنظیمات سازمان اکنون تصویر ۲۴۸۱ × ۳۵۰۸ را برای سربرگ بارگذاری می‌کند تا پذیرش ابعاد اعلام‌شده به‌صورت رگرسیون پوشش داده شود.
- کد، UI و تست در شاخه development ثبت شده‌اند. اجرای واقعی تست PHP و build در محیط توسعه کاربر هنوز تأیید نشده است؛ master و سرور تغییری نکرده‌اند.


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


## 2026-11-08 — اصلاح بارگذاری تصویر امضای مدیر در پیش‌نمایش چاپ

- علت شکست تست چاپ مشخص شد: رابطه `createdDepartment` با انتخاب محدود ستون‌ها (`id,name`) بارگذاری می‌شد؛ در نتیجه فیلدهای `manager_signature_path` و `manager_signature_active` در مدل بارگذاری‌شده وجود نداشتند و fallback امضای مدیر همیشه مسیر خالی دریافت می‌کرد.
- انتخاب ستون‌های رابطه اکنون شامل دو فیلد تصویر امضای مدیر نیز هست. مسیر snapshot همچنان اولویت دارد و fallback فقط برای تصویر snapshot ناموجود/غیرقابل‌استفاده اعمال می‌شود.
- تست رگرسیون موجود، دریافت هم‌زمان data URI مهر و امضا را بررسی می‌کند. نتیجه اجرای مجدد تست و build در محیط محلی کاربر هنوز در انتظار است. شاخه development؛ master و سرور بدون تغییر.


## 2026-11-08 — بهبود ابعاد مهر، هم‌پوشانی و عنوان سازمانی مدیر در چاپ نامه

- عرض تصویر مهر و امضا در پیش‌نمایش طراح و خروجی چاپ از عرض المان قالب پیروی می‌کند؛ ارتفاع تصویر خودکار و متناسب با نسبت ابعاد فایل اصلی است و محدودیت ثابت عرض/ارتفاع که تصویر را کوچک می‌کرد حذف شده است.
- فاصله از بخش قبل برای المان‌های جریان صادرکننده اکنون مقدار منفی تا ۸۰ میلی‌متر را می‌پذیرد. این امکان برای قرارگیری مهر و امضا روی نام تنظیم‌کننده در طراح و چاپ واقعی فراهم شده است.
- دو متغیر `{{manager_name}}` و `{{manager_position}}` به قالب اضافه شدند. نام مدیر و سمت فعال او از انتساب سمت در همان واحد دریافت می‌شود؛ سمت اصلی فعال در اولویت است. اگر سمت تعریف نشده باشد، متغیر خالی می‌ماند و متن ساختگی تولید نمی‌شود.
- تست پیش‌نمایش چاپ برای دریافت نام و سمت مدیر واحد گسترش یافت. اجرای تست محلی و build هنوز در انتظار گزارش کاربر است. شاخه development؛ master و سرور بدون تغییر.

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

## 2026-11-09 — Print layout single-origin refactor (validation pending)

- `Correspondence/Print.vue` now uses one physical A4 horizontal coordinate system for print: `@page` has zero left/right margins, the paper/coordinate plane and print flow box are 210 mm wide, and configured left/right text margins are applied once as flow-box padding.
- `@page` retains only effective top/bottom safe-area margins (`max(text margin, header/footer height)`), so body pagination reserves the header/footer zones on every page. The absolute template plane is shifted from the page content origin to physical A4 top; the body remains normal flow.
- This removes the previous combination of CSS horizontal page margins plus negative right offset. It targets the reported duplicate top/side offset and ignored header/footer area.
- Status: code committed to `development`; frontend build, focused PHP feature test and Chromium PDF comparison have NOT been run/confirmed. Do not mark print parity complete until the owner validates the same letter in preview and PDF, including a multi-page case and asymmetric left/right margins. `master`, release and server unchanged.

## 2026-11-09 — Outgoing dispatch stage and recipient title prefix

- The existing external tracking columns are reused; no duplicate schema was introduced. Outgoing letters in `awaiting_dispatch` are now included in the `needs_action` state filter for all active Secretariat members, fixing the hand-off that previously made the newly registered outgoing letter disappear from the actionable list.
- Final dispatch now requires both a dispatch method and a sent date while the letter is in `awaiting_dispatch`. The workflow service independently enforces the same invariant before recording `dispatched`; external registration number/date remain optional tracking metadata. While awaiting dispatch, only Secretariat members/system administrators may edit this stage; creator/task-owner tracking permissions resume outside that state.
- Added `{{recipient_organizational_title_prefix}}` to the addressing element token allow-list, designer token catalog/sample values, and print resolver. It reads the immutable `organizational_title_prefix` captured from the selected external contact at registration.
- Frontend build, focused correspondence tests, and manual Secretariat workflow verification are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Recipient personal title prefix token

- Added `{{recipient_personal_title_prefix}}` to the addressing/heading element token catalog. It resolves from the external contact's immutable `recipient_addressing_snapshot.personal_salutation` value in print, independently of `{{recipient_title}}` (which continues to combine personal salutation and professional title for backward compatibility).
- No database migration is needed; the personal salutation is already captured in the recipient addressing snapshot. Build and correspondence regression tests are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Correspondence editor and appendix work (in progress)

- Implemented the first integrated version of the reusable rich-text editor and replaced the old `execCommand` editor in correspondence creation/editing. Home/Insert and contextual Table/Picture/Shape tools are present.
- Added safe rich-text sanitization for formatting, tables, raster inline images, shapes, and HTTP(S)/mailto links.
- Added optional independent appendix modes: none, text, file, or text + file. Appendix text is stored on the correspondence; appendix files are linked with purpose `correspondence_appendix`. Manual page count entry is removed from the form; legacy `attachment_pages` is retained for existing data/tests.
- Print preview separates main and appendix page sequences. Signature/stamp flow remains within the main-letter section. The automatic template summary reports rendered appendix text pages and attached appendix file count.
- Added regression tests for text-only and combined appendices.
- **Not verified yet:** npm build, PHP feature tests, browser interaction, image/table/shape round-trip, and actual print-to-PDF page alignment. Do not mark this feature complete until those checks pass.


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

## 2026-11-11 — Rich-text editor refinement (in progress / unverified)

- Added compact Word-style dropdown color palettes for text, highlight, table borders, image borders, shape fill and shape outline. The controls include theme/standard swatches and custom color input; text/highlight/shape palettes include clear/no-color actions. Palette triggers display the currently computed color.
- Added drag-to-select table cell ranges without requiring Shift, table left/center/right alignment, and icon-first table selection/merge/split actions.
- Added visible selected-state outlines for images/shapes, aspect-ratio-aware image sizing, rotation and flip controls for images/shapes, shape border thickness, and contextual right-click menus.
- Raised the inline image client cap to 350 KB; the existing request size and server sanitization constraints remain authoritative.
- Not verified: npm build/type-check, drag selection in RTL and spanning tables, transform persistence through HTML sanitization, image load/ratio behavior, context menu actions, palette visual quality, save/reopen, and A4 preview/print. Do not treat these controls as accepted until owner testing.
- Next owner validation starts with `git pull origin development`, then `npm run build`. Development only; master/release/server unchanged.

## 2026-11-11 — TypeScript correction
Fixed Vue `ref` access in table pointer-drag selection: assignments and reads now consistently use `dragSelection.value`. This addresses the reported TS2588/TS2339 errors at the drag-selection handlers. The correction is committed to `development`; build has not yet been rerun and no build success is claimed.


## 2026-11-11 — Editor control feedback corrections (in progress / unverified)

- Fixed outside-click dismissal for color dropdowns and strengthened no-color handling for fills and borders.
- Replaced ambiguous low/normal/high paragraph spacing options with editable numeric line-height and paragraph-after spacing inputs with explicit units/ranges.
- Width/height controls now display dimensions measured from the selected table/cell/image/shape, and table height is independently editable. Image aspect-ratio lock is now a visible checkbox, default off; locked resizing updates the paired dimension and intrinsic ratio is recalculated after image load.
- The code is committed to `development`; no build or browser acceptance has been run. Next validation: `git pull origin development`, then `npm run build`. Manual checks must cover color no-color and outside click, numeric spacing, dimension readback after each resize, and ratio checkbox both off/on. Master/release/server unchanged.


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
