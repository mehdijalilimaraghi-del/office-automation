# Office Automation NAPS2 Integration

This small Windows bridge connects the correspondence form to **NAPS2**, the free and open-source scanner application. It opens the NAPS2 desktop UI so users can scan multiple pages on a flatbed, inspect/reorder pages, and save one PDF. The helper then lets the user choose that PDF and uploads it to the current user's short-lived upload URL. The web form provides a separate preview-and-confirm step before attachment.

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
   - `Naps2GuiPath`: full path to `NAPS2.exe` (usually `C:\Program Files\NAPS2\NAPS2.exe`).
6. Run `install.ps1` as the signed-in secretariat user. Protocol registration is per-user and does not require administrator rights. Close and reopen the browser afterward.

## User workflow

1. Open **ایجاد مکاتبه** and choose **وارده**.
2. Fill in the letter details and click **اسکن با NAPS2 و ارسال خودکار**.
3. NAPS2 opens in its normal window. Scan every page; with a flatbed scanner, replace the paper and click Scan again for each page. Review, rotate, reorder, or remove pages in NAPS2, save all pages as one PDF, and close NAPS2.
4. In the helper's file picker, choose the final PDF. The bridge uploads it as a pending scan; it is not attached to the letter until you confirm the preview in the browser.
5. In the browser, inspect the PDF and choose to attach it or discard it. Then save the draft.

The user can still use **انتخاب فایل اسکن‌شده** to upload a PDF/image manually if needed. Separate accompanying files continue to use the existing attachment field.

## Security and limits

- The bridge accepts only the custom `officeautomation-scanner://` launch and only the correspondence scanner upload path.
- The upload token is random, bound to the authenticated user on the server, and expires after ten minutes.
- The bridge verifies the upload origin against `ApplicationOrigin`, does not follow HTTP redirects, and uploads only the PDF generated for that invocation.
- The server accepts PDF output up to 20 MB. The bridge checks the same size limit before upload.
- Temporary scan files are deleted after the upload attempt.
- Scanner capabilities and feeder/duplex behavior vary by model and driver. Configure and verify the NAPS2 profile for each actual secretariat scanner before broad deployment.
- The PHP tests, frontend build, .NET publish, and physical scanner workflow have **not** been run as part of this code change. Keep rollout pending until those checks are completed.
