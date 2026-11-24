using System;
using System.IO;
using System.IO.Compression;
using System.Drawing;
using System.Reflection;
using System.Security.Cryptography;
using System.Text;
using System.Windows.Forms;
using System.Threading.Tasks;
namespace OfficeAutomation.Telephony.Setup {
 internal static class Program {
  static string BaseDirectory {get{return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),"OfficeAutomation","Telephony","Desktop");}}
  static string Digest(Stream stream){using(var hash=SHA256.Create())return BitConverter.ToString(hash.ComputeHash(stream)).Replace("-","").ToLowerInvariant();}
  static Stream Payload(){var stream=Assembly.GetExecutingAssembly().GetManifestResourceStream("OfficeAutomation.Payload.zip");if(stream==null)throw new InvalidDataException("Installation payload is missing");return stream;}
  internal static string Extract(string destination,bool verifyOnly) {
   string root=Path.GetFullPath(destination).TrimEnd(Path.DirectorySeparatorChar)+Path.DirectorySeparatorChar;
   using(var payload=Payload())using(var zip=new ZipArchive(payload,ZipArchiveMode.Read)) {
    var manifest=zip.GetEntry("SHA256SUMS");if(manifest==null)throw new InvalidDataException("Package manifest is missing");
    var hashes=new System.Collections.Generic.Dictionary<string,string>(StringComparer.OrdinalIgnoreCase);
    using(var reader=new StreamReader(manifest.Open(),Encoding.UTF8)){string line;while((line=reader.ReadLine())!=null){int split=line.IndexOf("  ",StringComparison.Ordinal);if(split!=64)throw new InvalidDataException("Invalid manifest");hashes.Add(line.Substring(split+2),line.Substring(0,split));}}
    long total=0;var seen=new System.Collections.Generic.HashSet<string>(StringComparer.OrdinalIgnoreCase);
    foreach(var entry in zip.Entries){
     if(entry.FullName=="SHA256SUMS" || entry.FullName.EndsWith("/") || entry.FullName.EndsWith("\\"))continue;
     string path=Path.GetFullPath(Path.Combine(root,entry.FullName.Replace('/',Path.DirectorySeparatorChar)));
     if(!path.StartsWith(root,StringComparison.OrdinalIgnoreCase)||Path.IsPathRooted(entry.FullName)||entry.FullName.IndexOf(':')>=0)throw new InvalidDataException("Invalid package path");
     if(entry.Length>268435456 || (total+=entry.Length)>1073741824)throw new InvalidDataException("Package exceeds installation limit");
     string key=entry.FullName.Replace((char)92,(char)47);if(!seen.Add(key))throw new InvalidDataException("Duplicate package file");
     string expected;if(!hashes.TryGetValue(key,out expected))throw new InvalidDataException("Unlisted package file");
     using(var source=entry.Open()){if(Digest(source)!=expected)throw new InvalidDataException("Package integrity check failed");}
    }
    if(hashes.Count!=seen.Count)throw new InvalidDataException("Manifest does not match payload");
    if(!hashes.ContainsKey("Start-OfficeTelephony.cmd"))throw new InvalidDataException("Receiver launcher is missing");
    if(verifyOnly)return "Package integrity verified";
    System.Threading.Mutex receiver;
    if(System.Threading.Mutex.TryOpenExisting(@"Local\OfficeAutomation.Telephony.Desktop",out receiver)){
     receiver.Dispose();throw new IOException("Close the Office Automation telephony application before installing.");
    }
    // Install into a fresh release directory; activate only after every file is written.
    string installationRoot=root;string releaseId=DateTime.UtcNow.ToString("yyyyMMddHHmmss")+"-"+Guid.NewGuid().ToString("N").Substring(0,8);
    root=Path.Combine(root,"Releases",releaseId)+Path.DirectorySeparatorChar;
    for(string parent=root.TrimEnd(Path.DirectorySeparatorChar);!String.IsNullOrEmpty(parent);parent=Path.GetDirectoryName(parent))if(Directory.Exists(parent)&&(File.GetAttributes(parent)&FileAttributes.ReparsePoint)!=0)throw new IOException("Installation directory must not be redirected");
    Directory.CreateDirectory(root);
    string backup=Path.Combine(root,"Previous",DateTime.UtcNow.ToString("yyyyMMddHHmmss")+"-"+Guid.NewGuid().ToString("N").Substring(0,6));
    foreach(var entry in zip.Entries){
     if(entry.FullName=="SHA256SUMS" || entry.FullName.EndsWith("/") || entry.FullName.EndsWith("\\"))continue;string path=Path.GetFullPath(Path.Combine(root,entry.FullName.Replace('/',Path.DirectorySeparatorChar)));
     for(string current=path;current.StartsWith(root,StringComparison.OrdinalIgnoreCase);current=Path.GetDirectoryName(current)){if((File.Exists(current)||Directory.Exists(current))&&(File.GetAttributes(current)&FileAttributes.ReparsePoint)!=0)throw new IOException("Installation entry must not be redirected");}
     Directory.CreateDirectory(Path.GetDirectoryName(path));
     if(File.Exists(path)){string saved=Path.Combine(backup,entry.FullName.Replace('/',Path.DirectorySeparatorChar));Directory.CreateDirectory(Path.GetDirectoryName(saved));File.Copy(path,saved,false);}
     string pending=path+"."+Guid.NewGuid().ToString("N")+".new";
     try{using(var source=entry.Open())using(var target=new FileStream(pending,FileMode.CreateNew,FileAccess.Write,FileShare.None))source.CopyTo(target);if(File.Exists(path))File.Replace(pending,path,null);else File.Move(pending,path);}finally{if(File.Exists(pending))File.Delete(pending);}
    }
    string launcher=Path.Combine(installationRoot,"Start-OfficeTelephony.cmd");
    if(File.Exists(launcher)&&(File.GetAttributes(launcher)&FileAttributes.ReparsePoint)!=0)throw new IOException("Installation launcher must not be redirected");
    string pendingLauncher=launcher+"."+releaseId+".new";
    File.WriteAllText(pendingLauncher,"@echo off\r\ncall \"%~dp0Releases\\"+releaseId+"\\Start-OfficeTelephony.cmd\" %*\r\nexit /b %errorlevel%\r\n",Encoding.ASCII);
    try{if(File.Exists(launcher))File.Replace(pendingLauncher,launcher,launcher+".previous");else File.Move(pendingLauncher,launcher);}finally{if(File.Exists(pendingLauncher))File.Delete(pendingLauncher);}
    string nativeLauncher=Path.Combine(installationRoot,"OfficeAutomation.Telephony.exe");
    if(File.Exists(nativeLauncher)&&(File.GetAttributes(nativeLauncher)&FileAttributes.ReparsePoint)!=0)throw new IOException("Application launcher must not be redirected");
    string nativePending=nativeLauncher+"."+releaseId+".new";File.Copy(Path.Combine(root,"OfficeAutomation.Telephony.exe"),nativePending,false);
    try{if(File.Exists(nativeLauncher))File.Replace(nativePending,nativeLauncher,null);else File.Move(nativePending,nativeLauncher);}finally{if(File.Exists(nativePending))File.Delete(nativePending);}
    string marker=Path.Combine(installationRoot,"current-release.txt");
    if(File.Exists(marker)&&(File.GetAttributes(marker)&FileAttributes.ReparsePoint)!=0)throw new IOException("Release marker must not be redirected");
    string markerPending=marker+"."+releaseId+".new";File.WriteAllText(markerPending,releaseId,Encoding.ASCII);
    try{if(File.Exists(marker))File.Replace(markerPending,marker,marker+".previous");else File.Move(markerPending,marker);}finally{if(File.Exists(markerPending))File.Delete(markerPending);}

    return installationRoot;
   }
  }
  static bool CreateShortcut(string path,string target,string working) {
   object shell=null,shortcut=null;
   try{
    var type=Type.GetTypeFromProgID("WScript.Shell");if(type==null)return false;shell=Activator.CreateInstance(type);
    shortcut=type.InvokeMember("CreateShortcut",BindingFlags.InvokeMethod,null,shell,new object[]{path});var shortcutType=shortcut.GetType();
    if(File.Exists(path)){string previous=Convert.ToString(shortcutType.InvokeMember("TargetPath",BindingFlags.GetProperty,null,shortcut,null));if(!String.Equals(previous,target,StringComparison.OrdinalIgnoreCase))return false;}
    shortcutType.InvokeMember("TargetPath",BindingFlags.SetProperty,null,shortcut,new object[]{target});
    shortcutType.InvokeMember("WorkingDirectory",BindingFlags.SetProperty,null,shortcut,new object[]{working});
    shortcutType.InvokeMember("Description",BindingFlags.SetProperty,null,shortcut,new object[]{"تلفن سازمانی اتوماسیون اداری"});
    shortcutType.InvokeMember("IconLocation",BindingFlags.SetProperty,null,shortcut,new object[]{target+",0"});
    shortcutType.InvokeMember("Save",BindingFlags.InvokeMethod,null,shortcut,null);return true;
   }catch{return false;}
   finally{if(shortcut!=null)System.Runtime.InteropServices.Marshal.FinalReleaseComObject(shortcut);if(shell!=null)System.Runtime.InteropServices.Marshal.FinalReleaseComObject(shell);}
  }
  static bool InstallShortcuts(string root){
   string target=Path.Combine(root,"OfficeAutomation.Telephony.exe");
   bool desktop=CreateShortcut(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory),"تلفن سازمانی - Office Automation.lnk"),target,root);
   string menu=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.StartMenu),"Programs","Office Automation");Directory.CreateDirectory(menu);
   return CreateShortcut(Path.Combine(menu,"تلفن سازمانی.lnk"),target,root)||desktop;
  }

  [STAThread] static int Main(string[] args) {
   if(args.Length==1&&args[0]=="--verify"){try{Console.WriteLine(Extract(Path.GetTempPath(),true));return 0;}catch(Exception error){File.WriteAllText(Assembly.GetExecutingAssembly().Location+".verify-error.txt",error.ToString());return 1;}}
   if(args.Length==2&&args[0]=="--test-extract"){try{string full=Path.GetFullPath(args[1]);string temp=Path.GetFullPath(Path.GetTempPath());if(!full.StartsWith(temp,StringComparison.OrdinalIgnoreCase)||!Path.GetFileName(full).StartsWith("office-setup-test-",StringComparison.Ordinal))return 2;Extract(full,false);return 0;}catch(Exception error){File.WriteAllText(Assembly.GetExecutingAssembly().Location+".verify-error.txt",error.ToString());return 1;}}
   OfficeAutomation.Telephony.DesktopFont.LoadEmbedded();Application.EnableVisualStyles();Application.SetCompatibleTextRenderingDefault(false);
   if(args.Length==2&&args[0]=="--render"){using(var form=new InstallerForm()){form.ShowInTaskbar=false;form.Opacity=0;form.Show();Application.DoEvents();form.PerformLayout();using(var bitmap=new Bitmap(form.Width,form.Height)){form.DrawToBitmap(bitmap,new Rectangle(Point.Empty,form.Size));bitmap.Save(args[1],System.Drawing.Imaging.ImageFormat.Png);}}return 0;}
   Application.Run(new InstallerForm());return 0;
  }
  internal sealed class InstallerForm : Form {
   readonly Button install=new Button(),finish=new Button();readonly Label status=new Label();readonly ProgressBar progress=new ProgressBar();bool installing;
   public InstallerForm(){
    Icon=System.Drawing.Icon.ExtractAssociatedIcon(Assembly.GetExecutingAssembly().Location);Text="نصب تلفن سازمانی";Font=OfficeAutomation.Telephony.DesktopFont.Create(11,FontStyle.Regular);RightToLeft=RightToLeft.Yes;RightToLeftLayout=true;StartPosition=FormStartPosition.CenterScreen;ClientSize=new Size(650,460);MinimumSize=new Size(570,440);BackColor=Color.White;
    var layout=new TableLayoutPanel{Dock=DockStyle.Fill,Padding=new Padding(28),ColumnCount=1,RowCount=6};layout.RowStyles.Add(new RowStyle(SizeType.Absolute,65));layout.RowStyles.Add(new RowStyle(SizeType.Percent,100));layout.RowStyles.Add(new RowStyle(SizeType.Absolute,62));layout.RowStyles.Add(new RowStyle(SizeType.Absolute,35));layout.RowStyles.Add(new RowStyle(SizeType.Absolute,54));layout.RowStyles.Add(new RowStyle(SizeType.Absolute,60));
    var title=new Label{Text="راه‌اندازی تلفن سازمانی",Font=OfficeAutomation.Telephony.DesktopFont.Create(20,FontStyle.Bold),ForeColor=Color.FromArgb(49,46,129),Dock=DockStyle.Fill,AutoEllipsis=true};
    var body=new Label{Text="برنامه روی حساب کاربری فعلی ویندوز نصب می‌شود. تنظیمات مرکز تلفن، رمز داخلی‌ها و سوابق قبلی حفظ می‌شوند.\n\nپیش از نصب، تماس‌ها را پایان دهید و پنجره برنامه تلفن را ببندید.",Dock=DockStyle.Fill};
    var location=new TextBox{Text=BaseDirectory,ReadOnly=true,Multiline=true,Dock=DockStyle.Fill,RightToLeft=RightToLeft.No,BackColor=Color.FromArgb(245,247,250),BorderStyle=BorderStyle.FixedSingle};progress.Dock=DockStyle.Fill;status.Dock=DockStyle.Fill;status.Text="آماده نصب";status.AutoEllipsis=true;
    var buttons=new FlowLayoutPanel{Dock=DockStyle.Fill,FlowDirection=FlowDirection.RightToLeft};install.Text="نصب برنامه";install.Size=new Size(150,44);install.BackColor=Color.FromArgb(67,56,202);install.ForeColor=Color.White;install.FlatStyle=FlatStyle.Flat;finish.Text="بستن";finish.Size=new Size(110,44);install.Click+=Install;finish.Click+=delegate{Close();};buttons.Controls.Add(install);buttons.Controls.Add(finish);
    layout.Controls.Add(title);layout.Controls.Add(body);layout.Controls.Add(location);layout.Controls.Add(progress);layout.Controls.Add(status);layout.Controls.Add(buttons);Controls.Add(layout);AcceptButton=install;FormClosing+=delegate(object sender,FormClosingEventArgs args){if(installing)args.Cancel=true;};
   }
   async void Install(object sender,EventArgs e){installing=true;install.Enabled=false;finish.Enabled=false;progress.Style=ProgressBarStyle.Marquee;status.Text="در حال بررسی و نصب فایل‌ها…";Refresh();try{string installed=await Task.Run(()=>Extract(BaseDirectory,false));bool shortcut=InstallShortcuts(installed);status.Text=shortcut?"نصب انجام شد؛ برنامه از میانبر تلفن سازمانی قابل اجراست.":"نصب انجام شد؛ برنامه را از پوشه نصب اجرا کنید.";progress.Style=ProgressBarStyle.Continuous;progress.Value=100;install.Text="اجرای برنامه";install.Click-=Install;install.Click+=delegate{System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo(Path.Combine(installed,"OfficeAutomation.Telephony.exe")){UseShellExecute=true});};install.Enabled=true;}catch(Exception error){status.Text="نصب کامل نشد؛ برنامه را ببندید و دوباره تلاش کنید.";MessageBox.Show(error.Message,"خطای نصب",MessageBoxButtons.OK,MessageBoxIcon.Error);install.Enabled=true;progress.Style=ProgressBarStyle.Continuous;}finally{installing=false;finish.Enabled=true;}}
  }
 }
}
