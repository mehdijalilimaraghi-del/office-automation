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
   var prompt=new GreetingPrompt(wavePath,20);audio=prompt.Audio;PromptSha256=prompt.Sha256;
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
