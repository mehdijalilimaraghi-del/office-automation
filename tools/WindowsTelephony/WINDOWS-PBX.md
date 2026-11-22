# Native Windows telephony host check

The owner requires the PBX and modem helper to run on the modem Windows PC, without Linux, WSL, Docker or a virtual machine. The existing Laravel/Vue/SQL Server application remains unchanged. The earlier Asterisk-specific assumption is superseded for this deployment.

FreeSWITCH 1.11.4 is the pinned native Windows lab candidate. Its official GitHub MSI has been downloaded and verified against the GitHub SHA256 digest and valid SignalWire Authenticode signature. The earlier files.freeswitch.org listing failure is resolved by the official GitHub release: https://github.com/signalwire/freeswitch/releases/tag/v1.11.4 . This is package verification, not an installed or accepted PBX bridge.

## Run on the modem computer

Extract the complete host-check ZIP to a writable folder. Double-click Inspect-Computer.cmd. Choose where to save telephony-host-report.json and send that report for assessment. Administrator rights are not required for the normal CIM inventory. It does not open the modem, stop VentaFax, upload data, install software, change firewall settings or restart Windows. No browser or application connection is required.

The report includes computer name, Windows edition/build/architecture, CPU model/core counts, memory snapshot and fixed-drive sizes/free space. It excludes passwords, product keys, serial numbers, network credentials and recordings. Resources are observations, not an automatic readiness certificate.

For unattended local validation, run Windows PowerShell 5.1 with Inspect-TelephonyHost.ps1 -NoUI -OutputPath <a writable JSON path>. A dot-source only defines the inventory function and does not collect/write automatically.

## Remaining work

Review the owner host report, verify a maintained native Windows PBX build and its dependencies, implement its driver and modem audio bridge, then test internal extensions and external calls. Preserve the passed local modem test. Review the 120060 received samples versus 96000 transmitted samples over 12012 ms before network streaming and validate remote hangup. Do not present host inspection as PBX installation or completed V4.

## Engine installation preparation

Install-PbxEngine.ps1 validates the pinned official MSI (166219776 bytes, SHA256 331cdd876c7ee73cc6d60bbdd7c9b7e60c157d3760226986fd7d6e604c83d8b6) and valid SignalWire signature. CheckOnly opens no installer and changes no system configuration. Normal execution requests Windows Administrator consent for msiexec, installs the core ProductFeature only, excludes vendor sample configuration/optional codec download, requests no automatic restart and records the result. Existing service/folder is refused to preserve prior installations.

Inspection of the actual MSI confirms ProductVersion 1.11.4, FreeSWITCH ServiceInstall StartType 3 (manual) and ServiceControl Event 162 (no start-on-install). The script verifies the service is stopped after installation. Core installation is not line activation, extension provisioning or a passing call test. Runtime configuration must be supplied before starting the service. Owner installation and its administrator path remain untested.

## Extension growth and moving hosts

Two extensions are the initial smoke test only. The product must add/manage extensions for people and units without a fixed two-extension limit; concurrency needs hardware benchmarks. One analog modem line permits one simultaneous external call, independently of internal extension count. Installer paths are generic, not bound to EDARII. Full migration/export/restore, modem re-enrollment, call-recording transfer and credential rekeying remain to be implemented and tested. Never copy machine-bound protected credentials blindly.

## Installer path patch (2026-11-22)

InstallerPath is resolved inside the script body rather than a parameter default. The CMD launcher supplies an explicit adjacent MSI path and returns the child exit code after pause. Use OfficeAutomation-PbxInstaller-PathFix.zip to replace only Install-PbxEngine.ps1 and Install-PbxEngine.cmd in an existing extracted package. Keep the signed MSI unchanged. Test-PbxInstallerPaths.ps1 covers Unicode/spaced paths and missing-root fallback with process elevation forbidden in every child.

## Diagnose the reported install before retrying

The actual MSI contains FreeSwitchConsole.exe; the former wrapper incorrectly checked freeswitch.exe after MSI success and saved a report only on success. That can report a wrapper failure even when installation completed. The black screen is not diagnosed yet. Run Collect-PbxInstallDiagnostics.cmd first; its archive includes current service/binary/uninstall metadata, relevant recent Application events and the latest own setup logs. It does not run an installer, Win32_Product, repair, reboot or service commands. Reports may contain local user paths/event details; keep them out of Git.

Installer console messages are now English, and failures save JSON/text and available MSI logs. The [Microsoft-documented Restart Manager property](https://learn.microsoft.com/en-us/windows/win32/msi/msirestartmanagercontrol) disables its interaction for future install attempts; /norestart alone controls OS restart rather than all application shutdown. This protection does not establish the cause of the observed black screen. Do not rerun until diagnostics are reviewed.
