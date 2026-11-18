# V3 code map

Paths are relative to the repository root. Start with the row for the behavior being changed and verify routes and callers in the current tree.

| Area | Entry points and ownership | Tests and data |
|---|---|---|
| Archive V3 | Scope and acceptance are under “V3 — Digital Archive” in `ROADMAP.md`. `archive.index` and `archive.store` → `app/Modules/Documents/Http/Controllers/ArchiveController.php` → `resources/js/Pages/Archive/Index.vue`. The Documents policy protects entry and download. | `tests/Feature/Modules/Documents/DocumentFoundationTest.php`. Search migrations for `add_archive_metadata_to_documents`. Details and version history use `resources/js/Pages/Archive/Show.vue`. Custody transitions are in `app/Modules/Documents/Services/ArchiveCustodyService.php` and approval items in CartableService. Archive scanning uses the installed helper callback with archive-scoped tokens. Category exceptions are managed with `app/Modules/Documents/Services/ArchiveApprovalPolicy.php`. Structured locations use `app/Modules/Documents/Services/ArchiveLocationCatalog.php`. Business record links use `app/Modules/Documents/Services/ArchiveReferenceService.php`. |
| Documents | `routes/web.php` → `app/Modules/Documents/Http/Controllers/DocumentController.php`. `app/Modules/Documents/Services/DocumentService.php` owns file creation, versions, links and grants. `app/Modules/Documents/Policies/DocumentPolicy.php` controls access. | `tests/Feature/Modules/Documents/DocumentFoundationTest.php`, `tests/Feature/Modules/Documents/TaskDocumentTest.php`. Search `database/migrations` for `create_document`. |
| Scan a letter | `resources/js/Pages/Correspondence/Create.vue` → `correspondence.scanner.*` routes → `app/Modules/Correspondence/Http/Controllers/CorrespondenceScannerController.php` → `app/Modules/Documents/Services/ScannerStagingService.php`. Search `scanner_upload_token` in `CorrespondenceController.php` for final attachment. | `tests/Feature/Modules/Correspondence/CorrespondenceHttpTest.php`, search `scanner_upload_token`. Check the callback CSRF exception in `bootstrap/app.php` and the upload-path allowlist in `tools/WindowsScanner/Program.cs` if changing the callback. Helper setup is in `tools/WindowsScanner/README.md`. |
| Cartable | `cartable.index` in `routes/web.php` → `app/Modules/Cartable/Http/Controllers/CartableController.php` → `app/Modules/Cartable/Services/CartableService.php` → `resources/js/Pages/Cartable/Index.vue`. | `tests/Feature/Modules/Cartable/CartableServiceTest.php`. Check source-module authorization when adding an actionable item. |
| Workflow and permissions | `app/Modules/Workflows/Services/WorkflowService.php` and `app/Modules/Workflows/Models/`. Permission registrations are in `database/seeders/CorePermissionSeeder.php`. | `tests/Feature/Modules/Workflows/WorkflowServiceTest.php`. Search `database/migrations` for `create_workflow`. |

Department configuration starts at `archive.departments.index` and `DepartmentArchiveController`. Department ownership and ordinary record visibility are defined in `ArchiveAccessService`. The separate upload page is `Archive/Create.vue`.

Central registration starts at `archive.central.create`. Read scope changes use `ArchiveAudienceService` and `ArchiveVisibilityFields.vue`. Listings and downloads share `ArchiveAccessService`.

Incoming-letter integration starts at `CorrespondenceArchiveService`. The correspondence register action redirects to `archive.create` or `archive.central.create` with `correspondence_id`. Archive forms and the letter details button use the same source association.
Location settings use ArchiveLocationTree.vue and recursive ArchiveLocationNode.vue; DepartmentArchiveController protects occupied document paths during catalog updates.

Reminder entry point: reminders:dispatch in routes/console.php. TaskReminderDispatcher and ArchiveReminderDispatcher call ReminderDeliveryService; operational scripts and activation checks are in tools/Scheduler.
