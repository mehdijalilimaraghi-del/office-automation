using System;
using System.IO;
using System.Collections.Generic;
using System.Diagnostics;
using OfficeAutomation.Telephony.Diagnostics;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
namespace OfficeAutomation.Telephony.Bridge {
 public sealed class NativeLineEcho : IDisposable {
  public const string ExpectedSha256="6a832c7492e0f3e8e637d817568b58225d9e7b87ad68c6fe5b68ddebb3adf7b7";
  [DllImport("kernel32.dll",CharSet=CharSet.Unicode,SetLastError=true)] static extern IntPtr LoadLibraryEx(string path,IntPtr file,uint flags);
  [DllImport("kernel32.dll",CharSet=CharSet.Ansi,ExactSpelling=true,SetLastError=true)] static extern IntPtr GetProcAddress(IntPtr library,string name);
  [DllImport("kernel32.dll")] static extern bool FreeLibrary(IntPtr library);
  [UnmanagedFunctionPointer(CallingConvention.Cdecl)] delegate IntPtr Init(int frameSize,int filterLength);
  [UnmanagedFunctionPointer(CallingConvention.Cdecl)] delegate void Cancel(IntPtr state,[In]short[] receive,[In]short[] reference,[Out]short[] output);
  [UnmanagedFunctionPointer(CallingConvention.Cdecl)] delegate int Control(IntPtr state,int request,ref int value);
  [UnmanagedFunctionPointer(CallingConvention.Cdecl)] delegate void Destroy(IntPtr state);
  [UnmanagedFunctionPointer(CallingConvention.Cdecl)] delegate void ResetState(IntPtr state);
  IntPtr library,state;Cancel cancel;Destroy destroy;ResetState reset;
  public NativeLineEcho(string libraryPath) {
   string path=Path.GetFullPath(libraryPath);
   if(path.StartsWith(@"\\",StringComparison.Ordinal))throw new ArgumentException("DSP library must be local");
   if(IntPtr.Size!=8)throw new PlatformNotSupportedException("The tested DSP requires a 64-bit process");
   using(var stream=File.OpenRead(path))using(var hash=SHA256.Create())
    if(BitConverter.ToString(hash.ComputeHash(stream)).Replace("-","").ToLowerInvariant()!=ExpectedSha256)throw new InvalidDataException("DSP library hash does not match the pinned release");
   try{
    library=LoadLibraryEx(path,IntPtr.Zero,0x900); // Restrict dependencies to component directory and System32.
    if(library==IntPtr.Zero)throw new InvalidOperationException("line_echo_library_load_failed_"+Marshal.GetLastWin32Error());
    var init=(Init)Marshal.GetDelegateForFunctionPointer(Find("speex_echo_state_init"),typeof(Init));
    cancel=(Cancel)Marshal.GetDelegateForFunctionPointer(Find("speex_echo_cancellation"),typeof(Cancel));
    destroy=(Destroy)Marshal.GetDelegateForFunctionPointer(Find("speex_echo_state_destroy"),typeof(Destroy));
    var control=(Control)Marshal.GetDelegateForFunctionPointer(Find("speex_echo_ctl"),typeof(Control));
    reset=(ResetState)Marshal.GetDelegateForFunctionPointer(Find("speex_echo_state_reset"),typeof(ResetState));
    state=init(160,2048);
    if(state==IntPtr.Zero)throw new InvalidOperationException("line_echo_allocation_failed");
    int rate=8000;if(control(state,24,ref rate)!=0)throw new InvalidOperationException("line_echo_rate_failed");
    // Only linear acoustic/line cancellation; no denoiser, AGC, clipping or nonlinear speech gate.
   }catch{Dispose();throw;}
  }
  IntPtr Find(string name){var address=GetProcAddress(library,name);if(address==IntPtr.Zero)throw new InvalidOperationException("line_echo_export_missing");return address;}
  public void Reset(){if(state==IntPtr.Zero)throw new ObjectDisposedException("NativeLineEcho");reset(state);}
  public short[] ProcessFrame(short[] reference,short[] receive){
   if(state==IntPtr.Zero)throw new ObjectDisposedException("NativeLineEcho");
   if(reference==null||receive==null||reference.Length!=160||receive.Length!=160)throw new ArgumentException("Echo processing requires paired 20ms frames");
   var output=new short[160];cancel(state,receive,reference,output);return output;
  }
  public void Dispose(){if(state!=IntPtr.Zero){destroy(state);state=IntPtr.Zero;}if(library!=IntPtr.Zero){FreeLibrary(library);library=IntPtr.Zero;}}
 }
 public sealed class LineEchoMetrics {
  public string Provider = "SpeexDSP 1.2.1-2 x64";
  public int FrameSamples = 160, TailSamples = 2048, Epochs, MaxReferenceQueue, MaxReceiveQueue;
  public long ReferenceSamples, ReceiveSamples, ProcessedSamples;
  public double MaxFrameMilliseconds;
 }
 // Pair by sample position, not arrival time: modem framing can initially retain ~480ms of RX.
 // The exact bytes successfully written to VTR are the reference; VTX greeting never trains it.
 public sealed class ModemLineEcho : IVoiceDuplexProcessor {
  readonly NativeLineEcho native;
  readonly Queue<short> reference = new Queue<short>(), receive = new Queue<short>();
  readonly LineEchoMetrics metrics = new LineEchoMetrics();
  bool active, disposed;
  public LineEchoMetrics Metrics { get { return metrics; } }
  public ModemLineEcho(string libraryPath){native=new NativeLineEcho(libraryPath);}
  void Check(){if(disposed)throw new ObjectDisposedException("ModemLineEcho");if(!active)throw new InvalidOperationException("line_echo_duplex_not_started");}
  public void StartDuplex(){if(disposed)throw new ObjectDisposedException("ModemLineEcho");native.Reset();reference.Clear();receive.Clear();active=true;metrics.Epochs++;}
  static void Append(Queue<short> queue,byte[] samples){
   if(samples==null)throw new ArgumentNullException("samples");
   if(queue.Count+samples.Length>16000)throw new InvalidOperationException("line_echo_clock_alignment_overflow");
   foreach(byte sample in samples)queue.Enqueue(VoiceCodec.DecodeMuLaw(sample));
  }
  public void ObserveTransmit(byte[] samples){Check();Append(reference,samples);metrics.ReferenceSamples+=samples.Length;metrics.MaxReferenceQueue=Math.Max(metrics.MaxReferenceQueue,reference.Count);}
  public byte[] ProcessReceive(byte[] samples){
   Check();Append(receive,samples);metrics.ReceiveSamples+=samples.Length;metrics.MaxReceiveQueue=Math.Max(metrics.MaxReceiveQueue,receive.Count);
   var output=new List<byte>();
   while(reference.Count>=160 && receive.Count>=160){
    var tx=new short[160];var rx=new short[160];for(int i=0;i<160;i++){tx[i]=reference.Dequeue();rx[i]=receive.Dequeue();}
    long start=Stopwatch.GetTimestamp();var clean=native.ProcessFrame(tx,rx);
    metrics.MaxFrameMilliseconds=Math.Max(metrics.MaxFrameMilliseconds,1000.0*(Stopwatch.GetTimestamp()-start)/Stopwatch.Frequency);
    foreach(short sample in clean)output.Add(VoiceCodec.EncodeMuLaw(sample));metrics.ProcessedSamples+=160;
   }
   return output.ToArray();
  }
  public void Dispose(){if(disposed)return;disposed=true;reference.Clear();receive.Clear();native.Dispose();}
 }
}
