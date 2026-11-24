using System;
using System.Drawing;
using System.Drawing.Text;
using System.IO;
using System.Runtime.InteropServices;
namespace OfficeAutomation.Telephony {
 public static class DesktopFont {
  private static readonly PrivateFontCollection Fonts=new PrivateFontCollection();
  private static IntPtr memory=IntPtr.Zero;
  [DllImport("gdi32.dll")] private static extern IntPtr AddFontMemResourceEx(IntPtr data,uint size,IntPtr reserved,ref uint count);
  public static void Load(string file) {if(Fonts.Families.Length==0)LoadBytes(File.ReadAllBytes(file));}
  public static void LoadEmbedded() {
   if(Fonts.Families.Length>0)return;
   using(var source=typeof(DesktopFont).Assembly.GetManifestResourceStream("OfficeAutomation.Vazirmatn.ttf")){
    if(source==null)throw new InvalidDataException("Persian font is missing");
    using(var buffer=new MemoryStream()){source.CopyTo(buffer);LoadBytes(buffer.ToArray());}
   }
  }
  private static void LoadBytes(byte[] data){
   memory=Marshal.AllocCoTaskMem(data.Length);Marshal.Copy(data,0,memory,data.Length);
   uint count=0;if(AddFontMemResourceEx(memory,(uint)data.Length,IntPtr.Zero,ref count)==IntPtr.Zero)throw new InvalidDataException("Persian font cannot be loaded");
   Fonts.AddMemoryFont(memory,data.Length);
  }
  public static Font Create(float size,FontStyle style){if(Fonts.Families.Length==0)throw new InvalidOperationException("Font not loaded");return new Font(Fonts.Families[0],size,style,GraphicsUnit.Point);}
 }
}
