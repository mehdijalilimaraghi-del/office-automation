using System;
using System.IO;
using System.Text;
using System.Threading;
using System.Security.Cryptography;
using OfficeAutomation.Telephony.Diagnostics;
namespace OfficeAutomation.Telephony.Bridge {
 public sealed class GreetingModeComparison : IGreetingModeComparison {
  readonly byte[] audio; int position; bool second, opened, disposed;
  public string PromptSha256 { get; private set; }
  public int PromptSamples { get { return audio.Length; } }
  public int CurrentPhase { get { return second ? 2 : 1; } }
  public string FirstMode { get { return "VTX"; } }
  public string SecondMode { get { return "VTR"; } }
  public int FirstPhaseSamples { get; private set; }
  public int SecondPhaseSamples { get; private set; }
  public bool AudioRecorded { get { return false; } }
  int PhaseSamples { get { return 8000 + ((audio.Length+159)/160)*160 + 8000; } }
  public bool NeedsDuplex { get { return !second && position >= PhaseSamples; } }
  public bool Ended { get { return disposed || (second && position >= PhaseSamples); } }
  public GreetingModeComparison(string wavePath) {
   long length=new FileInfo(wavePath).Length; if(length<46 || length>320044)throw new InvalidDataException("Greeting must be between one sample and 20 seconds");
   byte[] file=File.ReadAllBytes(wavePath);
   if(file.Length<46 || file.Length>320044 || Encoding.ASCII.GetString(file,0,4)!="RIFF" || Encoding.ASCII.GetString(file,8,8)!="WAVEfmt "
      || BitConverter.ToInt32(file,16)!=16 || BitConverter.ToInt16(file,20)!=1 || BitConverter.ToInt16(file,22)!=1
      || BitConverter.ToInt32(file,24)!=8000 || BitConverter.ToInt16(file,34)!=16 || Encoding.ASCII.GetString(file,36,4)!="data"
      || BitConverter.ToInt32(file,40)!=file.Length-44 || (file.Length-44)%2!=0) throw new InvalidDataException("Use the configured 8kHz mono PCM16 greeting, up to 20 seconds");
   audio=new byte[(file.Length-44)/2];
   for(int i=0;i<audio.Length;i++)audio[i]=VoiceCodec.EncodeMuLaw(BitConverter.ToInt16(file,44+i*2));
   using(var hash=SHA256.Create())PromptSha256=BitConverter.ToString(hash.ComputeHash(file)).Replace("-","").ToLowerInvariant();
  }
  public void Open(CancellationToken cancellation) { cancellation.ThrowIfCancellationRequested();if(opened||disposed)throw new InvalidOperationException("Invalid comparison lifecycle");opened=true; }
  public void ReceiveAudio(byte[] unused) { } // Never retain caller audio.
  public byte[] TransmitAudio(int count) {
   if(!opened || disposed || count!=160 || Ended || NeedsDuplex)throw new InvalidOperationException("Invalid comparison phase");
   var result=new byte[count];
   for(int i=0;i<count;i++){int index=position++-8000;result[i]=index>=0&&index<audio.Length?audio[index]:(byte)255;}
   if(second)SecondPhaseSamples+=count;else FirstPhaseSamples+=count;
   return result;
  }
  public void BeginDuplex(){if(!opened||disposed||!NeedsDuplex)throw new InvalidOperationException("Unexpected duplex transition");second=true;position=0;}
  public void Dispose(){disposed=true;}
 }
}
