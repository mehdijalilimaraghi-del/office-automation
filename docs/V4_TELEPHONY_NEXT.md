# V4 telephony continuation — 2026-11-25

Status: agreed requirements and implementation plan; the capabilities below are not implemented by this document.

## Confirmed evidence and constraints
- Owner reports clear audio for an external call answered in MicroSIP. Android echo test9196 is clear, but external-call uplink in the Android client was weak; owner is comparing clients. Preserve the accepted modem codec, gain and echo path; do not infer a confirmed Android defect.
- Latest supplied numeric report shows active RMS -24.048dBFS at PBX input versus -24.074dBFS delivered to the modem, one late packet/2289 received and15ms maximum transmit lag. These metrics do not measure analog-line output.
- Windows-only, independent operation with optional Office Automation connection remains mandatory.
- One physical modem line has one external channel, shared by inbound calls, outbound calls and fax. Never interrupt an active call to obtain that channel.
- Existing fax/history/queues and other V4 commitments remain open; this list does not cancel them.

## Delivery order and required behavior
1. Outbound calls from authorized internal SIP clients. Configurable dial-prefix/number rules, per-extension external-call enablement, busy/no-answer/cancel handling and safe line ownership. Coordinate idle receiver release with an authenticated PBX-originated request; never blindly open the COM port twice. No paid real outbound dialing in automated tests.
2. Audible transfer progress. Send ringback or a selected waiting prompt while the target is ringing, with configurable timeout. Internal180Ringing alone is insufficient once the external leg has already been answered.
3. Configurable incoming scenarios. Direct extension, IVR, sequential/group ringing, operator fallback, working/out-of-hours routing, busy/offline/no-answer branches, retry/return-to-menu and safe termination. Validate destinations, keypad conflicts, cycles and bounds. Offer common presets plus editable bounded rules; do not execute arbitrary commands from configuration.
4. Explicit voicemail choice. After no answer, play a selectable prompt offering a configured voicemail key and an operator key. Record only after the caller selects voicemail and hears its recording prompt/beep. Configurable message duration, stop key and minimum usable duration. Do not turn on general call recording.
5. Voicemail delivery. Private per-user/department mailbox, permitted listening/download and notifications. Standalone mode keeps messages locally; connected mode uploads with device authentication, stable message identifiers and retries. A lost network connection must not lose the local message; mark delivered only after server acknowledgement. No public recording URLs or passwords in reports.
6. Audio settings. Separate roles for greeting, transfer waiting, no answer, busy, closed hours, recording instructions/beep and farewell. Upload/select, preview, validation and normalization must be available in the standalone application and the connected web configuration. Preserve selections and drafts across tabs.
7. Caller identity. Capture actual network-provided caller number through supported modem CallerID capability before propagating it into the SIP identity. A fabricated/internal gateway number must not masquerade as the external caller. If absent/withheld, display unknown/private rather than inventing a match. Verify display name/number on actual supported clients, including Persian names.
8. Basic directory in V4. Reuse Core external organizations/contacts phone/mobile fields and internal extensions; provide a searchable shared directory with authorized management and consistent phone normalization. Exact normalized matches may supply the caller display name. Ambiguous matches show the number without guessing a person. Respect existing visibility and expose only the identity fields required for a call.

## Fax delivery requirements
- Receive and send fax in V4; support both independent Windows operation and optional Office Automation integration.
- Deliver received faxes to the active Secretariat department resolved through Core's OrganizationDepartmentRoleService (SECRETARIAT), as configured in organization settings. Do not hardcode a user, department ID or this computer.
- Provide a Secretariat incoming-fax inbox with private document viewing/download, sender number when available, receipt time, page count and delivery state. Notify authorized Secretariat recipients using existing access boundaries.
- If Secretariat is missing/inactive or server delivery fails, retain the private local fax and show a pending-delivery error; do not discard it or silently deliver it to an unrelated administrator.
- Support local retention and authenticated, acknowledged, retryable server transfer with duplicate protection. A successful fax reception and successful server delivery are separate states.
- Let Secretariat review a received fax and initiate incoming-correspondence registration with the fax attached; do not silently finalize a letter on receipt.
- Outgoing fax: authorized send request, destination validation, document selection/preview, queue/status, bounded retry and per-attempt result. Preserve received documents and existing call/correspondence permissions.
- Include fax routing in incoming scenarios: a configured manual fax choice and capability-tested automatic fax-tone detection where supported. Configure relevant prompts and any dedicated logical fax destination in application settings.
- Fax shares the single analog line with voice. Wait for a free line; never seize it from an ongoing call. Validate actual device fax class/provider behavior rather than assuming the accepted G.711 voice path guarantees fax reliability.
- Acceptance: native synthetic tests followed by owner-controlled real send/receive, multi-page content, busy/no-answer, cancellation/retry, Secretariat authorization, missing department, offline retention, duplicate delivery and content integrity. No real paid test transmission without a user-selected destination.

## Product/version boundary
The roadmap previously limited V4's phonebook to extensions and deferred customer lookups to V8. The owner now explicitly requests a basic directory and caller-name lookup from existing organization/contact records in V4. This is a Core directory projection, not a dependency on future CRM. CRM profiles, customer-linked history and business workflow integration remain V8.

## Verified implementation map
| Area | Existing entry point | Missing work |
|---|---|---|
| Device/config sync | app/Modules/Telephony/routes.php; Services/PbxConfigurationService.php and PbxConfigurationArchive.php | Scenario/audio/mailbox/identity contract extensions and tests |
| Windows local settings | tools/WindowsTelephony/TelephonyLocalSettings.psm1 | Scenario, audio-role and outbound settings |
| Private local runtime | TelephonyDesktop.psm1; TelephonyConfiguration.psm1; PbxLab/PbxLab.psm1 | Channel arbitration, durable mailbox and event delivery |
| Call audio/control | VoiceDiagnostics.cs; LocalSipAudioEndpoint.cs; ModemGreetingBridge.cs | Outbound modem state machine and real caller identity |
| Native routing | PbxLab/freeswitch.template.xml | Ringback, failure branches and mailbox routing; existing bridge timeout30s |
| Existing contacts | app/Core/ExternalParties/Models/ExternalOrganization.php and ExternalContact.php | Authorized normalized directory contract, search UI and caller matching |
| Tests | tests/Feature/Modules/Telephony; Windows native and packaged-runtime tests | Authorization, scenarios, audio continuity, message delivery and physical acceptance |

The historical V4 map under .agents is supplemented by this verified delta; its early pending statements must not override current source code.

## Acceptance
- Verify outbound state transitions without hardware, then native SIP/PBX integration, then owner-controlled real-line tests.
- Test ringback before answer, success, busy/offline, timeout, caller cancellation, fallback, voicemail choice and operator choice.
- Assert no message recording before selection, private access, interrupted upload/retry/idempotency and standalone operation.
- Verify caller ID absent, exact match, duplicate number and Persian display on actual clients.
- Preserve incoming-call audio, keypad barge-in, credentials, local settings and feature disablement.

## Weekly quota guard
Owner requests stopping when only 5% of the weekly allowance remains. The assistant cannot read that percentage. When the owner reports the threshold reached, save a concise checkpoint (branch/commit, dirty work, checks, next step) and stop further development. Do not promise automatic quota detection.
