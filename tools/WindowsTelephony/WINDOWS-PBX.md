# Native Windows telephony host check

The owner requires the PBX and modem helper to run on the modem Windows PC, without Linux, WSL, Docker or a virtual machine. The existing Laravel/Vue/SQL Server application remains unchanged. The earlier Asterisk-specific assumption is superseded for this deployment.

FreeSWITCH is a candidate native Windows PBX: its official repository supports building for Windows. This is not a verified release selection. The official prebuilt Windows package listing could not be resolved from the development environment; do not install an obsolete/unverified binary or claim a working bridge. Reference: https://github.com/signalwire/freeswitch .

## Run on the modem computer

Extract the complete host-check ZIP to a writable folder. Double-click Inspect-Computer.cmd. Choose where to save telephony-host-report.json and send that report for assessment. Administrator rights are not required for the normal CIM inventory. It does not open the modem, stop VentaFax, upload data, install software, change firewall settings or restart Windows. No browser or application connection is required.

The report includes computer name, Windows edition/build/architecture, CPU model/core counts, memory snapshot and fixed-drive sizes/free space. It excludes passwords, product keys, serial numbers, network credentials and recordings. Resources are observations, not an automatic readiness certificate.

For unattended local validation, run Windows PowerShell 5.1 with Inspect-TelephonyHost.ps1 -NoUI -OutputPath <a writable JSON path>. A dot-source only defines the inventory function and does not collect/write automatically.

## Remaining work

Review the owner host report, verify a maintained native Windows PBX build and its dependencies, implement its driver and modem audio bridge, then test internal extensions and external calls. Preserve the passed local modem test. Review the 120060 received samples versus 96000 transmitted samples over 12012 ms before network streaming and validate remote hangup. Do not present host inspection as PBX installation or completed V4.
