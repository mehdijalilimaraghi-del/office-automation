using System;
using System.IO;
using System.Text.RegularExpressions;
using System.Windows.Forms;
using System.Diagnostics;
namespace OfficeAutomation.Telephony.Desktop {
 internal static class Launcher {
  static string ResolveRuntime() {
   string root=AppDomain.CurrentDomain.BaseDirectory;
   if(File.Exists(Path.Combine(root,"Start-ModemBridge.ps1")))return root;
   string marker=Path.Combine(root,"current-release.txt");
   if(!File.Exists(marker))throw new InvalidDataException("Installed release is missing");
   string id=File.ReadAllText(marker).Trim();
   if(!Regex.IsMatch(id,"\\A[0-9]{14}-[a-f0-9]{8}\\z"))throw new InvalidDataException("Invalid installed release");
   return Path.Combine(root,"Releases",id);
  }
  [STAThread] static int Main(string[] args) {
   try {
    string root=ResolveRuntime();
    foreach(string file in new[]{"Start-ModemBridge.ps1","TelephonyDesktop.psm1","TelephonyConnection.psm1","TelephonyConfiguration.psm1","Invoke-TelephonyOperation.ps1","PbxLab.psm1","Normalize-Prompt.cs","Engine/FreeSwitchConsole.exe","Dsp/libspeexdsp-1.dll"})
     if(!File.Exists(Path.Combine(root,file)))throw new FileNotFoundException("Application file is missing: "+file);
    if(args.Length==1&&args[0]=="--verify-runtime")return 0;
    if(args.Length!=0)return 2;
    string powershell=Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows),"System32","WindowsPowerShell","v1.0","powershell.exe");
    var start=new ProcessStartInfo(powershell,"-NoProfile -ExecutionPolicy Bypass -STA -WindowStyle Hidden -File \""+Path.Combine(root,"Start-ModemBridge.ps1")+"\" -DesktopMode -EngineDirectory \""+Path.Combine(root,"Engine")+"\"");
    start.UseShellExecute=false;start.CreateNoWindow=true;start.WindowStyle=ProcessWindowStyle.Hidden;start.WorkingDirectory=root;
    Process.Start(start);return 0;
   }catch(Exception error){
    if(args.Length==0)MessageBox.Show("شروع برنامه ممکن نشد. نصب برنامه را بررسی کنید.\n\n"+error.Message,"تلفن سازمانی",MessageBoxButtons.OK,MessageBoxIcon.Error);
    return 1;
   }
  }
 }
}
