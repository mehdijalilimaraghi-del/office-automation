# Windows modem discovery helper — V4 stage 1

This helper discovers Windows-installed modems and reports read-only capability queries. It does not dial, answer, reset, hang up, set a voice mode or bridge audio to SIP. A declared voice class is evidence for further testing, not certification of full-duplex voice.

## Installation on the modem computer

Download the helper ZIP from Telephony using an active account with settings.manage. Extract it, then run:

powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Install.ps1

The download includes settings.json with the requesting application origin. Installation is per-user in LocalAppData and HKCU; it does not need administrative privileges or install drivers. Explicit ServerUrl overrides must be exact HTTP(S) origins. Use trusted HTTPS where available; LAN HTTP reports have the same transport exposure as the existing application.

Open the application on that same computer, create a discovery request and launch the helper. The browser may ask permission to open the custom protocol. Close other fax/dial-up applications, select a modem and review the automatic initial capability test result, then submit the report. Review a new request if the ten-minute token has expired.

The token is one-use and the uploader does not follow redirects or bypass TLS certificate validation. Only the exact installed origin and telephony report path are accepted. Tokens and credentials are not stored in settings.json.

## Diagnostic invocation

ModemHelper.ps1 -DiscoverOnly -OutputPath report.json

This reads Win32_POTSModem without opening a COM port or sending a report. The local helper identity is persisted per Windows user. Capability queries in the UI are limited to ATI, AT+FCLASS=? and AT+VSM=?; port read/write timeouts and response sizes are bounded.

## Limitations and acceptance

Requires Windows PowerShell 5.1, installed compatible modem drivers and an exposed COM port. Run under the Windows user who installed the protocol. A report with no class 8 response is inconclusive: some drivers expose voice through other APIs. An unavailable/busy port is not proof of incompatible hardware.

Physical testing on the owner's LSI PCI-SV92PP/Gnet card is still required. No working voice bridge, Asterisk connection or external-line routing has been delivered in this stage. Never enable the line based on a discovery result alone.

## Offline hardware review

On the modem computer, extract the ZIP and double-click Run-Offline.cmd. Select the modem, review its automatic capability result and save the JSON report. This mode requires neither installation nor an application connection. Send the saved report for review before any voice call test.

Run Test-Capabilities.ps1 to validate the helper parser without opening any modem. Matching PHP regression cases cover bare and prefixed replies and false-positive prevention.
