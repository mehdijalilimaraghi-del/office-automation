## Continuous incoming reception — 2026-11-24

Test-IncomingReceiver.ps1 exercises actual native echo with sequential simulated modem calls, media/cleanup/report/stop conditions, idle cancellation, bounded one-hour timing windows and repeated events. Test-IncomingReceiverUi.ps1 executes the real launcher event handlers with controlled sessions/reports: one journal entry, running rearm timer, one next-start action, cleanup/write-failure stops, stop during the rearm gap and a completed-call report preserved when stop precedes the next tick. Native internal-call regression passed16 checks. It opens no modem/PBX. Native Test-ModemBridge -Receiver -LocalGreeting -BargeIn -DspDirectory <verified Dsp> exercises two calls against one Windows PBX. Second-call investigation identified WSAECONNRESET in the simulated phone; its RTP socket now uses SIO_UDP_CONNRESET=false, while strict two-way packet and SIP/BYE assertions remain. Recipient failures cancel/wait for session cleanup before test teardown. All focused audio suites, SQL546/3445 and TypeScript/build passed. No actual24-hour call or physical receiver acceptance is claimed. Microsoft reference: https://learn.microsoft.com/en-us/windows/win32/api/ws2spi/nc-ws2spi-lpwspioctl .

## Graphify workflow and V4 continuation — 2026-11-24

Graphify setup: uv tool install graphifyy --python <supported interpreter>; graphify install --platform codex. Release0.9.118 query imports optional MCP dependencies: install graphifyy[mcp] in the same isolated tool environment if anyio is missing. On this Windows host uv minor-version links failed after Python download; selecting the actual downloaded Python3.12.16 executable resolved installation. Build/update: powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Update-ProjectGraph.ps1; optional -Rebuild. Query from repository root: graphify query "VoiceSession" --budget 1200; graphify explain "ModemLineEcho"; use targeted source reads for returned locations. Initial code-only extraction and report generation passed; no confidential/dependency paths were indexed. Graph limitations:65 symbol-free files and47 self-links, no missing/dangling/collapsed endpoints. Sources: https://github.com/Graphify-Labs/graphify ; https://docs.astral.sh/uv/getting-started/installation/ .

## Controlled modem line-echo trial — 2026-11-24

Test-ModemLineEcho.ps1 checks pinned native DLL at80/512/1536-sample delays and8k/28k reference levels; require >=15dB echo reduction, double-talk error<-12dB and near/onset gain within1.5dB. Independent zero-reference native baseline accounts for the documented DC-notch response; a separate700Hz speech-band level check prevents a muted baseline from passing. Also checks sample alignment/chunks/initial burst/overflow/reset/hash/UNC/disposal. Test-LineEchoSetup.ps1 verifies pinned local staging/tamper rejection/PS5.1 binding and actual native DSP with a fake modem. Test-BridgeLifecycle covers failed-write reference exclusion and cancellation/media-error cleanup. PbxLab/tests/Test-ModemBridge.ps1 -LocalGreeting -BargeIn -DspDirectory <verified Dsp> passes native IVR/RTP/hangup with simulated modem. Full SQL546/3445 and TypeScript/build passed. Prepare-LineEchoDsp.ps1 reproducibly downloads only the official pinned MSYS2 BSD-3-Clause package; binary/archive never enter Git. A default native regression exposed PowerShell null-to-empty prompt coercion in the test harness; treating empty prompt as absent fixed it and the native default test then passed. Hardware echo and simultaneous speech remain pending. References: https://www.speex.org/docs/manual/speex-manual/node7.html ; https://packages.msys2.org/packages/mingw-w64-x86_64-speexdsp .

## Duplex downlink continuity and level diagnosis — 2026-11-23

Test-RtpPlayout.ps1 reproduces old FIFO underrun with a deterministic packet schedule and verifies exact reordered source bytes, duplicate rejection, bounded fading, late rejection, mixed payload durations, timestamp/sequence wrap, initial reorder, single-packet startup, flood bounds, source changes, gap recovery and one-second accounting. Native integration initially failed because same-SSRC timestamps alternated e.g.9440 voice/76800 generated silence; modem-scoped rtp_rewrite_timestamps fixed clock consistency. Native test phone now paces via Stopwatch instead of accumulating Sleep20/processing delay. Normal native call passed79/79 packets, zero late/clock resets and serial meter equality; no hardware claim. Existing protocol/authentication/keypad/audio/PCM regressions and full SQLServer546 tests/3445 assertions passed; TypeScript/build passed. Temporary packet-header tracing was removed. References: https://www.rfc-editor.org/rfc/rfc3550.html ; https://www.rfc-editor.org/rfc/rfc3551.html ; https://developer.signalwire.com/freeswitch/reference/channel-variables/ . Physical downlink continuity and loudness pending.

## Preserve and synchronize modem flow control — 2026-11-23

Flow-control regressions check original 2,2 is kept without any IFC=0,0, original 0,0 remains usable, and mixed/software-flow or host configuration errors stop before answer with restoration. Test-BridgeLifecycle/Test-Pcm16 and existing 14 Test-Audio outcomes passed. Reference source: https://raw.githubusercontent.com/Distrotech/mgetty/master/voice/libvoice/V253modem.c (conditional hardware flow synchronization); https://raw.githubusercontent.com/Distrotech/mgetty/master/voice/libvoice/Lucent.c (model-specific format IDs must not be copied blindly). Manufacturer manual: https://pdc.parastream.com/downloads/reference/AT%20Command%20Set%20for%20Lucent%20chipset%202.pdf . Native/software tests do not certify real CTS timing or greeting loudness.

## PCM timing failure and native driver evidence — 2026-11-23

Test-Pcm16 includes blocked serial output at 35 ms per frame: require timing failure, nonzero elapsed duration, final sample evidence, measured write/lag and acknowledged cleanup. PCM software roundtrip passes but real PCM pilot failed and is not accepted. Native G.711, existing bridge/audio regressions, SQL Server 546 tests/3445 assertions and TypeScript/build passed for this correction. DriverVoiceEvidence/Test-ModemDriverVoiceEvidence.ps1 validates bounded fields, driver-key mapping, endian metadata and privacy filters; actual local COM3 collection reported no matching installed modem with no changes/errors. Owner-host driver profile remains pending. Schema reference: https://learn.microsoft.com/en-us/previous-versions/windows/hardware/modem/ff542435(v=vs.85).

## Optional PCM16 modem path for quiet greeting — 2026-11-23

Run Test-Pcm16.ps1 plus Test-BridgeLifecycle.ps1 and Test-Audio.ps1 in Windows PowerShell 5.1. PCM coverage includes all 256 PCMU amplitudes, LE wire encoding, byte splits/DLE/event alignment, odd terminal byte rejection, 60/48 framing after adaptation, lifecycle, missing codec, mismatched readback and cancellation. Run PbxLab/tests/Test-ModemBridge.ps1 with and without -Pcm16 using separate fresh profiles. A combined same-profile two-call attempt failed with a simulated-phone UDP reset; isolated native runs passed, including actual serial-byte level comparison and DTMF routing. SQL Server 546 tests/3445 assertions and TypeScript/build passed. Real modem baud support, PCM greeting loudness and conversation quality are not certified.

## Nonrecording external-playback level diagnostics — 2026-11-23

LocalSipAudioEndpoint.Tests.cs verifies empty/silent metrics, a known PCMU amplitude and the exact 3.0103 dB total-RMS dilution by half silence while active RMS stays constant. Native ModemBridge.Tests.cs compares playback counts, peak and RMS against DLE-decoded serial bytes captured only in the simulated modem fixture. These counters do not assert analog loudness. No PHP/TypeScript code or dependencies changed; their previously passing suites/build are not rerun for this C#-only instrumentation; focused C# and real native PBX tests are the relevant gate.

## Controlled modem transmit-volume trial — 2026-11-23

Test-BridgeLifecycle.ps1 includes actual LSI gain replies, normal-mode nonmutation, opt-in set/restore, unsupported range, missing snapshot, rejected write, accepted-but-ignored write, cancellation and restore failure while class restoration continues. Test-Audio.ps1 existing 14 diagnostic sessions passed. Actual native PBX integration and existing application regressions are rechecked for this slice; physical output-level acceptance remains separate. Manufacturer background only, not certification of this LSI device: https://multitech.com/wp-content/uploads/plusvoice-command_s000099e.pdf documents VGT zero as automatic control and nominal reference 128.

Validation: SQL Server suite passed (546 tests / 3445 assertions), TypeScript/build passed, native PBX simulated-modem routing/audio/BYE passed, diagnostic/lifecycle/protocol tests passed, and PowerShell constructor binding passed for both modes. Restoration is read back after codec restoration. Physical external-line volume remains unverified.

## Physical IVR and final setup requirements — 2026-11-23

Evidence-only checks: LAN ping succeeded while a password-free SIP OPTIONS probe timed out; EDARII collector showed Public profile (enum 0), correct 192.168.1.101:5160 listener/domain and no registrations. Owner switching to Private fixed registration. Latest physical report confirms pbx_hangup. Original-versus-converted WAV measurement found only 0.1 dB RMS loss. Separate +7 dB PCM comparison retained sample count/duration and had zero clipping, peak -3.07 dBFS. No application code changed and no new PHPUnit/build run was needed for this documentation/audio comparison. Do not infer louder hardware playback until owner tests it.

## IVR helper checks — 2026-11-23

Run Windows PowerShell 5.1 Test-IvrActivation.ps1 for WAV-based activation, both routes, preservation of all credentials including extra accounts, reuse of configured menu/device secret and invalid-file rollback. PromptRecorder.cs compiles on .NET Framework; PowerShell syntax checks pass. The existing native Test-ModemBridge.ps1 passes IVR key routing, bidirectional RTP and PBX-originated release using a simulated serial modem. Recording uses documented Windows MCI waveaudio commands (https://learn.microsoft.com/en-us/windows/win32/multimedia/recording); microphone capture is intentionally not started by automated validation and requires owner acceptance. Do not call these checks a real-line IVR test.

Validation on 2026-11-23: SQL Server regression suite passed (546 tests / 3445 assertions), TypeScript and production build passed; activation including replacement of custom-menu audio and native simulated-modem IVR integration passed. Hardware microphone/real-line IVR remain pending.

## Controlled incoming modem bridge — 2026-11-22

Run Test-BridgeLifecycle.ps1 for PBX/media failure before/after answer, user cancellation, cleanup failure, no recording, bounded queues, local-only endpoints and bad SDP/contact/cancelled-auth protocol cases. Run PbxLab/tests/Test-ModemBridge.ps1 -EngineDirectory <trusted engine> for actual Windows FreeSWITCH plus a real-time fake serial source: repeated-tail framing, wrong-password rejection before answer, inband 150 ms key 1 (697/1209 Hz), IVR route to 1002 with different fallback 1003, two-way RTP, BYE and cleanup. Native test phone must wait for ACK before sending media; early RTP caused Windows UDP reset during investigation. All passed; full SQL Server 546 / 3445 and build passed. Guarded WinForms/CMD render from a Persian/spaced path passed without opening COM or starting a PBX. Physical EDARII testing is still required; packages exclude fixtures, credentials and recordings.

## Modem receive framing correction — 2026-11-22

VoiceDiagnostics.Tests now covers repeated-tail recovery across split serial reads/DLE escaping, ordinary streams, periodic-tone false positives, short streams, changed framing and valid/invalid final partial frames, alongside the existing 14 simulated audio sessions. Private replay of canonical mu-law reconstructed from the owner WAV passed: exact retained payload, raw 120060, removed 24012, output 96048 / 12.006 s. This replay does not prove every original raw serial byte or live modem behavior. Original recording remains unchanged; no new call or serial port access was used.

Validation passed: full SQL Server suite 546 tests / 3445 assertions; TypeScript/build; 14 simulated audio-session outcomes and seven framing regressions; replay of the existing WAV with exact retained payload. The corrected receiver has not yet been exercised on a new physical call.

## V4 extension management and IVR — 2026-11-22

New SQL Server feature tests cover access/disabled module, ownership/duplicates/reserved numbers, valid/corrupt WAV, active IVR destinations, preventing disabling routed extensions, credential-free ZIP and menus without shortcut keys. Test-OfficeProvisioning.ps1 exercises preserved secrets, rejected configuration, converted WAV and actual native internal calls plus IVR greeting/RFC2833 transfer and clean shutdown; failed profiles are retained until owned processes stop. Existing audio simulations pass with new clock checks rejecting missing evidence/10 kHz while excluding a startup burst. ModemTiming.ps1 records a physical 12-second test with per-second sample windows; this physical timing test has not run here. Migration creates only the new extension table and is rollbackable by dropping that table; no old data changes.

Validation: full SQL Server suite passed (546 tests / 3445 assertions), TypeScript/build passed; actual native IVR greeting and key routing passed, plus existing 14 simulated modem sessions and added clock checks. Physical modem timing and human IVR acceptance are still required.

## Native Windows internal-call lab — 2026-11-22

Test-PbxLabAddress.ps1 passed seven checks covering IP migration, preserved passwords/identity, updated XML/account instructions, idempotency, ambiguity rejection and active-engine refusal. Test-PbxLab.ps1 again passed all 16 native SIP/RTP checks. Start/Stop launchers continue using the original protected profile.

Run Windows PowerShell 5.1: tools/WindowsTelephony/PbxLab/tests/Test-PbxLab.ps1 -EngineDirectory <trusted engine directory>. Sixteen checks passed, including private ACL/input rejection/no overwrite/bounded ESL frames and an actual authenticated SIP call with bidirectional RTP/hangup. NativeSipSmoke.cs uses only loopback endpoints, no modem or PSTN. Expand-TrustedEngine.ps1 validates the pinned MSI hash and extracts cabinet files for native testing without executing MSI; 1640 files extracted successfully. Collect-PbxLabDiagnostics reports readiness/registrations/call count only; tested ready=true, two extensions, zero remaining calls. Owner subsequently confirmed two-PC LAN calls/audio/hangup. Package excludes tests, binaries, credentials and generated reports.
Validation for this change: full SQL Server test suite passed (538 tests, 3387 assertions); npm run build passed including vue-tsc; all new PowerShell files parsed successfully. Native integration passed 16 checks. No production database, modem, service, master or release branch was changed.


## Installer diagnostics and executable validation — 2026-11-22



Test-PbxInstallerReports.ps1 exercises mocked MSI success and exit 1603, verifying the real binary name, English errors, failure JSON and copied MSI log. tests/InstallerDoubles.ps1 replaces installer/service operations and is excluded from delivered packages. Existing path tests guard elevation. Run the diagnostic collector on a new/empty output folder and validate its JSON/ZIP; it must not install/repair or change services. The owner machine status and black-screen cause remain unverified. Microsoft Restart Manager reference: https://learn.microsoft.com/en-us/windows/win32/msi/msirestartmanagercontrol .

Validation passed: three mocked installer outcomes (success, MSI 1603, post-check failure); four guarded Unicode/path scenarios; real read-only collector JSON/ZIP creation on development Windows; recursive PowerShell syntax; full SQL Server suite 538 tests / 3387 assertions; TypeScript/build. No real MSI, UAC elevation, repair or service change was performed. Owner diagnostic archive is still required. Small diagnostic-only package: storage/app/private/OfficeAutomation-PbxInstallDiagnostics.zip.

## Installer path binding correction — 2026-11-22

Run Test-PbxInstallerPaths.ps1 -TrustedInstallerPath <official MSI> using Windows PowerShell 5.1. It exercises default and explicit paths, forced PSScriptRoot/PSCommandPath absence with MyCommand.Path fallback, and CMD argument forwarding from a Persian folder with spaces. All child copies block Start-Process and use CheckOnly. The real administrator/MSI installation remains unexecuted.

Validation passed: four guarded Windows PowerShell path/launcher scenarios including Persian/spaced directories and forced missing-root fallback; PS syntax; full SQL Server suite 538 tests / 3387 assertions; TypeScript/build. No MSI installation or UAC process was allowed in path tests. Small patch: storage/app/private/OfficeAutomation-PbxInstaller-PathFix.zip; the complete installer ZIP was refreshed as well.

## Windows PBX engine preparation — 2026-11-21

Run Install-PbxEngine.ps1 -CheckOnly -InstallerPath <official MSI> to validate the package without installation/UAC. Tested using Windows PowerShell 5.1 on development Windows. Actual owner installation uses the separate launcher and Windows Administrator consent, core-only features, logging and stopped-service verification; it was not executed in development. The pinned MSI size is 166219776 bytes. Do not commit installers, owner inventory, audio, install logs or credentials. More extensions and another-host installation/migration must be included in subsequent acceptance.

Validation passed: official MSI size/SHA256 and SignalWire signature; actual read-only MSI product/feature/service-table inspection; installer CheckOnly acceptance and same-size invalid-hash rejection; PowerShell syntax; 14 simulated modem sessions and 10 parser cases; full SQL Server suite 538 tests / 3387 assertions; TypeScript/build. Administrator/UAC installation and runtime activation have not been executed. Offline artifact: storage/app/private/OfficeAutomation-PbxEngine-1.11.4-Windows.zip.

## Native Windows PBX requirement — 2026-11-21

Inspect-Computer.cmd starts Inspect-TelephonyHost.ps1 in Windows PowerShell STA, presenting a JSON save dialog. The NoUI/OutputPath path supports local validation without user interaction. Inventory contains Windows build/architecture, CPU, memory and fixed-drive free space; no modem/network access or system changes. Run syntax and actual Windows PowerShell inventory validation. A development-host report does not establish readiness of the owner modem PC. Native PBX binary and bridge tests remain unavailable until those components exist.

Validation: actual inventory execution and JSON structure checks passed on the development Windows computer (not the modem PC), PS syntax passed, existing 14 audio-session and 10 capability cases passed, full SQL Server suite passed (538 tests / 3387 assertions), and TypeScript/build passed. Local read-only WAV inspection confirms 120060 samples / 8000 Hz = 15.0075 s versus the reported 12.012 s capture window; cause remains unverified and must be resolved before a live bridge.

## Voice-mode inbound ring — 2026-11-21

Audio diagnostics cover fourteen fake-port outcomes, with DLE-R session acceptance, non-ring timeout evidence, bounded-input overflow, optional-query rejection and parser negatives for lowercase ringback/escaped DLE/lookalike text/caller-ID/numeric data. Capability cases and PS syntax remain required. Real hardware retest uses OfficeAutomation-ModemAudioTest-v4.zip; wait evidence may include caller-ID data, so keep reports local and out of Git.

Validation passed: 538 SQL Server tests / 3387 assertions, TypeScript/build, fourteen fake-port audio outcomes and ring/parser regressions, 10 capability-parser cases, PS syntax and fake-port GUI/automatic JSON evidence checks. Offline artifact: storage/app/private/OfficeAutomation-ModemAudioTest-v4.zip. No real call was made from this computer.

## Descriptive modem format correction — 2026-11-21

Audio diagnostics now cover eleven simulated session outcomes, including the exact owner VSM? response and restoration command AT+VSM=0,8000,128,0. Parser cases cover other codec labels, numeric compatibility, range listings, incomplete/failed replies and conflicts. A fake-port GUI test verifies the mapped values and helper version in automatically saved JSON. Physical testing uses a fresh extraction of OfficeAutomation-ModemAudioTest-v3.zip; no real call was run here.

Validation passed: 538 SQL Server tests / 3387 assertions, TypeScript/build, 10 capability-parser cases, eleven simulated audio sessions and parser regressions, PowerShell syntax, and fake-port GUI/automatic JSON verification using the actual owner reply. Live call acceptance remains pending.

## Modem preflight diagnostics — 2026-11-21

Windows audio regressions now cover ten simulated session outcomes, complete four-value restoration, ambiguous/invalid setting rejection, raw/rejected/partial reply retention and cancellation during streaming. GUI verification checks the Persian failure stage and parses an automatically saved v2 JSON report using a fake port. No new real-modem call was made from this computer; the owner's first report stops during preparation. Use a fresh extraction of OfficeAutomation-ModemAudioTest-v2.zip and close VentaFax before physical retesting.

Validation passed: 538 SQL Server tests / 3387 assertions, TypeScript/production build, 10 Windows capability-parser cases, ten simulated audio sessions plus settings/trace regressions, PowerShell syntax and fake-port GUI/automatic JSON verification. Offline package: storage/app/private/OfficeAutomation-ModemAudioTest-v2.zip. Live modem acceptance remains pending.

## Controlled modem audio test — 2026-11-21

Run tools/WindowsTelephony/Test-Audio.ps1 in Windows PowerShell 5.1 for codec, DLE framing, WAV and five simulated session outcomes without opening hardware; also run Test-Capabilities.ps1. The voice wizard was rendered without arming a call. Physical acceptance must use Run-Offline.cmd on the modem PC, explicit consent, an incoming mobile call, playback and both hearing confirmations. Preserve the exported JSON/WAV locally; do not commit call audio or report identities. AT command references: https://ads.usr.com/support/5637/5637-ug/ref_voice.html and https://multitech.com/wp-content/uploads/s000468.pdf .

Full regression command used: php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --no-progress (test database only); 538 tests / 3387 assertions passed after the initial 128 MB artisan run exhausted memory. Focused modem HTTP tests: 6 / 61 passed. TypeScript/build, parser cases, audio fixtures, PS syntax and simulated GUI result display passed.

## Equipment compatibility policy

Compatibility must be assessed automatically by the selected equipment adapter, with a human-readable result and reason. Windows modem, FXO gateway and SIP-trunk paths must remain distinct; only the modem discovery adapter is currently implemented. Initial modem queries now run on selection. A positive voice advertisement remains a candidate until an explicit, controlled two-way call test passes; uncertain results must not be presented as proven compatibility or universal incompatibility.

## LSI modem capability report

Regression coverage uses the sanitized actual LSI FCLASS response and negative class/error/version cases. The owner report is read-only evidence, not executable instructions. No call, voice-mode change or physical audio test has been performed.

## V4 modem discovery — 2026-11-21

Enable TELEPHONY_ENABLED only in a controlled development environment. Apply the additive telephony discovery migration. Test disabled-route isolation, manager authorization, token expiry/reuse/revocation, candidate freshness, report limits and ZIP origin binding. Windows helper supports -DiscoverOnly and interactive -Offline modes; actual LSI/Gnet voice and COM-port behavior remain untested until the owner runs it.

## V4 discovery and release closeout — 2026-11-21

Use the V4 code map for focused file discovery. Asterisk integration requires an identified non-production endpoint and real call acceptance; neither is currently established. No PBX connection, configuration mutation or live telephone changes have been performed.

## Approved release preparation

Owner verified notification audio and authorized promotion to master and server deployment. Temporary sound-test controls removed; automatic native audio and conditional browser sound activation remain. Verify release identity, additive migrations, backup and rollback before activation.

## Notification audio playback diagnostics

Verify the WAV asset loads, native media currentTime advances and ended fires after a trusted click, automatic playback follows interaction, polling does not replay it, and both notification menus expose a sound test. Muted browser tabs and physical speaker output require manual acceptance.

## Reminder sound and follow-up navigation

Verify legacy absolute localhost follow-up URLs on a browser using another port, new task-card anchors, top banner placement, read-state update, explicit sound activation and automatic sound after interaction. Actual audible playback needs human acceptance.

## Scheduled task and archive reminders

Run reminders:dispatch only in controlled development/test contexts. Laravel schedule runs it every minute without overlap. tools/Scheduler contains a runner, explicit installer and operational verification steps; activation must use the stable application junction and a database-capable service account. Test repeated dispatch, edited due time, cancelled/finalized/inaccessible reminders, return/recheckout, escalation and UI notification display.

## Hierarchical archive locations

Verify tree hydration from existing paths, adding two folders under a shared shelf, duplicate sibling names, incomplete locations and rejection of occupied-location removal. Check desktop and mobile layouts.

## Incoming-letter archive registration

Verify incoming registration redirects, postponed archiving via the letter button, prefilled category and manual override, duplicate POSTs, all original/attachment files, and confidential access. Apply the additive correspondence/archive-link migration locally.

## Central archive and document audiences

Verify central registration as the central department manager, publication as a responsible archivist, and viewing as a user without unit membership. Check selected-role deactivation and scope reduction on both the list and direct downloads. Browser QA passed for the central list, registration and audience editor at desktop and mobile widths; physical NAPS2 hardware acceptance remains separate.

## Department archive workflow

Verify the workflow with a manager without global permissions, a delegated archivist, a same-unit member and an outsider. Browse, registration and department settings are separate pages. Apply the two additive unit-archive migrations locally before opening these screens.

## V3 — Central and departmental archives

Before a new workflow build, document roles, tasks, page boundaries, validation, empty states, errors and success. Verify separate registration, browse, details and settings routes at representative RTL viewport widths.

## V3 — Archive access

Apply the V3 migrations before opening Archive on a development database. Test reader, uploader, owner with delegation permission, and revoked user separately.

## V3 — Locations and business references

Test archive location selection during intake and moves, and link each supported record type. Use a user with document access but no target access to verify the reference is hidden.

## V3 — Category-based checkout approval

Verify both mandatory and exempt categories. Check that settings permissions protect edits, and a policy change leaves already-registered requests unchanged.

## V3 — Scan, versions and custody

Exercise Archive/Index direct scan and Archive/Show version history and physical custody. Use a second authorized user to verify the Cartable approval and confirm the physical return location. The archive callback uses the installed NAPS2 helper path.

## V3 — Archive registration

To verify archive registration, run DocumentFoundationTest and the full isolated SQL Server suite. In the browser, open the archive from the Work menu and verify physical and digital upload, filters, pagination and download as the owner and a second user.

## V3 — Archive scanning

For scanner changes, run the correspondence scanner HTTP regression and document tests. Verify the actual Windows NAPS2 scan, preview and draft save with a physical scanner before accepting the workflow.

## 2026-11-17 — Save feedback and local text sample migration

- If the text sample table does not exist, apply the existing body templates migration on the intended environment. ProfileTest covers default save feedback, delivery once, and no success on validation failure.

## V1 and V2 acceptance fixes

- The additive correspondence body template migration must be applied for template storage. Use the release-readiness.html and correspondence-form.html browser fixtures for the new flows.

## 2026-11-16 — Draft preview and scrollable correspondence text

- The protected print preview regression exercises assigned-manager access, denial for unrelated viewers and asset suppression for saved drafts and unsaved previews.

## 2026-11-16 — Editable appendix labels

- Appendix text is covered by OrganizationSettingsTest and the template pagination browser suite, including customised and intentionally empty values.

## 2026-11-15 — Independent appendix numbering and designer preview switch

- Set EDITOR_TEST_PAGE to /tests/Browser/template-pagination.html (append ?size=A5 for A5) and run node tests/Browser/run-editor-images.mjs. Set EDITOR_TEST_MEDIA=print for actual Chromium print-media CSS; unset it for screen checks. The test entry starts after media emulation is enabled, uses real application CSS and Vue pages, and verifies independent formats/positions/fonts/totals, toggle persistence, letter-only content isolation, output/designer coordinate parity and visibility. All seven scenarios passed in the four size/media combinations. PHPUnit covers saving/rendering, invalid format rejection, read-only legacy defaults, explicit removal and old save payloads. Physical print/PDF comparison is still a separate owner acceptance step.

## 2026-11-15 — Reliable preview transport and external-unit manager notice

- For preview/form browser coverage, set EDITOR_TEST_PAGE to /tests/Browser/correspondence-form.html and run node tests/Browser/run-editor-images.mjs; unset that variable to run the default 11 editor-image cases. The seven form cases use the actual Vue/Inertia form and native POST navigation with a local synthetic token/preview server: fresh and repeated tokens, popup/missing-token failures, no-manager notice, SQL Server string manager IDs, absent page props/old route metadata and successful retry without clearing input. PHPUnit additionally checks live token changes, no-store headers, guest/inactive access and manager metadata in create/edit responses. These test fixtures never connect to real application data.

## 2026-11-15 — Automatic editor image preparation and clipboard import

- Run node tests/Browser/run-editor-images.mjs for 11 headless browser scenarios using existing Vite/Vue packages and a dedicated temporary browser profile. It defaults to installed Windows Edge; set EDITOR_TEST_BROWSER to another Chromium executable if needed. Coverage includes large-image preparation/transparency, HTML/RTF/VML and bitmap clipboard paths, missing-image messages, aggregate size, active-markup removal, actual editor file/Paste integration, processing notifications, caret/focus and undo/redo. PHPUnit regression test_editor_images_survive_save_and_reopen_in_body_and_appendix verifies both fields survive persistence and unsafe image attributes are stripped. Synthetic browser clipboard tests do not replace manual copying from the actual Word installation.

## 2026-11-15 — Outgoing preview session-token correction

- The outgoing preview regression explicitly enables CSRF validation by overriding only the middleware testing bypass. It verifies the current token in an Inertia navigation response, stale-token rejection (419), valid-token preview rendering and zero persisted correspondences. Focused test: --filter=test_outgoing_preview_uses_current_session_token_and_does_not_save_a_letter. Full validation uses the isolated SQL Server invocation documented below; browser verification must include navigation after login.

## 2026-11-15 — Verified Windows SQL Server test invocation

Use the actual checkout `D:\KPMM\Office-automation`. Before any RefreshDatabase tests, verify the effective environment is `testing`, the driver is `sqlsrv`, and the connected database returned by `SELECT DB_NAME()` is `office_automation_test`. Never point this invocation at production.

This workstation's full suite exceeds the default 128 MB PHP memory limit. Use a process-only override; no php.ini edit is required:

```powershell
# Use only with the verified, isolated localhost test connection.
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlsrv'
$env:DB_HOST = 'localhost'
$env:DB_PORT = '1433'
$env:DB_DATABASE = 'office_automation_test'
$env:DB_URL = ''
$env:DB_USERNAME = ''
$env:DB_PASSWORD = ''
$env:DB_ENCRYPT = 'yes'
$env:DB_TRUST_SERVER_CERTIFICATE = 'true'
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --no-progress --stop-on-error
```

Empty credentials here use this workstation's Windows authentication. Certificate trust is scoped to the local test process with its self-signed SQL Server certificate; do not propagate it to production settings. The direct PHPUnit entry point runs the complete suite in phpunit.xml without Artisan spawning another PHP process with the original memory limit. The verified result was 493 tests / 2446 assertions. Browser/physical printing remains a separate acceptance gate.

## 2026-11-15 — SQL Server CI driver prerequisite

- The first SQL Server CI connection attempt confirmed that enabling PHP's `pdo_sqlsrv` extension alone does not install Microsoft's ODBC runtime. The Laravel test job now installs Microsoft ODBC Driver 18 from Microsoft's Ubuntu 24.04 package feed before configuring PHP SQL Server extensions.
- Re-run the workflow and verify the isolated database connection and full PHPUnit suite before marking backend CI operational.

## 2026-11-15 — Frontend CI dependency completion

- The frontend CI job now installs Composer dependencies with scripts disabled before running `vue-tsc` and Vite, because the TypeScript resolver imports Ziggy declarations from `vendor/tightenco/ziggy`. This keeps the generated/vendor type dependency available without executing Composer project scripts in the frontend job.

## 2026-11-15 — CI compatibility corrections

- GitHub Actions exposed two pre-existing environment mismatches: the npm lockfile did not contain all dependencies declared by `package.json`, and the locked Symfony 8.1 packages require PHP 8.4.1 or newer.
- Regenerated and committed `package-lock.json` from the existing manifest without changing declared package versions. The CI frontend job now uses reproducible `npm ci`; the temporary lockfile-export diagnostic step has been removed.
- Updated the CI PHP runtime to 8.5 to match the repository's current development runtime and satisfy the locked Symfony package requirements. The Laravel job uses an isolated SQL Server 2022 service.
- The initial CI run failed as expected on these mismatches; the corrected workflow must complete successfully before this setup is considered validated. npm reported dependency advisories during installation; no automatic audit fixes or dependency upgrades were applied.

## 2026-11-15 — Codex local workflow and GitHub CI preparation

- Updated `AGENTS.md` to reflect the repository's current modular office-automation scope, enforce development-branch-only work, and define validation, diff-review, documentation, and automatic-push gates for Codex.
- Added the repository-local Codex skill at `.agents/skills/validated-development/SKILL.md`.
- Added `.github/workflows/ci.yml`: Laravel PHPUnit tests run against an isolated SQL Server service; the frontend job installs the lockfile dependencies and runs the Vue type-check/production build. The workflow is limited to pushes and pull requests targeting `development`, plus manual dispatch.
- Exposed SQL Server encryption and certificate-trust options through environment variables in `config/database.php`; defaults preserve encrypted connections and certificate validation.
- The authorized automatic push is performed by local Codex only after local validation and diff review. GitHub Actions runs after the push as a second verification layer; it cannot gate a direct push retroactively. The `development` branch was observed unprotected, and no branch-protection settings were changed.
- Workflow execution has not yet been observed. Do not treat CI, PHPUnit, or frontend build as passed until GitHub Actions reports successful runs. Browser and physical printer acceptance remain separate.

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

- Chromium lays out the document inside the `@page` printable rectangle, not the physical A4 dimensions. The previous `width:auto` coordinate plane therefore shrank with left/right margins, distorting percentage-based template positions and widths. Print CSS now restores a 210 × 297 mm plane and offsets it to the physical page origin using configured right/top margins; body flow stays inside the printable content box.
- Verify in Chromium with A4, 100% scale, browser headers/footers disabled, and no additional custom browser margins layered over the CSS-defined template margins. Build, focused PHP tests, and PDF acceptance remain pending. Development only; master/release/server unchanged.

## 2026-11-09 — Print coordinate-origin mismatch correction

- The print containing block starts after the `@page` top margin, unlike the screen A4 card whose origin is the physical page top. First-page positioned meta/heading elements were therefore displaced downward in print. Added a print-only negative top offset to the coordinate plane, based on the effective top content margin; body flow remains anchored to the printable content area.
- Requires local build, focused correspondence PHP test, and Chromium print/PDF comparison. Development only; master, release and server unchanged.

## 2026-11-09 — Page-number coordinate reconciliation and organization postal code

- The page-number mismatch was caused by different coordinate systems: screen previews used raw A4 percentages, while Chromium's print counters are rendered in page-margin boxes. A counter placed inside the body region was moved to the nearest top/bottom print margin only in actual print. Both screen previews now emulate the same margin-box segment, vertical boundary, horizontal anchor/padding and alignment. The printed counter also honors the configured font family, size, weight and format-specific bidi direction instead of hard-coded 9pt Tahoma/LTR preview behavior.
- Page counters are constrained to the reserved top/bottom print margins. If configured in the body area, they snap to the nearest margin boundary. The designer now explains this behavior.
- Added optional organization postal code across settings form, validation, settings persistence, template sample data, repeatable header/footer/legacy-repeat token lists, and print token resolution as `{{postal_code}}`. Older document identity snapshots fall back to the current organization postal code when the snapshot has no non-empty value.
- Regression assertions cover postal-code persistence and print props. PHP tests, frontend build, and Chromium print acceptance are pending owner execution.
- Development only; master, release branch, and server remain unchanged.

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

## 2026-11-04 — Template preview uses semantic document flow

- The A4 editor preview now lays out metadata, addressee/subject, the automatic body placeholder, issuer/signature and page-number indicator in document flow. Non-repeating roles no longer use fixed Y coordinates in the preview.
- Repeating elements remain page-positioned. The preview shows sequence and approximate occupied space over the actual letterhead background; the final body length and issuer position are determined by the letter's real HTML and print pagination.
- The current `development` feature test fixture no longer expects the removed `height` properties. The reported local output still contains them in its expected array, so that run is from a stale local revision. Pull before rerunning.
- Validation remains pending: focused tests, `vue-tsc`, production build and multi-page print acceptance. Development only; master/server untouched.

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

## 2026-11-04 — Template designer scope update

This entry supersedes earlier first-slice notes that described stamp/signature elements as future work: the visual A4 editor now supports text, organization-stamp and manager-signature element kinds, and print preview binds the latter two to correspondence-specific snapshot assets. Creator and recipient/letter metadata tokens are also available. Configurable body/recipient/footer flow zones, repeating headers/footers, snapping/guides/zoom, and local validation remain open.

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

## 2026-11-04 — External unit owns manager and addressee data (implementation follow-up)

- The four addressee components are owned by `ExternalDepartment` only: organizational title prefix, organizational position, personal salutation, and professional title. This applies only to external organizational units; internal Core Department manager selection and behavior are unchanged.
- External contact create/edit UI and request validation now collect only the person's identity/contact details. Legacy contact-level addressing/default columns are retained for historical compatibility and migration fallback, but are no longer exposed as current input or list metadata.
- An external unit selects one active manager from contacts belonging to that same unit. Service-level guards prevent deactivating or moving an assigned manager until the unit manager is changed; unit update validates manager ownership.
- Outgoing finalization snapshots the unit's addressing fields and selected manager's name/ID, without requiring an individual contact selection. Legacy contact values remain a fallback for older data.
- Regression coverage added for manager assignment integrity and outgoing recipient snapshot. Local migration, focused tests, TypeScript/build and manual acceptance are still pending; do not claim validation until owner reports results. Development only; master and server untouched.

## 2026-11-04 — Fix correspondence controller parse error — validated

- Owner confirmed PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` all pass after pulling the fix. This validates the syntax correction and focused regression suites only; full suite, frontend build and end-to-end acceptance remain separate gates.
- Root cause of the widespread HTTP 500/test failures: the `createDraft()` attribute array in `CorrespondenceController::store()` had a malformed key (`'reply_to_id => ...`) introduced while wiring multi-letter relations. PHP could not parse the controller, so unrelated routes/tests also failed when Laravel attempted to load routes/controllers.
- Corrected the key to `'reply_to_id' => ...`; retained the existing first-related-letter fallback and source-project correspondence fallback. No workflow, validation, authorization, or database behavior was changed.
- Existing `CorrespondenceHttpTest` and `ExternalPartyControllerTest` cover the affected route-loading and create/update behavior. Validation completed by the project owner: PHP lint, `ExternalPartyControllerTest`, and `CorrespondenceHttpTest` passed.
- Development only. `master`, release branch, migrations, and server are untouched.

## 2026-11-04 — Direct correspondence registration follow-up

- Fixed the create-page contract: `Correspondence/Create` now receives the per-type `approvalSettings` used by its submit label and explanatory text. Previously this prop was omitted on the create action (although it was supplied by the edit action), so a type configured for direct registration could still display «ذخیره پیش‌نویس».
- Made the server's immediate-registration decision explicitly use the same `CorrespondenceCompositionSettings::requiresApproval(type)` policy instead of inferring the decision from the created record's workflow state. When approval is disabled for the selected type, the create request invokes numbering/final registration immediately; approval-enabled types remain drafts assigned for approval. Reply/request-generated correspondence remains on its dedicated workflow.
- Added an HTTP regression test asserting the create page receives the direct setting and a direct internal letter is persisted as registered in the initial create request, not as a draft.
- Validation pending on the project owner's machine. Development only; master and server untouched.

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
# Office Automation System — Development Guide

## 1. Version-Oriented Development

The product roadmap is versioned in ROADMAP.md. That file is the authoritative source for product scope and version boundaries.

Current state:
- V1 — Foundation & Work Management: implementation scope complete.
- Current checkpoint: V1 technical/debugging and manual UI/functional acceptance.
- Next version: V2 — Office Automation.
- V2 must not start until V1 acceptance is closed.

Version order:
1. V1 — Foundation & Work Management
2. V2 — Office Automation
3. V3 — Collaboration & Planning
4. V4 — Customer & Commercial Operations
5. V5 — Procurement, Inventory & Assets
6. V6 — HR & Internal Collaboration
7. V7 — Intelligence, Automation & Integrations
8. V8 — Production & Enterprise Readiness

Normal development loop:
**Inspect → Root Cause / Boundary Review → Smallest Coherent Change → Test → Build → Manual Acceptance → Documentation/Status Update → Next Item**

### Engineering Rules
- Inspect the GitHub repository and persistent project documents first.
- Make reasonable architectural decisions directly when user approval is not required.
- Do not wait for confirmation for ordinary implementation/debugging work.
- Keep Modular Monolith boundaries intact.
- Use standard Laravel/PHP/Vue/TypeScript patterns and production-quality code.
- Add or strengthen tests for meaningful behavior and regressions.
- Fix root causes rather than masking symptoms.
- Avoid unnecessary refactors, packages and architectural changes.
- UI/UX should follow modern Google Material Design principles while respecting Persian RTL requirements.
- Record meaningful implementation decisions and completed work in persistent documentation.

### V1 Completion Gate
- Relevant backend/regression tests pass.
- Frontend production build passes.
- RTL/Persian/Jalali UI is reviewed where applicable.
- Critical user journeys are manually accepted.
- Known runtime defects are fixed at root cause.
- Documentation matches repository reality.
- No V2 implementation starts before this gate closes.

## 2. Git Workflow

Git is part of the project from the beginning.

Commits should be:
- Small
- Focused
- Meaningful
- Related to one logical change

Active development branch: development

Stable branch: master

Do not develop directly on master.

## 3. Change Workflow

Before every meaningful change:
1. Inspect the relevant existing implementation.
2. Identify the root cause or requirement.
3. Make the smallest coherent change.
4. Add/update relevant tests.
5. Inspect the result.
6. Update documentation/status when needed.

For local validation, the user runs the project on their own computer. After code changes, provide the exact PowerShell validation command.

Only include `git pull origin development` at the beginning of a validation command when code changes were made immediately before that validation. Do not include it when no code change was made.

Prefer one validation step at a time.

### Correspondence approval test fixtures

When a test enables `correspondence.approval.internal=approval` and creates an internal correspondence through the HTTP endpoint, its creator must belong to a primary active department whose `manager_id` references an active user. The approval workflow intentionally rejects the request if no active department manager can be resolved. Draft-persistence tests must build this fixture rather than bypassing the manager requirement.

## 4. Troubleshooting

- Fix the root cause.
- Do not change unrelated variables at the same time.
- Do not weaken tests, remove security controls, or use destructive database changes to hide an error.
- Inspect environment differences before changing application architecture.

## 5. Dependencies

A new Composer/NPM package requires a concrete architectural reason and compatibility review. Avoid unnecessary dependencies.

## 6. Database and Security Rules

- SQL Server is the target database.
- Schema changes use migrations.
- Existing shared migrations are not rewritten to hide development problems.
- Backend authorization is mandatory.
- File uploads require validation, safe generated names, controlled storage and authorization.

## 7. Frontend Rules

- Vue 3 + TypeScript + Inertia.js is the frontend architecture.
- Keep components focused.
- Use reusable components only where reuse is meaningful.
- Keep business authorization on the backend.
- Respect Persian RTL and Jalali presentation requirements.

## 8. Documentation Authority

- ROADMAP.md — product versions, scope and acceptance gates
- CURRENT_STATUS.md — concise current checkpoint and immediate blockers
- PROJECT_CONTEXT.md — persistent detailed context
- ARCHITECTURE.md — architecture and module boundaries
- PROJECT_SPEC.md — product/system specification
- DEVELOPMENT.md — engineering workflow

Meaningful requirements, architecture decisions, implementation status and blockers must be recorded in the appropriate project document.

## 9. Definition of Done

A change is complete only when intended behavior is implemented, relevant tests are added/passing, authorization and validation are correct, no known regression is introduced, and documentation is reconciled when the change affects project scope/status.

Current environment target: Windows + PHP 8.5.x + Composer 2.10.x + Node.js 24.x + NPM 11.x + Laravel 12.x + Microsoft SQL Server.




## 2026-11-04 — Phase 3 output architecture decision gate

The active product version is V2 — Office Automation, which is authorized and underway according to `ROADMAP.md`. Any older wording in this guide describing V1 as the current checkpoint / V2 as not yet started is historical and must not override the roadmap or `CURRENT_STATUS.md`.

Before implementing the phase-3 shared letter output, inspect and reuse existing Core organization settings for organization details, existing Department manager ownership, and the Documents storage abstraction. The repository currently has no PDF-rendering dependency and no correspondence print/PDF route or print-specific page. The owner must choose whether phase 3 uses browser-native Print / Save as PDF over one shared HTML/CSS A4 template, or a server-side PDF renderer for downloadable generated PDFs. Do not silently introduce a PDF package or external binary. Keep stamp and per-department manager signature as distinct assets, use controlled access, and preserve finalization-time snapshots for historical letters.


## 2026-11-04 — Phase 3 implementation slice 1

The owner selected browser-native Print / Save as PDF from a shared HTML/CSS A4 template. Do not add a server-side PDF dependency without a new explicit requirement. Store the organization stamp in Core organization settings and the manager signature on each Department (not on User). A manager change must deactivate the old unit signature until replaced. Both are private, separately managed assets. Snapshot creator/unit details and the exact stamp/signature files at outgoing-letter finalization; only include the manager signature when workflow approval authorizes it. The new registered-letter print preview provides a per-operation letterhead toggle and independent visibility settings. Local PHP tests, migration, TypeScript/build and physical print pagination remain pending; do not report them as passed until owner confirmation.


## 2026-11-04 — Letterhead and settings navigation

Organization Settings now has three tabs. A separate organization letterhead image can be uploaded and previewed; it is snapshotted per registered letter and placed through a dedicated image element in the A4 template editor. The template persistence regression compares decoded JSON after normalizing key order. Upload/preview and snapshot coverage has been added. Local validation remains pending.


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

## 2026-11-09 — Correspondence print single-origin layout refactor

- Removed the previous print model that combined horizontal `@page` margins with a negative right offset on the absolute coordinate plane. Print `@page` now reserves only the effective vertical safe areas; left/right CSS page margins are zero.
- The print paper, absolute template coordinate plane and normal-flow container use a full 210 mm A4 width. The normal-flow container applies configured left/right margins as padding once, matching the screen preview's physical x boundaries without shrinking percentage-based template coordinates.
- The absolute plane is shifted upward by the effective top safe area to restore physical A4 top alignment. Body flow remains normal and is paginated by Chromium, with top/bottom `@page` margins preserving header/footer clearance on every page.
- Validation sequence: `git pull origin development`, `npm run build`; then `git pull origin development`, `php artisan test --filter=CorrespondenceHttpTest`; then Chromium PDF comparison using A4, 100% scale, browser headers/footers off and default margins. Compare first and later pages with asymmetric left/right margins and non-default header/footer heights. Build, PHP tests and PDF acceptance are pending owner execution. Development only; master/release/server unchanged.

## 2026-11-09 — Outgoing dispatch stage and recipient title prefix

- The existing external tracking columns are reused; no duplicate schema was introduced. Outgoing letters in `awaiting_dispatch` are now included in the `needs_action` state filter for all active Secretariat members, fixing the hand-off that previously made the newly registered outgoing letter disappear from the actionable list.
- Final dispatch now requires both a dispatch method and a sent date while the letter is in `awaiting_dispatch`. The workflow service independently enforces the same invariant before recording `dispatched`; external registration number/date remain optional tracking metadata. While awaiting dispatch, only Secretariat members/system administrators may edit this stage; creator/task-owner tracking permissions resume outside that state.
- Added `{{recipient_organizational_title_prefix}}` to the addressing element token allow-list, designer token catalog/sample values, and print resolver. It reads the immutable `organizational_title_prefix` captured from the selected external contact at registration.
- Frontend build, focused correspondence tests, and manual Secretariat workflow verification are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Recipient personal title prefix token

- Added `{{recipient_personal_title_prefix}}` to the addressing/heading element token catalog. It resolves from the external contact's immutable `recipient_addressing_snapshot.personal_salutation` value in print, independently of `{{recipient_title}}` (which continues to combine personal salutation and professional title for backward compatibility).
- No database migration is needed; the personal salutation is already captured in the recipient addressing snapshot. Build and correspondence regression tests are pending. Development only; master/release/server unchanged.

## 2026-11-10 — Correspondence editor and appendix validation notes

- New migration: `2026_11_10_000001_add_appendix_content_to_correspondences_table.php`.
- New editor component: `resources/js/Components/CorrespondenceRichTextEditor.vue`.
- Main form now posts `appendix_mode`, `appendix_body`, and `appendix_files[]`. `appendix_files[]` accepts the same document types and 20 MB per-file limit as the existing supporting document uploads, with a maximum of 10 appendix files.
- The main letter body and appendix body accept up to 500 KB of sanitized rich-text HTML. Inline images are limited in the editor to 180 KB each and PNG/JPEG/WEBP only.
- Run migration and tests after pulling. Then run the frontend build and manually verify Home/Insert/contextual tabs, save/reopen, table editing, image insertion/resize/alignment, shapes, and links. Finally print a multi-page main letter with stamp/signature plus a multi-page text appendix and attached files; confirm issuer assets remain at the end of the main letter, appendix starts on a new A4 sheet, repeated template elements remain, and preview pages match printed PDF pages.
- No build, PHP test suite, or browser/PDF acceptance has been run for this change yet.


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

## 2026-11-11 — Editor refinement verification notes

The current development branch includes compact color dropdowns, current-color indicators, drag selection for table ranges, table alignment, image/shape selection outlines, image aspect-ratio sizing, rotation/flips, shape outline thickness, contextual right-click menus, and a 350 KB client-side inline image cap.

Before acceptance:
1. Run `git pull origin development` and `npm run build`.
2. Verify no TypeScript/Vue template or icon-import errors.
3. Test palette open/close, theme/standard/custom colors, clear/no-color and current-color preview for every target.
4. Drag across table cells without Shift; test RTL, multi-row, rowspan/colspan and selection persistence.
5. Test image ratio lock by editing width then height; rotate and flip images/shapes; save, reopen and confirm styles survive sanitization.
6. Verify 350 KB inline images against request validation and persistence; do not increase the limit without validating base64-expanded payload size.
7. Test custom right-click menus and every action; compare long content, tables, images and rotated objects in A4 preview and printed PDF.

No build or browser acceptance is claimed by this entry. Work remains on development only.

## 2026-11-11 — TypeScript correction
Fixed Vue `ref` access in table pointer-drag selection: assignments and reads now consistently use `dragSelection.value`. This addresses the reported TS2588/TS2339 errors at the drag-selection handlers. The correction is committed to `development`; build has not yet been rerun and no build success is claimed.


## 2026-11-11 — Editor property controls validation

Latest development-only editor follow-up addresses no-color behavior, outside-click dismissal, ambiguous paragraph spacing, and incorrect dimension readback. Validate in this order:
1. `git pull origin development`
2. `npm run build`
3. In the editor, open each color palette, click outside to dismiss it, then repeat by selecting a swatch; test no-color for text highlight, cell fill, table border, image border, shape fill and shape outline.
4. Edit line-height multiplier and paragraph-after spacing numerically; confirm the labels/units and saved result.
5. Select a table, cell, image and shape; compare displayed width/height with their rendered dimensions. Resize and confirm the corresponding fields refresh. Verify table height separately.
6. Confirm image ratio checkbox is initially unchecked. Resize width and height with it off, then enable it and repeat; only locked mode should recalculate the paired dimension.
7. Save/reopen and verify all styles, then compare A4 preview and printed output.

No build, browser, persistence or print acceptance is claimed yet. Keep all work on `development`; do not touch master/release/server.


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

## 2026-11-11 — Outgoing preview and sticky toolbar verification

Implementation notes:
- The preview button belongs beside the bottom form actions and is visible only when form.type is outgoing.
- Preview data is posted as a JSON form field with the page CSRF token to correspondence.preview, targeting a newly opened tab. The controller validates permission, allowed department, and external organization/department/contact relationships, then renders an unsaved model through the same print props as persisted letters.
- Preview file content is not embedded. The print page warns when the composition mode includes uploaded files.
- The editor's contextual tab strip and active toolbar are inside .rte-toolbar-sticky (position: sticky; top: 4rem); do not reintroduce overflow-hidden on the editor shell or an ancestor scroll container that prevents viewport sticking.

Required local verification before acceptance:
1. git pull origin development
2. Run npm run build and the focused Correspondence PHP feature tests.
3. In the browser, scroll a long outgoing letter and confirm both tabs and the entire active toolbar remain visible below the app header; verify contextual selection/settings still work.
4. Open outgoing preview before saving and compare subject, body, recipient, date, appendix, letterhead, stamp/signature and page layout with the form/template. Confirm the new tab closes without saving and no correspondence record is created.
5. Confirm the preview button is absent on incoming forms and confirm the file-content warning when file composition is selected.

## 2026-11-11 — Resized table width regression test

Implementation:
- Do not reintroduce inline `max-width:none!important` when resizing tables or cells.
- Print.vue calls `constrainLetterTables()` after the Vue DOM is mounted and before measuring page counts. It resets legacy table/cell max-width values, clamps to the body column and enables wrapping.
- Print CSS constrains tables and cells to the letter body width.

Required verification:
1. Pull `development`.
2. Open an outgoing draft with a three-column table whose width was previously changed; confirm it no longer exceeds the body column.
3. Change the table width to a smaller value and then a larger value within the printable area; confirm both settings visibly apply and do not overflow.
4. Compare with a newly inserted, unmodified table.
5. Print/save as PDF and confirm the same width constraint holds on paper.
6. Run `npm run build` and focused correspondence tests; report actual results.


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


## 2026-11-14 — Correspondence numbering validation and SQLite-compatible counters

- Corrected the registration-numbering request rule to accept the supported `template` format; previously the form rejected every custom registration template before persistence, despite the template parser accepting the tokens.
- Replaced scoped-counter `insertOrIgnore` calls with conflict-safe `upsert` operations that preserve `last_number` on conflict. This avoids Laravel's unsupported SQLite `insertOrIgnore` grammar while retaining unique-key protection and the lock-based allocation path used by supported production databases.
- Kept letter-number and Secretariat-registration-number sequences independent. The registration regression now exercises a templated letter number alongside a simple Secretariat registration number, so it verifies the expected distinct output without incorrectly forcing two independently configured `simple` formats to differ.
- Focused PHPUnit and frontend build have not yet been rerun after these corrections. Development branch only; master, release and server unchanged.


## 2026-11-14 — Correspondence settings cleanup and A5 page support

- Moved organization stamp upload/activation, letterhead upload/removal, and the correspondence-template designer entry point out of the organization-details tab and into Correspondence → Letter Settings. Asset storage and template placement remain separate: uploaded images are managed here, while their position and dimensions remain controlled by the template designer.
- Removed the obsolete numbering preset/order/separator/padding and custom-part-count controls from the organization settings form, request validation, and update persistence. The two explicit token templates are now the only numbering-format inputs; old stored compatibility keys are left intact rather than destructively migrated.
- Replaced native-only color controls for default text, table borders, and shape borders with the same theme palette, standard swatches, and custom color input used by the rich-text editor.
- Added A5 alongside A4. The selected page size is passed to the template designer and print renderer; the designer's paper ratio, millimetre coordinate conversion, safe-area geometry, and page-number placement now use 148×210 mm for A5 or 210×297 mm for A4. Print preview/page slicing, body-height estimation, appendix measurement, and the browser `@page` size use the same selected dimensions. Template-layout validation now checks available width/height against the selected format.
- Added feature coverage for saving A5, A5 designer props, and the unchanged A4 default. PHPUnit and frontend build have not yet been rerun after these changes; browser print acceptance for both A4 and A5 remains required. Development branch only.


## 2026-11-14 — A5 pagination follow-up and obsolete payload removal

- Completed the A5 conversion through the final print-page slicing path: first-page body offset measurement now derives pixels-per-millimetre from the selected page width; body slice translation and page-count estimation use the selected page height; appendix measurement width uses the selected page width. The print toolbar width is also constrained to the selected format, while the print document uses the dynamically generated `@page` size and leaves the outer document width automatic so it does not depend on a descendant CSS variable.
- Removed the old preset/count/order fields from the settings form payload and feature-test payload. The request and controller now consume only the two token templates for numbering, while historical compatibility keys remain stored but are no longer editable through this form.
- The settings feature test now saves A5 and verifies the A5 designer prop; the existing designer preview test verifies the default remains A4. Runtime test/build and browser print checks are still pending.
