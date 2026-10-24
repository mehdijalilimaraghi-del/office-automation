using System.Diagnostics;
using System.Text.Json;

namespace OfficeAutomation.Scanner;

internal static class Program
{
    private const long MaximumPdfBytes = 20L * 1024 * 1024;

    [STAThread]
    private static async Task Main(string[] args)
    {
        try
        {
            var uploadUrl = args.Length > 0 ? ReadUploadUrl(args[0]) : null;
            if (uploadUrl is null)
            {
                MessageBox.Show("این برنامه فقط از فرم ثبت نامه وارده در سامانه اجرا می‌شود.", "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Information);
                return;
            }

            using var temporary = new TemporaryDirectory();
            var pdfPath = Path.Combine(temporary.Path, "scanned-letter.pdf");
            await ScanWithNaps2(pdfPath);
            await UploadPdf(uploadUrl, pdfPath);

            MessageBox.Show("فایل PDF اسکن‌شده با موفقیت به فرم مکاتبات ارسال شد. به مرورگر بازگردید و پیش‌نویس را ذخیره کنید.", "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show("اسکن یا ارسال فایل انجام نشد.\n\n" + ex.Message, "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private static string? ReadUploadUrl(string uriText)
    {
        if (!Uri.TryCreate(uriText, UriKind.Absolute, out var uri) || uri.Scheme != "officeautomation-scanner")
            return null;

        var query = uri.Query.TrimStart('?').Split('&', StringSplitOptions.RemoveEmptyEntries);
        var pair = query.Select(part => part.Split('=', 2))
            .FirstOrDefault(parts => parts.Length == 2 && parts[0] == "uploadUrl");
        if (pair is null)
            throw new InvalidOperationException("نشانی موقت ارسال اسکن در پیوند اجرا وجود ندارد.");

        var decoded = Uri.UnescapeDataString(pair[1]);
        if (!Uri.TryCreate(decoded, UriKind.Absolute, out var uploadUri)
            || (uploadUri.Scheme != Uri.UriSchemeHttps && uploadUri.Scheme != Uri.UriSchemeHttp)
            || !uploadUri.AbsolutePath.Contains("/correspondence/scanner/upload/", StringComparison.Ordinal)
            || uploadUri.UserInfo.Length > 0)
            throw new InvalidOperationException("نشانی ارسال فایل نامعتبر است.");

        var settingsPath = Path.Combine(AppContext.BaseDirectory, "appsettings.json");
        if (!File.Exists(settingsPath))
            throw new InvalidOperationException("فایل appsettings.json برنامه اتصال وجود ندارد؛ تنظیمات را طبق راهنمای نصب تکمیل کنید.");

        using var settings = JsonDocument.Parse(File.ReadAllText(settingsPath));
        var configuredOrigin = settings.RootElement.TryGetProperty("ApplicationOrigin", out var originValue)
            ? originValue.GetString()
            : null;
        if (!Uri.TryCreate(configuredOrigin, UriKind.Absolute, out var trustedOrigin)
            || !string.Equals(uploadUri.GetLeftPart(UriPartial.Authority), trustedOrigin.GetLeftPart(UriPartial.Authority), StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException("نشانی ارسال فایل با نشانی مجاز سامانه در appsettings.json یکسان نیست.");

        var token = uploadUri.AbsolutePath.Split('/').LastOrDefault();
        if (token is null || token.Length != 64 || token.Any(character => !char.IsLetterOrDigit(character)))
            throw new InvalidOperationException("شناسه موقت اسکن معتبر نیست.");

        return uploadUri.ToString();
    }

    private static async Task ScanWithNaps2(string outputPath)
    {
        var settingsPath = Path.Combine(AppContext.BaseDirectory, "appsettings.json");
        using var settings = JsonDocument.Parse(File.ReadAllText(settingsPath));
        var root = settings.RootElement;

        var consolePath = root.TryGetProperty("Naps2ConsolePath", out var pathValue)
            ? pathValue.GetString()
            : null;
        if (string.IsNullOrWhiteSpace(consolePath) || !File.Exists(consolePath))
            throw new InvalidOperationException("NAPS2 پیدا نشد. ابتدا NAPS2 را نصب و مسیر NAPS2.Console.exe را در appsettings.json تنظیم کنید.");

        var profileName = root.TryGetProperty("Naps2ProfileName", out var profileValue)
            ? profileValue.GetString()
            : null;

        var startInfo = new ProcessStartInfo
        {
            FileName = consolePath,
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
            WorkingDirectory = Path.GetDirectoryName(consolePath) ?? AppContext.BaseDirectory,
        };
        startInfo.ArgumentList.Add("--output");
        startInfo.ArgumentList.Add(outputPath);
        if (!string.IsNullOrWhiteSpace(profileName))
        {
            startInfo.ArgumentList.Add("--profile");
            startInfo.ArgumentList.Add(profileName);
        }
        startInfo.ArgumentList.Add("--progress");
        startInfo.ArgumentList.Add("--verbose");

        using var process = new Process { StartInfo = startInfo };
        if (!process.Start())
            throw new InvalidOperationException("اجرای NAPS2 انجام نشد.");

        var stdoutTask = process.StandardOutput.ReadToEndAsync();
        var stderrTask = process.StandardError.ReadToEndAsync();
        await process.WaitForExitAsync();
        var stdout = await stdoutTask;
        var stderr = await stderrTask;

        if (process.ExitCode != 0)
        {
            var details = string.Join(Environment.NewLine, new[] { stderr.Trim(), stdout.Trim() }.Where(value => value.Length > 0));
            if (details.Length > 1200) details = details[..1200];
            throw new InvalidOperationException("NAPS2 اسکن را کامل نکرد. اتصال اسکنر، پروفایل انتخاب‌شده و تنظیمات فیدر را بررسی کنید."
                + (details.Length > 0 ? Environment.NewLine + details : string.Empty));
        }

        var file = new FileInfo(outputPath);
        if (!file.Exists || file.Length == 0)
            throw new InvalidOperationException("NAPS2 فایل PDF تولید نکرد. بررسی کنید اسکن لغو نشده و پروفایل اسکن معتبر است.");
        if (file.Length > MaximumPdfBytes)
            throw new InvalidOperationException("حجم PDF اسکن‌شده بیش از ۲۰ مگابایت است. وضوح یا تنظیمات رنگ اسکن را در پروفایل NAPS2 کاهش دهید.");
    }

    private static async Task UploadPdf(string uploadUrl, string pdfPath)
    {
        using var handler = new HttpClientHandler { AllowAutoRedirect = false };
        using var client = new HttpClient(handler) { Timeout = TimeSpan.FromMinutes(2) };
        using var form = new MultipartFormDataContent();
        await using var stream = File.OpenRead(pdfPath);
        using var file = new StreamContent(stream);
        file.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/pdf");
        form.Add(file, "file", "scanned-letter.pdf");
        using var response = await client.PostAsync(uploadUrl, form);
        if (!response.IsSuccessStatusCode)
        {
            var details = await response.Content.ReadAsStringAsync();
            if (details.Length > 1200) details = details[..1200];
            throw new InvalidOperationException($"سامانه فایل اسکن‌شده را نپذیرفت ({(int)response.StatusCode}). {details}");
        }
    }

    private sealed class TemporaryDirectory : IDisposable
    {
        public string Path { get; } = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "OfficeAutomationScanner-" + Guid.NewGuid().ToString("N"));
        public TemporaryDirectory() => Directory.CreateDirectory(Path);
        public void Dispose()
        {
            try { Directory.Delete(Path, true); } catch { }
        }
    }
}
