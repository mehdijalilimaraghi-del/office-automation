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
            var selectedPdf = await ScanWithNaps2();
            File.Copy(selectedPdf, pdfPath, true);
            var file = new FileInfo(pdfPath);
            if (file.Length > MaximumPdfBytes)
                throw new InvalidOperationException("حجم PDF اسکن‌شده بیش از ۲۰ مگابایت است. وضوح یا تنظیمات رنگ اسکن را در پروفایل NAPS2 کاهش دهید.");
            await UploadPdf(uploadUrl, pdfPath);
            MessageBox.Show("فایل PDF ارسال شد. به مرورگر بازگردید، پیش‌نمایش همه صفحه‌ها را بررسی و در صورت تأیید، آن را به نامه پیوست کنید.", "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Information);
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

    private static async Task<string> ScanWithNaps2()
    {
        var settingsPath = Path.Combine(AppContext.BaseDirectory, "appsettings.json");
        using var settings = JsonDocument.Parse(File.ReadAllText(settingsPath));
        var guiPath = settings.RootElement.TryGetProperty("Naps2GuiPath", out var guiValue) ? guiValue.GetString() : null;
        if (string.IsNullOrWhiteSpace(guiPath) || !File.Exists(guiPath))
            throw new InvalidOperationException("مسیر NAPS2.exe در appsettings.json تنظیم نشده است. مسیر رابط گرافیکی NAPS2 را وارد کنید.");
        MessageBox.Show("در پنجره NAPS2 همه صفحه‌های نامه را اسکن کنید. برای اسکنر تخت، پس از هر صفحه برگه بعدی را قرار دهید و دوباره Scan را بزنید.\n\nهمه صفحه‌ها را بررسی و در یک فایل PDF ذخیره کنید، سپس NAPS2 را ببندید تا فایل ذخیره‌شده را برای ارسال انتخاب کنید.", "اسکن چندصفحه‌ای نامه", MessageBoxButtons.OK, MessageBoxIcon.Information);
        var startInfo = new ProcessStartInfo { FileName = guiPath, UseShellExecute = true, WorkingDirectory = Path.GetDirectoryName(guiPath) ?? AppContext.BaseDirectory };
        using var process = Process.Start(startInfo) ?? throw new InvalidOperationException("اجرای رابط گرافیکی NAPS2 انجام نشد.");
        await process.WaitForExitAsync();
        using var dialog = new OpenFileDialog { Title = "انتخاب PDF نهایی اسکن‌شده", Filter = "فایل PDF (*.pdf)|*.pdf", CheckFileExists = true, Multiselect = false, ValidateNames = true };
        if (dialog.ShowDialog() != DialogResult.OK) throw new OperationCanceledException("انتخاب فایل اسکن‌شده لغو شد.");
        var selected = new FileInfo(dialog.FileName);
        if (!selected.Exists || selected.Length == 0) throw new InvalidOperationException("فایل انتخاب‌شده خالی یا در دسترس نیست.");
        if (selected.Length > MaximumPdfBytes) throw new InvalidOperationException("حجم PDF اسکن‌شده بیش از ۲۰ مگابایت است. وضوح یا تنظیمات رنگ اسکن را در پروفایل NAPS2 کاهش دهید.");
        return selected.FullName;
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
