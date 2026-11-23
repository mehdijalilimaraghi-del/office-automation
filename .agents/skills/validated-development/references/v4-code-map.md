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

- Install-PbxEngine.ps1 and Install-PbxEngine.cmd: pinned official core-only Windows engine installer, CheckOnly mode and no automatic line activation. Owner diagnostics now confirm core 1.11.4 installed, Manual/Stopped service and MSI 3010 reboot request; owner subsequently confirmed reboot and version display. Future provider/extension/migration work must support more extensions and another Windows machine.

- Installer path binding fix: resolve omitted MSI path in script body, pass explicit path from CMD and preserve exit status. Test-PbxInstallerPaths.ps1 guards Start-Process while testing Persian/spaced paths and missing root context.

- Actual engine binary is FreeSwitchConsole.exe per MSI File table. Collect-PbxInstallDiagnostics.ps1/.cmd gathers current state without repair or reinstall; English failure logs and Test-PbxInstallerReports.ps1 cover post-check failures with test-only doubles.

## Internal-call lab — 2026-11-22

- tools/WindowsTelephony/PbxLab: Configure/Start/Stop, PbxLab.psm1, isolated freeswitch.template.xml, private accounts page and password-free Collect-PbxLabDiagnostics; README.fa.md describes two-client acceptance.
- tests/Test-PbxLab.ps1: 16 checks with actual native SIP/RTP/hangup via NativeSipSmoke.cs. Expand-TrustedEngine.ps1/TrustedMsiCab.cs extract only the pinned engine for tests, never execute MSI.
- Owner reboot/version already confirmed; do not request another check. Next: physical LAN audio acceptance, application provider/ownership and modem timing/bridge. No V4 completion claim.

- Address-change recovery: Get-PbxLabLocalAddress/Sync-PbxLabAddress in PbxLab.psm1; Start uses the original profile with preserved credentials. Test-PbxLabAddress.ps1 covers seven migration and guard checks.

## Extension and IVR administration

- PbxController + PbxConfigurationService + WavePromptValidator: active ownership, private PCM WAV validation, configuration ZIP; extensions/ivr/configuration routes under existing gate. Extension model and 2026_11_22_120000 migration.
- Pages/Telephony/Extensions.vue, Ivr.vue, Components/TelephonyNav.vue; explicit apply workflow, no connection-status claim from database flags.
- PbxLab/Apply-OfficeConfig.*, Normalize-Prompt.cs, Import-PbxOfficeConfiguration and generated native IVR. Tests: PbxConfigurationTest.php, Test-OfficeProvisioning.ps1 and NativeSipSmoke.RunIvr.
- ModemTiming.ps1/Run-ModemTiming.cmd use VoiceDiagnostics sample windows and VoiceTiming; physical timing result precedes a modem media bridge. Owner LAN calls/audio/hangup already accepted; keep SIP Proxy including port.

- VoiceReceiveFraming/VoiceDecoder in VoiceDiagnostics.cs normalize evidence-proven 60/48 lag-10 repeated tails. Tests cover stream splitting, DLE, linear/tone pass-through, changed and partial framing; existing WAV replay recovered 96048 payload samples. Preserve distinction from untested live bridge/fax.

## Controlled incoming modem bridge

- LocalSipAudioEndpoint.cs + IVoiceAudioEndpoint/VoiceSession: local SIP/PCMU, streaming counters, bounded queues, no recording, cleanup. Setup uses Set-PbxModemGateway in PbxLab.psm1 with a dedicated exact-destination context.
- Prepare-ModemBridge.* / Start-ModemBridge.* and MODEM-BRIDGE.fa.md: owner-controlled one-call 60-second lab; package in storage/app/private/OfficeAutomation-ModemBridge, no embedded credentials.
- Test-BridgeLifecycle.ps1, VoiceBridgeLifecycle.Tests.cs, LocalSipAudioEndpoint.Tests.cs: lifecycle and protocol negatives. ModemBridge.Tests.cs + PbxLab/tests/Test-ModemBridge.ps1 + NativeSipSmoke.ReceiveGateway: actual native engine/fake modem/150 ms inband DTMF/phone BYE integration. Physical modem acceptance remains outstanding.

## Owner real-call gate and greeting helpers — 2026-11-23

Owner confirms real incoming two-way voice to 1002; physical greeting/keys/hangup remain pending. Record-IvrGreeting.ps1/PromptRecorder.cs record a bounded explicit WAV independently of the PBX host. Enable-ModemIvr.ps1 selects a supplied WAV or reuses the enabled menu, preserves all accounts/secrets, uses existing conversion/import/gateway functions and restores protected configuration on failure. Test-IvrActivation.ps1 covers routes, extra accounts, secrets, reuse and invalid-file rollback. Windows mixer session mute is a workaround only; do not disable Multiplicity/global sound.

## Controlled transmit level

VoiceTransmitLevel in VoiceDiagnostics.cs plus optional VoiceSession constructor argument: supported/current VGT, post-codec readback, safe restoration and report fields. Start-ModemBridge-NominalVolume.cmd opts into 128; regular mode untouched. VoiceBridgeLifecycle.Tests.cs / DiagnosticFakePort cover actual LSI evidence and failure/cancel/nonmutation/readback cases. Physical loudness acceptance remains pending.

## External playback metering

AudioLevelMeter / SipBridgeMetrics in LocalSipAudioEndpoint.cs provide numeric-only PBX input and unchanged modem-playback levels/fill counters. LocalSipAudioEndpoint.Tests.cs covers known PCMU/silence levels; ModemBridge.Tests.cs compares with decoded serial bytes on the native PBX/fake modem. Owner rejected VGT 128 as quieter; use regular launcher pending hardware evidence, no further guessed gain.

## Optional PCM16 modem format

VoiceCodec.FindPcm16/Pcm16FromMuLaw and VoiceDecoder PCM pairing adapt only serial media; SIP stays PCMU. VoiceSession optional format constructor verifies advertisement/readback and restores existing settings. Start-ModemBridge-Pcm16.cmd / -Pcm16Modem opts into 230400-baud PCM; baseline unchanged. VoicePcm16.Tests.cs/Test-Pcm16.ps1 and ModemBridge.Tests.cs native integration cover conversion, framing, cleanup and both formats. Test-ModemBridge.ps1 -Pcm16 uses a fresh profile per format; real line loudness pending.
