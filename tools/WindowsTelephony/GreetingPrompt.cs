using System;
using System.IO;
using System.Text;
using System.Security.Cryptography;
using OfficeAutomation.Telephony.Diagnostics;
namespace OfficeAutomation.Telephony.Bridge {
 public sealed class GreetingPrompt {
  public byte[] Audio { get; private set; }
  public string Sha256 { get; private set; }
  public GreetingPrompt(string path,int maxSeconds) {
   long length=new FileInfo(path).Length;
   if(length<46 || length>44+maxSeconds*16000)throw new InvalidDataException("Configured greeting exceeds the bounded duration");
   byte[] file=File.ReadAllBytes(path);
   if(file.Length<46 || file.Length>44+maxSeconds*16000 || Encoding.ASCII.GetString(file,0,4)!="RIFF" || Encoding.ASCII.GetString(file,8,8)!="WAVEfmt "
    || BitConverter.ToInt32(file,16)!=16 || BitConverter.ToInt16(file,20)!=1 || BitConverter.ToInt16(file,22)!=1
    || BitConverter.ToInt32(file,24)!=8000 || BitConverter.ToInt16(file,34)!=16 || Encoding.ASCII.GetString(file,36,4)!="data"
    || BitConverter.ToInt32(file,40)!=file.Length-44 || (file.Length-44)%2!=0)throw new InvalidDataException("Use the configured 8kHz mono PCM16 greeting");
   Audio=new byte[(file.Length-44)/2];
   for(int i=0;i<Audio.Length;i++)Audio[i]=VoiceCodec.EncodeMuLaw(BitConverter.ToInt16(file,44+i*2));
   using(var hash=SHA256.Create())Sha256=BitConverter.ToString(hash.ComputeHash(file)).Replace("-","").ToLowerInvariant();
  }
 }
}
