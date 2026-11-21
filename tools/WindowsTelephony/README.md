# Windows modem discovery and controlled audio diagnostic — V4

This helper discovers Windows-installed modems and reports read-only capability queries. Discovery does not dial, answer, reset, hang up or set a voice mode. A separate, explicitly armed audio wizard may answer one incoming test call; neither path bridges audio to SIP. A declared voice class is evidence for further testing, not certification of full-duplex voice.

## Installation on the modem computer

Download the helper ZIP from Telephony using an active account with settings.manage. Extract it, then run:

powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Install.ps1

The download includes settings.json with the requesting application origin. Installation is per-user in LocalAppData and HKCU; it does not need administrative privileges or install drivers. Explicit ServerUrl overrides must be exact HTTP(S) origins. Use trusted HTTPS where available; LAN HTTP reports have the same transport exposure as the existing application.

Open the application on that same computer, create a discovery request and launch the helper. The browser may ask permission to open the custom protocol. Fully close VentaFax and other fax/dial-up applications, select a modem and review the automatic initial capability test result, then submit the report. Review a new request if the ten-minute token has expired.

The token is one-use and the uploader does not follow redirects or bypass TLS certificate validation. Only the exact installed origin and telephony report path are accepted. Tokens and credentials are not stored in settings.json.

## Diagnostic invocation

ModemHelper.ps1 -DiscoverOnly -OutputPath report.json

This reads Win32_POTSModem without opening a COM port or sending a report. The local helper identity is persisted per Windows user. Capability queries in the UI are limited to ATI, AT+FCLASS=? and AT+VSM=?; port read/write timeouts and response sizes are bounded.

## Limitations and acceptance

Requires Windows PowerShell 5.1, installed compatible modem drivers and an exposed COM port. Run under the Windows user who installed the protocol. A report with no class 8 response is inconclusive: some drivers expose voice through other APIs. An unavailable/busy port is not proof of incompatible hardware.

Physical testing on the owner's LSI PCI-SV92PP/Gnet card is still required. No working voice bridge, Asterisk connection or external-line routing has been delivered in this stage. Never enable the line based on a discovery result alone.

## Offline hardware review

On the modem computer, extract the ZIP and double-click Run-Offline.cmd. Select the modem, review its automatic capability result and save the JSON report. This mode requires neither installation nor an application connection. The separate practical audio test below is available after the initial voice-class check.

Run Test-Capabilities.ps1 to validate the helper parser without opening any modem. Matching PHP regression cases cover bare and prefixed replies and false-positive prevention.

## Practical incoming-call audio test

Extract the complete updated ZIP, including VoiceTest.ps1 and VoiceDiagnostics.cs. Open Run-Offline.cmd on the modem computer, select the modem and click the practical audio test button after discovery. Connect a free test line, close fax/dial-up software, read the consent checkbox and click Prepare. No outbound number is dialed. While waiting for a ring, call the line from a mobile. Listen for test beeps, speak the displayed random number and hang up the mobile after about eight seconds. The test waits at most 60 seconds and streams at most 12 seconds. Stop or close requests cancellation and waits for cleanup. Modem/driver native I/O can still fail; if line release is unconfirmed, check the line manually.

Play the captured WAV and confirm both hearing checks only if the beeps were audible on the mobile and the correct spoken number is clear in the recording. Data counters or a CONNECT reply alone do not certify audio. Results include restoration acknowledgement; busy/end indications do not independently prove reliable remote hangup detection. A failed AT/G.711 path is inconclusive about other APIs or adapters.

The current path requires serial AT voice commands, G.711 mu-law at 8 kHz and AT+VTR simultaneous streaming. Codec IDs are parsed rather than hardcoded. Other paths such as TAPI/wave, FXO gateways and SIP trunks remain future adapters. Settings are queried before changes and restored; failures trigger a DTR-release attempt and an unconfirmed-cleanup result. No audio is uploaded automatically: recordings remain under LocalAppData/OfficeAutomation/Telephony/AudioTests. Save Result exports JSON plus an available WAV to a chosen folder. Record only an agreed test call; remove local recordings when no longer needed.

## Software verification

Test-Audio.ps1 compiles the runtime engine and injectable fake-port fixtures in Windows PowerShell 5.1. It checks codec vectors, cross-chunk DLE escaping, WAV structure, human acceptance gates and ten simulated session outcomes, including complete four-value restoration, unrecognized settings, rejected/partial queries and cancellation while streaming. These tests open no real COM port and do not establish hardware compatibility. The wizard was visually rendered without starting a call.

Command references: [USR voice reference](https://ads.usr.com/support/5637/5637-ug/ref_voice.html), [MultiTech SocketModem AT guide](https://multitech.com/wp-content/uploads/s000468.pdf). Exact device/driver support must be tested; generic manuals do not establish LSI support.

## Diagnostic helper version 2026.11.21.2

The first owner audio test stopped at cannot_preserve_original_format before any ring/answer. Its report omitted the current AT+VSM? response, so the exact device-specific cause remains unknown. Owner-confirmed VentaFax greeting playback establishes playback evidence only. Fully exit VentaFax before testing; do not change its modem profile or drivers.

Current-setting snapshots accept complete two-to-four-number VSM replies with bare or prefixed syntax; all supplied values are restored. Unrecognized/ambiguous replies still stop before changing the audio format or answering. Raw query responses, bounded command traces, partial replies after timeout, failure stage and independent cleanup errors are now retained in schema_version 2 JSON. Residual audio drained while stopping voice is omitted from the text trace.

After a completed attempt, an automatic JSON snapshot is stored beside local audio under LocalAppData/OfficeAutomation/Telephony/AudioTests, with no automatic upload. Hearing confirmations update that snapshot. Use Save Result to export it, even if no recording exists. The title shows version 2 to distinguish a fresh extraction from an old running helper. No compatibility pass is inferred from the parser update.
