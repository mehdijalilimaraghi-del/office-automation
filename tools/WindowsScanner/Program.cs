using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using PdfSharp.Drawing;
using PdfSharp.Pdf;

namespace OfficeAutomation.Scanner;

internal static class Program
{
    private const string JpegFormat = "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}";

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
            var pages = ScanPages(temporary.Path);
            if (pages.Count == 0)
                throw new InvalidOperationException("هیچ صفحه‌ای از اسکنر دریافت نشد.");

            var pdfPath = System.IO.Path.Combine(temporary.Path, "scanned-letter.pdf");
            CreatePdf(pages, pdfPath);
            await UploadPdf(uploadUrl, pdfPath);
            MessageBox.Show($"اسکن {pages.Count} صفحه با موفقیت به فرم مکاتبات ارسال شد. به مرورگر بازگردید و پیش‌نویس را ذخیره کنید.", "اسکن نامه", MessageBoxButtons.OK, MessageBoxIcon.Information);
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
            return null;

        var decoded = Uri.UnescapeDataString(pair[1]);
        if (!Uri.TryCreate(decoded, UriKind.Absolute, out var uploadUri)
            || (uploadUri.Scheme != Uri.UriSchemeHttps && uploadUri.Scheme != Uri.UriSchemeHttp)
            || !uploadUri.AbsolutePath.Contains("/correspondence/scanner/upload/", StringComparison.Ordinal)
            || uploadUri.UserInfo.Length > 0)
            throw new InvalidOperationException("نشانی ارسال فایل نامعتبر است.");

        return uploadUri.ToString();
    }

    private static List<string> ScanPages(string outputDirectory)
    {
        var managerType = Type.GetTypeFromProgID("WIA.DeviceManager")
            ?? throw new InvalidOperationException("قابلیت Windows Image Acquisition (WIA) در این ویندوز در دسترس نیست.");
        dynamic manager = Activator.CreateInstance(managerType)!;
        dynamic? scanner = null;
        try
        {
            foreach (dynamic info in manager.DeviceInfos)
            {
                try
                {
                    if (Convert.ToInt32(info.Type) == 1)
                    {
                        scanner = info.Connect();
                        break;
                    }
                }
                finally
                {
                    if (scanner is null) Marshal.ReleaseComObject(info);
                }
            }

            if (scanner is null)
                throw new InvalidOperationException("اسکنر WIA پیدا نشد. اتصال کابل، روشن بودن دستگاه و درایور اسکنر را بررسی کنید.");

            TrySetProperty(scanner.Properties, 3088, 1);
            var pages = new List<string>();
            var item = scanner.Items[1];
            for (var pageNumber = 1; pageNumber <= 100; pageNumber++)
            {
                try
                {
                    var image = item.Transfer(JpegFormat);
                    var pagePath = System.IO.Path.Combine(outputDirectory, $"page-{pageNumber:D3}.jpg");
                    image.SaveFile(pagePath);
                    pages.Add(pagePath);
                    Marshal.ReleaseComObject(image);
                }
                catch (COMException) when (pages.Count > 0)
                {
                    break;
                }

                var status = TryGetProperty(scanner.Properties, 3087);
                var hasFeederReady = status.HasValue && (status.Value & 1) == 1;
                var hasFeeder = TryGetProperty(scanner.Properties, 3088) == 1;
                if (!hasFeeder || !hasFeederReady)
                    break;
            }

            Marshal.ReleaseComObject(item);
            return pages;
        }
        finally
        {
            if (scanner is not null) Marshal.ReleaseComObject(scanner);
            Marshal.ReleaseComObject(manager);
        }
    }

    private static void TrySetProperty(dynamic properties, int propertyId, int value)
    {
        try
        {
            foreach (dynamic property in properties)
            {
                try
                {
                    if (Convert.ToInt32(property.PropertyID) == propertyId)
                    {
                        property.set_Value(value);
                        return;
                    }
                }
                finally { Marshal.ReleaseComObject(property); }
            }
        }
        catch (COMException) { }
    }

    private static int? TryGetProperty(dynamic properties, int propertyId)
    {
        try
        {
            foreach (dynamic property in properties)
            {
                try
                {
                    if (Convert.ToInt32(property.PropertyID) == propertyId)
                        return Convert.ToInt32(property.get_Value());
                }
                finally { Marshal.ReleaseComObject(property); }
            }
        }
        catch (COMException) { }
        return null;
    }

    private static void CreatePdf(IReadOnlyList<string> images, string destination)
    {
        using var document = new PdfDocument();
        foreach (var imagePath in images)
        {
            using var image = XImage.FromFile(imagePath);
            var page = document.AddPage();
            page.Width = XUnit.FromPoint(image.PointWidth);
            page.Height = XUnit.FromPoint(image.PointHeight);
            using var graphics = XGraphics.FromPdfPage(page);
            graphics.DrawImage(image, 0, 0, page.Width, page.Height);
        }
        document.Save(destination);
    }

    private static async Task UploadPdf(string uploadUrl, string pdfPath)
    {
        using var client = new HttpClient { Timeout = TimeSpan.FromMinutes(2) };
        using var form = new MultipartFormDataContent();
        await using var stream = File.OpenRead(pdfPath);
        using var file = new StreamContent(stream);
        file.Headers.ContentType = new MediaTypeHeaderValue("application/pdf");
        form.Add(file, "file", "scanned-letter.pdf");
        using var response = await client.PostAsync(uploadUrl, form);
        if (!response.IsSuccessStatusCode)
        {
            var details = await response.Content.ReadAsStringAsync();
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
