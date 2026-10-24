# Office Automation scanner integration

This Windows bridge uses the official NAPS2 SDK directly. It no longer opens the NAPS2 desktop window and does not ask staff to save or select a PDF manually.

## Requirements

- Windows 10/11 x64.
- A scanner with a working WIA or TWAIN driver supported by NAPS2.
- .NET 8 SDK on the build machine.
- The application reachable from the workstation over HTTP or HTTPS.

## One-time workstation setup

1. Install the scanner manufacturer's Windows driver. Installing the NAPS2 desktop application is not required by this SDK-based helper.
2. Publish the helper from the repository root:

   ```powershell
   dotnet publish .\tools\WindowsScanner\OfficeAutomation.Scanner.csproj -c Release -r win-x64 --self-contained true -o .\tools\WindowsScanner\publish
   ```

3. Copy the published files into `%LOCALAPPDATA%\OfficeAutomation\Scanner`.
4. Copy `appsettings.json.example` to `appsettings.json` in that folder and set `ApplicationOrigin` to the exact deployed origin (scheme, host, optional port; no path).
5. Run `install.ps1` as the signed-in secretariat user. Protocol registration is per-user and does not require administrator rights. Close and reopen the browser afterward.

## Workflow

1. Open **ایجاد مکاتبه**, choose **وارده**, and click **اسکن با NAPS2 و ارسال خودکار**.
2. The helper queries scanner capabilities through NAPS2 SDK. If a feeder (ADF) is reported, it scans the batch automatically and uploads one PDF. The browser displays the PDF preview for final attach/discard confirmation.
3. If no ADF is reported but a flatbed is available, each scanned page appears in a review window with **اسکن صفحه بعدی**, **اسکن دوباره همین صفحه**, and **اتمام و ذخیره**. No manual file save or file picker is involved.
4. The resulting pages are assembled into one PDF and uploaded automatically. The PDF is not attached to the correspondence until the user confirms the browser preview.

If more than one WIA/TWAIN scanner is detected, the helper asks the user to choose one. Scanner capability reporting depends on the model and driver; verify ADF detection on the actual workstation.

## Security and limits

- The bridge accepts only the custom `officeautomation-scanner://` launch and only the correspondence scanner upload path.
- The upload token is random, bound to the authenticated user on the server, and expires after 30 minutes.
- The bridge verifies the upload origin against `ApplicationOrigin`, does not follow HTTP redirects, and uploads only the PDF generated for that invocation.
- The server accepts PDF output up to 20 MB. The bridge checks the same size limit before upload.
- Temporary scan files are deleted after the upload attempt.
- PHP tests, frontend build, .NET publish, and physical scanner testing must be run before rollout. No successful validation is claimed by this source update.
