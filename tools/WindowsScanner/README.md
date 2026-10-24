# Office Automation NAPS2 Integration

This small Windows bridge connects the correspondence form to **NAPS2**, the free and open-source scanner application. NAPS2 handles scanner drivers, feeder/duplex scanning, page assembly, and PDF creation. The bridge only starts NAPS2, uploads the resulting PDF to the current user's short-lived upload URL, and returns the result to the browser.

## Requirements

- Windows 10/11 x64.
- NAPS2 installed from the official website: https://www.naps2.com/
- A working scanner driver supported by NAPS2.
- .NET 8 SDK on the build machine.
- The application reachable from the workstation over HTTP or HTTPS.

## One-time workstation setup

1. Install NAPS2 from the official website.
2. Open NAPS2 and add a profile for the secretariat scanner. Choose the correct driver (try WIA or TWAIN), paper source (Feeder or Duplex when available), page size, color mode, and DPI. Save the profile.
3. Publish the small bridge executable from the repository root:

   ```powershell
   dotnet publish .\tools\WindowsScanner\OfficeAutomation.Scanner.csproj -c Release -r win-x64 --self-contained true -o .\tools\WindowsScanner\publish
   ```

4. Copy the published files into `%LOCALAPPDATA%\OfficeAutomation\Scanner`.
5. Copy `appsettings.json.example` to `appsettings.json` in that folder and configure:
   - `ApplicationOrigin`: exact deployed origin (scheme, host, optional port; no path).
   - `Naps2ConsolePath`: full path to `NAPS2.Console.exe` (usually `C:\Program Files\NAPS2\NAPS2.Console.exe`).
   - `Naps2ProfileName`: exact profile name created in NAPS2. Leave empty to use NAPS2's most recently used profile.
6. Run `install.ps1` as the signed-in secretariat user. Protocol registration is per-user and does not require administrator rights. Close and reopen the browser afterward.

## User workflow

1. Open **ایجاد مکاتبه** and choose **وارده**.
2. Fill in the letter details and click **اسکن با NAPS2 و ارسال خودکار**.
3. NAPS2 scans using the configured profile and saves all pages from that scan operation into one PDF. The bridge uploads the PDF to the active form; no manual file selection or copy/paste is required.
4. Wait for the success message in the browser, then save the draft in the correspondence form.

The user can still use **انتخاب فایل اسکن‌شده** to upload a PDF/image manually if needed. Separate accompanying files continue to use the existing attachment field.

## Security and limits

- The bridge accepts only the custom `officeautomation-scanner://` launch and only the correspondence scanner upload path.
- The upload token is random, bound to the authenticated user on the server, and expires after ten minutes.
- The bridge verifies the upload origin against `ApplicationOrigin`, does not follow HTTP redirects, and uploads only the PDF generated for that invocation.
- The server accepts PDF output up to 20 MB. The bridge checks the same size limit before upload.
- Temporary scan files are deleted after the upload attempt.
- Scanner capabilities and feeder/duplex behavior vary by model and driver. Configure and verify the NAPS2 profile for each actual secretariat scanner before broad deployment.
- The PHP tests, frontend build, .NET publish, and physical scanner workflow have **not** been run as part of this code change. Keep rollout pending until those checks are completed.
