using System;
using System.IO;
using OfficeAutomation.Telephony.Bridge;
using OfficeAutomation.Telephony.Diagnostics;
public static class NativeEchoChecks {
 public static string Run(string path){
  foreach(int scenario in new[]{0,1,2,3}){
   int delay=new[]{80,512,1536,512}[scenario],amplitude=scenario==3?28000:8000;
   var random=new Random(834+delay);var nearRandom=new Random(29847+scenario);var history=new short[96000];
   double raw=0,clean=0,nearEnergy=0,error=0,cross=0,onsetEnergy=0,onsetCross=0;const int train=64000,total=80000;
   using(var echo=new NativeLineEcho(path))using(var baseline=new NativeLineEcho(path)){
    for(int start=0;start<96000;start+=160){
     var reference=new short[160];var received=new short[160];var near=new short[160];
     for(int j=0;j<160;j++){
      int i=start+j;reference[j]=VoiceCodec.DecodeMuLaw(VoiceCodec.EncodeMuLaw((short)random.Next(-amplitude,amplitude+1)));history[i]=reference[j];
      short reflected=i>=delay?(short)(history[i-delay]*0.4):(short)0;
      if(i>=total)near[j]=(short)nearRandom.Next(-2500,2501);
      received[j]=VoiceCodec.DecodeMuLaw(VoiceCodec.EncodeMuLaw((short)(reflected+near[j])));
     }
     var output=echo.ProcessFrame(reference,received);var nearInput=new short[160];for(int j=0;j<160;j++)nearInput[j]=VoiceCodec.DecodeMuLaw(VoiceCodec.EncodeMuLaw(near[j]));var expectedNear=baseline.ProcessFrame(new short[160],nearInput);
     for(int j=0;j<160;j++){
      int i=start+j;if(i>=train&&i<total){raw+=(double)received[j]*received[j];clean+=(double)output[j]*output[j];}
      if(i>=total){nearEnergy+=(double)expectedNear[j]*expectedNear[j];error+=(double)(output[j]-expectedNear[j])*(output[j]-expectedNear[j]);cross+=(double)output[j]*expectedNear[j];if(i<total+1600){onsetEnergy+=(double)expectedNear[j]*expectedNear[j];onsetCross+=(double)output[j]*expectedNear[j];}}
     }
    }
   }
   double reduction=10*Math.Log10(raw/Math.Max(1,clean)),distortion=10*Math.Log10(error/nearEnergy),gain=20*Math.Log10(Math.Abs(cross/nearEnergy));
   Console.WriteLine("DSP delay="+delay+" amplitude="+amplitude+"; echo reduction="+reduction.ToString("F2")+"dB; double-talk error="+distortion.ToString("F2")+"dB; near gain="+gain.ToString("F2")+"dB");
   if(reduction<15||distortion>-12||Math.Abs(gain)>1.5||Math.Abs(20*Math.Log10(Math.Abs(onsetCross/onsetEnergy)))>1.5)throw new Exception("Native echo/double-talk acceptance failed");
  }
  CheckAlignment(path);CheckLoading(path);
  return "PASS: pinned DSP reduces synthetic line echo and preserves independent double talk at ordinary and high input levels; no modem or real call.";
 }
 static void Require(bool ok,string message){if(!ok)throw new Exception(message);}
 static void CheckLoading(string path){
  bool rejected=false;try{new NativeLineEcho(@"\\host\share\libspeexdsp-1.dll");}catch(ArgumentException){rejected=true;}Require(rejected,"UNC DLL must be rejected");
  string wrong=Path.Combine(Path.GetTempPath(),"office-wrong-dsp-"+Guid.NewGuid().ToString("N")+".dll");
  try{File.WriteAllText(wrong,"invalid");rejected=false;try{new NativeLineEcho(wrong);}catch(InvalidDataException){rejected=true;}Require(rejected,"Unpinned DLL must be rejected before load");}finally{File.Delete(wrong);}
  var disposed=new NativeLineEcho(path);disposed.Dispose();disposed.Dispose();rejected=false;try{disposed.ProcessFrame(new short[160],new short[160]);}catch(ObjectDisposedException){rejected=true;}Require(rejected,"Disposed native state must not execute");
 }
 static void CheckAlignment(string path){
  using(var processor=new ModemLineEcho(path))using(var baseline=new NativeLineEcho(path)){
   processor.StartDuplex();var source=new byte[8000];var expected=new byte[8000];
   for(int i=0;i<source.Length;i++)source[i]=VoiceCodec.EncodeMuLaw((short)(3500*Math.Sin(2*Math.PI*700*i/8000.0)));
   double inputEnergy=0,outputEnergy=0;
   for(int n=0;n<8000;n+=160){var rx=new short[160];for(int j=0;j<160;j++){rx[j]=VoiceCodec.DecodeMuLaw(source[n+j]);inputEnergy+=(double)rx[j]*rx[j];}var clean=baseline.ProcessFrame(new short[160],rx);for(int j=0;j<160;j++){expected[n+j]=VoiceCodec.EncodeMuLaw(clean[j]);outputEnergy+=(double)clean[j]*clean[j];}}
   Require(Math.Abs(10*Math.Log10(outputEnergy/inputEnergy))<1.5,"No-reference speech must retain its level");
   var silence=new byte[8000];for(int i=0;i<silence.Length;i++)silence[i]=255;
   // Delayed RX detection and arbitrary serial chunk boundaries must not discard or re-time samples.
   processor.ObserveTransmit(silence);var result=new System.Collections.Generic.List<byte>();int offset=0;
   foreach(int length in new[]{3840,1,319,1601,2239}){var chunk=new byte[length];Array.Copy(source,offset,chunk,0,length);result.AddRange(processor.ProcessReceive(chunk));offset+=length;}
   Require(result.Count==expected.Length,"Chunked processing must preserve all complete samples");for(int i=0;i<expected.Length;i++)Require(result[i]==expected[i],"Chunk boundaries changed paired DSP output");
   processor.StartDuplex();Require(processor.ProcessReceive(new byte[320]).Length==0,"RX before reference must wait");processor.ObserveTransmit(new byte[320]);Require(processor.ProcessReceive(new byte[0]).Length==320,"Deferred RX must be retained");
   processor.StartDuplex();processor.ObserveTransmit(new byte[16000]);bool rejected=false;try{processor.ObserveTransmit(new byte[1]);}catch(InvalidOperationException){rejected=true;}Require(rejected,"Unbounded TX history must fail safely");
   processor.StartDuplex();processor.ProcessReceive(new byte[16000]);rejected=false;try{processor.ProcessReceive(new byte[1]);}catch(InvalidOperationException){rejected=true;}Require(rejected,"Unbounded RX history must fail safely");
   Require(processor.Metrics.Epochs==4,"Duplex epoch reset must be explicit");
  }
  Console.WriteLine("PASS: sample alignment, initial framing burst, split reads, bounded queues, preserved speech level, epoch reset, native pinning and disposal");
 }
}
