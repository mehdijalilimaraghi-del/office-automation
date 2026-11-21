# V4 telephony code map

## Confirmed existing entry points

- app/Providers/AppServiceProvider.php conditionally registers TelephonyServiceProvider using config/telephony.php.
- app/Modules/Telephony/routes.php: isolated management and token-report routes. EnsureTelephonyEnabled also protects previously cached routes.
- app/Core/Settings/Services/SystemSettingsService.php: non-secret organization settings.
- app/Core/Users/Http/Controllers/UserController.php and app/Models/User.php: organization users; extension ownership must be explicit.
- app/Core/Departments/Models/Department.php: department context for scoped access.
- app/Modules/Notifications/Services/NotificationService.php: existing notification boundary.
- app/Http/Middleware/HandleInertiaRequests.php: frontend abilities.
- resources/js/Layouts/AuthenticatedLayout.vue: navigation and notifications.
- database/seeders/CorePermissionSeeder.php: permission catalog.
- tests/Feature: SQL Server-backed authorization and HTTP tests.

## V4 implementation and remaining scope

- Implemented: app/Modules/Telephony/Providers/TelephonyServiceProvider.php, routes.php, ModemController, ModemDiscoveryService and Discovery/Modem models. Driver contracts and audio integration remain pending.
- Implemented: resources/js/Pages/Telephony/Index.vue and Discovery.vue. Windows helper and installer: tools/WindowsTelephony. Tests: tests/Feature/Modules/Telephony.
- Pending UI: extensions/phone book, call history, recordings and PBX configuration.
- Dedicated additive migrations and integration tests for the selected supported PBX.
- Feature disablement must avoid registering telephony routes, jobs, listeners or navigation.
- Telephony must not query or require CRM data.

## Decisions needed before integration code

Owner update: PBX host is the existing modem PC, native Windows only; Linux and VM deployment are ruled out. Asterisk-specific deployment assumption is superseded. Evaluate a maintained native Windows provider/build; FreeSWITCH 1.11.4 official Windows package is now hash/signature verified; runtime installation and integration remain untested. Start with Inspect-TelephonyHost.ps1 / Inspect-Computer.cmd and WINDOWS-PBX.md.

The owner has no installed PBX. The modem is a Gnet FM56PCI with LSI PCI-SV92PP Soft Modem driver on another Windows computer. Obtain its capability report, then establish a non-production PBX environment and test the voice path. Do not assume that an Asterisk-compatible distribution exposes the same provisioning API. Read-only discovery comes before any changes to telephone routing.

## Acceptance

Real supported inbound/outbound call tests; authorization for logs/recordings; extension and trunk/queue/IVR behavior; disabled-module isolation; existing module regression checks. Never represent a fake driver as proof of a working PBX integration.

## Controlled audio diagnostic

- tools/WindowsTelephony/VoiceDiagnostics.cs: injectable serial AT engine, G.711 codec, DLE framing, local WAV and conservative acceptance gate.
- tools/WindowsTelephony/VoiceTest.ps1: explicit-consent incoming-call wizard; ModemHelper.ps1 opens it separately from discovery.
- tools/WindowsTelephony/Test-Audio.ps1 and VoiceDiagnostics.Tests.cs: no-hardware session regressions.
- ModemController helper ZIP and Install.ps1 include both runtime dependencies. Local audio results are not silently added to discovery uploads.

- VoiceSettings and CommandTrace in VoiceDiagnostics.cs: complete current-setting snapshots and versioned bounded diagnostics; VoiceTest automatically saves local schema v2 reports. First real owner attempt stopped before answering at current-format parsing; next report must retain that raw response.

- ParseFormat supports the actual descriptive LSI VSM? response, mapping only settable fields. VoiceDiagnostics.Tests includes exact-response session and restore coverage; helper v3 still needs live acceptance.

- RingDetector in VoiceDiagnostics.cs recognizes text and DLE-R ring events; waiting evidence is bounded. VEM/VDR/VLS queries are read-only and optional. Owner v4 report now confirms DLE-R input and local incoming audio acceptance with both human checks and cleanup. Next: PBX bridge, timing/sample-count review and remote-hangup validation; no production line certification.

- Install-PbxEngine.ps1 and Install-PbxEngine.cmd: pinned official core-only Windows engine installer, CheckOnly mode and no automatic line activation. Actual installation is pending on the owner host. Future provider/extension/migration work must support more extensions and another Windows machine.
