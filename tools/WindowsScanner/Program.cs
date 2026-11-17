using System.Drawing;
using System.Text.Json;
using NAPS2.Images;
using NAPS2.Images.Gdi;
using NAPS2.Pdf;
using NAPS2.Scan;

namespace OfficeAutomation.Scanner;

internal static class Program
{
    private const long MaximumPdfBytes = 20L * 1024 * 1024;
    private const int ScanDpi = 300;
    private static readonly string LogPath = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "OfficeAutomation", "Scanner", "scanner.log");

    private static void Log(string message)
    {
        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(LogPath)!);
            File.AppendAllText(LogPath, $"[{DateTimeOffset.Now:yyyy-MM-dd HH:mm:ss zzz}] {message}{Environment.NewLine}");
        }
        catch { }
    }

    [STAThread]
    private static void Main(string[] args)
    {
        ApplicationConfiguration.Initialize();
        AppDomain.CurrentDomain.UnhandledException += (_, eventArgs) =>
            Log("UNHANDLED: " + eventArgs.ExceptionObject);
        TaskScheduler.UnobservedTaskException += (_, eventArgs) =>
        {
            Log("UNOBSERVED TASK: " + eventArgs.Exception);
            eventArgs.SetObserved();
        };

        // Keep the WinForms message loop alive while asynchronous scanning runs
        // through native WIA/TWAIN drivers and while progress/review forms are shown.
        using var host = new Form
        {
            ShowInTaskbar = false,
            WindowState = FormWindowState.Minimized,
            FormBorderStyle = FormBorderStyle.FixedToolWindow,
            Text = "OfficeAutomation Scanner"
        };
        host.Shown += async (_, _) =>
        {
            host.Hide();
            await RunAsync(args);
            if (!host.IsDisposed) host.Close();
        };
        Application.Run(host);
    }

    private static async Task RunAsync(string[] args)
    {
        Log("Helper started.");
        try
        {
            Log($"Protocol arguments received: {args.Length}.");
            var uploadUrl = args.Length > 0 ? ReadUploadUrl(args[0]) : null;
            if (uploadUrl is null)
            {
                MessageBox.Show("این برنامه فقط از فرم ثبت نامه وارده در سامانه اجرا می‌شود.", "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Information);
                return;
            }

            Log("Upload URL validated.");
            using var temporary = new TemporaryDirectory();
            var pdfPath = Path.Combine(temporary.Path, "scanned-letter.pdf");
            Log("Starting scanner discovery and scan.");
            await ScanAndCreatePdf(pdfPath);
            Log("Scan and PDF export completed.");
            var file = new FileInfo(pdfPath);
            if (!file.Exists || file.Length == 0)
                throw new InvalidOperationException("فایل PDF اسکن‌شده ساخته نشد.");
            if (file.Length > MaximumPdfBytes)
                throw new InvalidOperationException("حجم PDF اسکن‌شده بیش از ۲۰ مگابایت است. وضوح اسکن را کاهش دهید.");
            Log($"PDF created ({file.Length} bytes); starting upload.");
            await UploadPdf(uploadUrl, pdfPath);
            Log("Upload completed successfully.");
            MessageBox.Show("اسکن ارسال شد. پیش‌نمایش PDF در فرم مکاتبه نمایش داده می‌شود؛ پس از بررسی، آن را تأیید و پیوست کنید.", "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (OperationCanceledException ex)
        {
            Log("Operation cancelled: " + ex.Message);
            // Intentional user cancellation.
        }
        catch (Exception ex)
        {
            Log("ERROR: " + ex);
            MessageBox.Show("اسکن یا ارسال فایل انجام نشد.\n\n" + ex.Message, "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private static async Task ScanAndCreatePdf(string pdfPath)
    {
        Log("Creating NAPS2 scanning context.");
        using var context = new ScanningContext(new GdiImageContext());
        context.SetUpWin32Worker();
        var controller = new ScanController(context);

        var devices = new List<ScanDevice>();
        try { devices.AddRange(await controller.GetDeviceList(Driver.Wia)); } catch { }
        try { devices.AddRange(await controller.GetDeviceList(Driver.Twain)); } catch { }
        devices = devices
            .GroupBy(d => $"{d.Driver}:{d.ID}", StringComparer.OrdinalIgnoreCase)
            .Select(g => g.First())
            .ToList();

        Log($"Scanner discovery completed. Device count: {devices.Count}.");
        if (devices.Count == 0)
            throw new InvalidOperationException("هیچ اسکنری از طریق WIA یا TWAIN شناسایی نشد. اتصال دستگاه و درایور آن را بررسی کنید.");

        var device = devices.Count == 1 ? devices[0] : DeviceSelectionForm.Select(devices);
        if (device is null) throw new OperationCanceledException("انتخاب اسکنر لغو شد.");

        Log($"Selected device: {device.Name}; driver: {device.Driver}.");
        var caps = await controller.GetCaps(device);
        var hasFeeder = caps.PaperSourceCaps?.SupportsFeeder == true || caps.FeederCaps is not null;
        var hasFlatbed = caps.PaperSourceCaps?.SupportsFlatbed == true || caps.FlatbedCaps is not null;
        Log($"Reported capabilities: feeder={hasFeeder}, flatbed={hasFlatbed}.");

        if (hasFeeder)
        {
            var options = new ScanOptions { Device = device, PaperSource = PaperSource.Feeder, PageSize = PageSize.A4, Dpi = ScanDpi };
            var pages = new List<ProcessedImage>();
            try
            {
                using var progress = new ProgressForm("در حال اسکن همه صفحه‌ها از تغذیه‌کننده خودکار (ADF)...");
                progress.Show();
                await foreach (var image in controller.Scan(options))
                {
                    pages.Add(image);
                    Log($"ADF page received: {pages.Count}.");
                    progress.SetMessage($"در حال اسکن صفحه {pages.Count}...");
                }
                progress.Close();

                if (pages.Count == 0)
                    throw new InvalidOperationException("اسکنر ADF هیچ صفحه‌ای برنگرداند. وجود کاغذ و تنظیمات منبع اسکن را بررسی کنید.");

                Log($"Exporting ADF pages to PDF. Page count: {pages.Count}.");
                var exporter = new PdfExporter(context);
                await exporter.Export(pdfPath, pages);
            }
            finally
            {
                foreach (var page in pages) page.Dispose();
            }
            return;
        }

        if (!hasFlatbed)
            throw new InvalidOperationException("قابلیت ADF یا تخت از طریق درایور این اسکنر گزارش نشد. درایور WIA/TWAIN و تنظیمات دستگاه را بررسی کنید.");

        var flatbedOptions = new ScanOptions { Device = device, PaperSource = PaperSource.Flatbed, PageSize = PageSize.A4, Dpi = ScanDpi };
        var flatbedPages = new List<ProcessedImage>();
        try
        {
            while (true)
            {
                ProcessedImage? page = null;
                using (var progress = new ProgressForm($"در حال اسکن صفحه {flatbedPages.Count + 1}..."))
                {
                    progress.Show();
                    await foreach (var image in controller.Scan(flatbedOptions))
                    {
                        page = image;
                        break;
                    }
                }

                if (page is null)
                    throw new InvalidOperationException("اسکنر تصویری برای این صفحه برنگرداند.");

                var action = FlatbedPageReviewForm.Review(page, flatbedPages.Count + 1);
                if (action == FlatbedReviewAction.Rescan)
                {
                    page.Dispose();
                    continue;
                }
                if (action == FlatbedReviewAction.Cancel)
                {
                    page.Dispose();
                    throw new OperationCanceledException("اسکن توسط کاربر لغو شد.");
                }

                flatbedPages.Add(page);
                if (action == FlatbedReviewAction.Finish)
                    break;
            }

            if (flatbedPages.Count == 0)
                throw new OperationCanceledException("هیچ صفحه‌ای برای ذخیره انتخاب نشد.");

            Log($"Exporting flatbed pages to PDF. Page count: {flatbedPages.Count}.");
            var exporter = new PdfExporter(context);
            await exporter.Export(pdfPath, flatbedPages);
        }
        finally
        {
            foreach (var page in flatbedPages) page.Dispose();
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
        var configuredOrigin = settings.RootElement.TryGetProperty("ApplicationOrigin", out var originValue) ? originValue.GetString() : null;
        if (!Uri.TryCreate(configuredOrigin, UriKind.Absolute, out var trustedOrigin)
            || !string.Equals(uploadUri.GetLeftPart(UriPartial.Authority), trustedOrigin.GetLeftPart(UriPartial.Authority), StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException("نشانی ارسال فایل با نشانی مجاز سامانه در appsettings.json یکسان نیست.");

        var token = uploadUri.AbsolutePath.Split('/').LastOrDefault();
        if (token is null || token.Length != 64 || token.Any(character => !char.IsLetterOrDigit(character)))
            throw new InvalidOperationException("شناسه موقت اسکن معتبر نیست.");

        return uploadUri.ToString();
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
        public void Dispose() { try { Directory.Delete(Path, true); } catch { } }
    }
}

internal enum FlatbedReviewAction { Next, Rescan, Finish, Cancel }

internal sealed class DeviceSelectionForm : Form
{
    private readonly ComboBox devices = new() { DropDownStyle = ComboBoxStyle.DropDownList, Dock = DockStyle.Top };
    private DeviceSelectionForm(IReadOnlyList<ScanDevice> available)
    {
        Text = "انتخاب اسکنر";
        Width = 560;
        Height = 210;
        MinimumSize = new Size(480, 200);
        StartPosition = FormStartPosition.CenterScreen;
        RightToLeft = RightToLeft.Yes;
        RightToLeftLayout = true;
        Font = new Font("Segoe UI", 10F, FontStyle.Regular, GraphicsUnit.Point);
        BackColor = Color.FromArgb(248, 250, 252);
        Padding = new Padding(18);

        var layout = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 1, RowCount = 3, Padding = new Padding(4) };
        layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        var label = new Label { Text = "دستگاه اسکن مورد استفاده را انتخاب کنید", Dock = DockStyle.Fill, AutoSize = true, Padding = new Padding(0, 0, 0, 12), TextAlign = ContentAlignment.MiddleRight, Font = new Font("Segoe UI", 10F, FontStyle.Bold) };
        devices.Dock = DockStyle.Fill;
        devices.Font = new Font("Segoe UI", 10F);
        devices.Margin = new Padding(0, 0, 0, 16);
        foreach (var item in available) devices.Items.Add(new DeviceChoice(item));
        devices.SelectedIndex = 0;
        var buttons = new FlowLayoutPanel { Dock = DockStyle.Fill, AutoSize = true, FlowDirection = FlowDirection.LeftToRight, WrapContents = false, Padding = new Padding(0, 8, 0, 0) };
        var ok = new Button { Text = "ادامه", DialogResult = DialogResult.OK, AutoSize = true, MinimumSize = new Size(100, 38), FlatStyle = FlatStyle.Flat, BackColor = Color.FromArgb(79, 70, 229), ForeColor = Color.White, UseVisualStyleBackColor = false };
        var cancel = new Button { Text = "انصراف", DialogResult = DialogResult.Cancel, AutoSize = true, MinimumSize = new Size(100, 38), FlatStyle = FlatStyle.Flat, BackColor = Color.White, ForeColor = Color.FromArgb(51, 65, 85), UseVisualStyleBackColor = false };
        buttons.Controls.Add(ok);
        buttons.Controls.Add(cancel);
        layout.Controls.Add(label, 0, 0);
        layout.Controls.Add(devices, 0, 1);
        layout.Controls.Add(buttons, 0, 2);
        Controls.Add(layout);
        AcceptButton = ok;
        CancelButton = cancel;
    }
    public static ScanDevice? Select(IReadOnlyList<ScanDevice> available)
    {
        using var form = new DeviceSelectionForm(available);
        return form.ShowDialog() == DialogResult.OK && form.devices.SelectedItem is DeviceChoice choice ? choice.Device : null;
    }
    private sealed record DeviceChoice(ScanDevice Device)
    {
        public override string ToString() => Device.Name;
    }
}

internal sealed class ProgressForm : Form
{
    private readonly Label message = new() { Dock = DockStyle.Fill, TextAlign = ContentAlignment.MiddleCenter };
    public ProgressForm(string text)
    {
        Text = "اسکن نامه";
        Width = 480;
        Height = 150;
        StartPosition = FormStartPosition.CenterScreen;
        RightToLeft = RightToLeft.Yes;
        RightToLeftLayout = true;
        Font = new Font("Segoe UI", 10F, FontStyle.Regular, GraphicsUnit.Point);
        BackColor = Color.White;
        Padding = new Padding(20);
        message.Text = text;
        message.Font = new Font("Segoe UI", 11F, FontStyle.Regular, GraphicsUnit.Point);
        message.ForeColor = Color.FromArgb(30, 41, 59);
        message.Padding = new Padding(12);
        Controls.Add(message);
    }
    public void SetMessage(string text)
    {
        if (InvokeRequired) { BeginInvoke(() => message.Text = text); return; }
        message.Text = text; Refresh();
    }
}

internal sealed class FlatbedPageReviewForm : Form
{
    private readonly PictureBox picture = new() { Dock = DockStyle.Fill, SizeMode = PictureBoxSizeMode.Zoom, BackColor = Color.DimGray };
    private FlatbedReviewAction actionResult = FlatbedReviewAction.Cancel;
    private FlatbedPageReviewForm(ProcessedImage image, int pageNumber)
    {
        Text = $"بازبینی صفحه {pageNumber}";
        Width = 1100;
        Height = 820;
        MinimumSize = new Size(720, 540);
        StartPosition = FormStartPosition.CenterScreen;
        RightToLeft = RightToLeft.Yes;
        RightToLeftLayout = true;
        Font = new Font("Segoe UI", 10F, FontStyle.Regular, GraphicsUnit.Point);
        BackColor = Color.FromArgb(241, 245, 249);
        Padding = new Padding(16);

        var header = new Label
        {
            Text = $"بازبینی صفحه {pageNumber}  |  کیفیت تصویر را بررسی کنید",
            Dock = DockStyle.Top,
            Height = 48,
            TextAlign = ContentAlignment.MiddleRight,
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Color.FromArgb(30, 41, 59),
            Padding = new Padding(8, 0, 8, 0)
        };
        picture.Dock = DockStyle.Fill;
        picture.BackColor = Color.FromArgb(226, 232, 240);
        picture.BorderStyle = BorderStyle.FixedSingle;
        picture.Margin = new Padding(0, 0, 0, 12);
        using (var bitmap = image.RenderToBitmap()) picture.Image = new Bitmap(bitmap);

        var buttons = new FlowLayoutPanel
        {
            Dock = DockStyle.Bottom,
            Height = 76,
            FlowDirection = FlowDirection.RightToLeft,
            WrapContents = false,
            Padding = new Padding(4, 14, 4, 4),
            BackColor = Color.White
        };
        Button MakeButton(string text, Color background, Color foreground)
        {
            var button = new Button
            {
                Text = text,
                AutoSize = true,
                MinimumSize = new Size(150, 42),
                Padding = new Padding(12, 4, 12, 4),
                Margin = new Padding(6, 0, 6, 0),
                FlatStyle = FlatStyle.Flat,
                BackColor = background,
                ForeColor = foreground,
                UseVisualStyleBackColor = false,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold)
            };
            button.FlatAppearance.BorderColor = background == Color.White ? Color.FromArgb(203, 213, 225) : background;
            return button;
        }
        var next = MakeButton("اسکن صفحه بعدی", Color.FromArgb(79, 70, 229), Color.White);
        var rescan = MakeButton("اسکن دوباره این صفحه", Color.White, Color.FromArgb(51, 65, 85));
        var finish = MakeButton("اتمام و ذخیره PDF", Color.FromArgb(5, 150, 105), Color.White);
        var cancel = MakeButton("لغو", Color.White, Color.FromArgb(185, 28, 28));
        next.Click += (_, _) => { actionResult = FlatbedReviewAction.Next; Close(); };
        rescan.Click += (_, _) => { actionResult = FlatbedReviewAction.Rescan; Close(); };
        finish.Click += (_, _) => { actionResult = FlatbedReviewAction.Finish; Close(); };
        cancel.Click += (_, _) => { actionResult = FlatbedReviewAction.Cancel; Close(); };
        buttons.Controls.Add(next);
        buttons.Controls.Add(rescan);
        buttons.Controls.Add(finish);
        buttons.Controls.Add(cancel);

        Controls.Add(picture);
        Controls.Add(header);
        Controls.Add(buttons);
    }
    public static FlatbedReviewAction Review(ProcessedImage image, int pageNumber)
    {
        using var form = new FlatbedPageReviewForm(image, pageNumber);
        form.ShowDialog();
        return form.actionResult;
    }
    protected override void OnFormClosed(FormClosedEventArgs e)
    {
        picture.Image?.Dispose();
        base.OnFormClosed(e);
    }
}