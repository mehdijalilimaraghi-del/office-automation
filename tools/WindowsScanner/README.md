# Office Automation Windows Scanner Helper

This helper is intended for Windows workstations used by the secretariat. It uses the built-in Windows Image Acquisition (WIA) driver, combines scanned pages into one PDF, and uploads the PDF through the short-lived URL issued by the signed-in correspondence form.

## Requirements

- Windows 10/11 x64 with a working WIA-compatible scanner driver.
- .NET 8 SDK on the build machine.
- The application reachable from the workstation over HTTP or HTTPS.

## Build and install

From the repository root, publish a self-contained Windows executable:

```powershell
dotnet publish .\tools\WindowsScanner\OfficeAutomation.Scanner.csproj -c Release -r win-x64 --self-contained true -o .\tools\WindowsScanner\publish
```

Copy the published files into `%LOCALAPPDATA%\OfficeAutomation\Scanner`, then run `install.ps1` as the signed-in secretariat user. Registration is per-user and does not require administrator rights.

## Operation and safety

- The helper accepts only an upload URL issued by the correspondence scanner endpoint and rejects unrelated URL paths.
- The upload token is random, user-bound on the server, expires after ten minutes, and is consumed when the draft is saved.
- The server accepts PDF output only and applies a 20 MB upload limit, matching original-letter uploads.
- The helper uses a temporary directory and removes it after upload.
- WIA behavior varies by scanner driver. The helper attempts feeder scanning for multiple pages; flatbed-only devices may produce one page per run. Test with the exact scanner models used by the secretariat before broad deployment.
- Review and publish the helper through the organization's normal software deployment process. Do not install an untrusted build.
