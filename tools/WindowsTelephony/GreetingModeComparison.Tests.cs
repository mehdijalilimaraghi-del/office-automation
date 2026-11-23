using System;
using System.IO;
using System.Linq;
using System.Threading;
using System.Collections.Generic;
using OfficeAutomation.Telephony.Diagnostics;
using OfficeAutomation.Telephony.Bridge;
public sealed class ShortGreetingComparison : IGreetingModeComparison {
 public bool Second, Disposed; public int Position, PhaseFrames=4;
 public bool NeedsDuplex {get{return !Second&&Position>=PhaseFrames;}}
 public bool Ended {get{return Second&&Position>=PhaseFrames;}}
 public void Open(CancellationToken c){c.ThrowIfCancellationRequested();}
 public void ReceiveAudio(byte[] audio){}
 public byte[] TransmitAudio(int n){Position++;return Enumerable.Repeat((byte)255,n).ToArray();}
 public void BeginDuplex(){if(!NeedsDuplex)throw new Exception("early transition");Second=true;Position=0;}
 public void Dispose(){Disposed=true;}
}
public static class GreetingComparisonChecks {
 static void Check(bool ok,string why){if(!ok)throw new Exception(why);}
 static void Finish(VoiceSession s){for(int i=0;i<1600&&!s.Completed;i++)Thread.Sleep(5);if(!s.Completed){s.Cancel();throw new Exception("Comparison test timeout");}}
 public static string Run(){
  string path=Path.Combine(Path.GetTempPath(),"office-greeting-comparison-"+Guid.NewGuid().ToString("N")+".wav");
  try{
   byte[] source=Enumerable.Range(0,237).Select(i=>VoiceCodec.EncodeMuLaw((short)(3000*Math.Sin(i)))).ToArray();
   VoiceCodec.SaveWave(path,source);var endpoint=new GreetingModeComparison(path);endpoint.Open(CancellationToken.None);
   var first=new List<byte>();while(!endpoint.NeedsDuplex)first.AddRange(endpoint.TransmitAudio(160));
   bool rejected=false;try{endpoint.TransmitAudio(160);}catch(InvalidOperationException){rejected=true;}Check(rejected,"Premature second phase accepted");
   endpoint.BeginDuplex();var second=new List<byte>();while(!endpoint.Ended)second.AddRange(endpoint.TransmitAudio(160));
   Check(first.SequenceEqual(second)&&endpoint.FirstPhaseSamples==endpoint.SecondPhaseSamples&&endpoint.PromptSamples==237&&endpoint.PromptSha256.Length==64,"Comparison must send identical padded audio in both modes");
   Check(first.Take(8000).All(x=>x==255)&&first.Skip(8000).Take(237).SequenceEqual(source.Select(x=>VoiceCodec.EncodeMuLaw(VoiceCodec.DecodeMuLaw(x)))),"Comparison changed leading silence or source amplitude");
   endpoint.Dispose();File.WriteAllText(path,"not audio");rejected=false;try{new GreetingModeComparison(path);}catch(InvalidDataException){rejected=true;}Check(rejected,"Invalid greeting accepted");
  }finally{if(File.Exists(path))File.Delete(path);}
  var port=new DiagnosticFakePort{TransmitStopDelayMilliseconds=350,ResidualAudioOnStop=true};var paired=new ShortGreetingComparison();
  var session=new VoiceSession(port,Path.GetTempPath(),300,60000,paired);session.Start();Finish(session);
  var r=session.Result;
  int start=r.Trace.FindIndex(t=>t.Command=="AT+VTX"),stop=r.Trace.FindIndex(t=>t.Command=="<DLE><ETX>"),duplex=r.Trace.FindIndex(t=>t.Command=="AT+VTR");
  Check(r.Status=="comparison_completed"&&r.EndReason=="comparison_completed"&&r.TransmitOnlyStarted&&r.SwitchedToDuplex&&r.CleanupConfirmed&&paired.Disposed,"Same-call comparison did not complete/restore");
  Check(start>=0&&stop>start&&duplex>stop&&r.ModeTransitionMilliseconds>=300&&r.MaxTransmitLagMilliseconds<250,"Control-mode delay must not be counted as streaming underrun");
  Check(r.Trace[stop].Response=="OK"&&!r.Trace[stop].Response.Contains("private"),"Stop reply leaked residual audio");
  Check(session.RecordingPath==null&&!port.Commands.Contains("AT+VGT?")&&r.EffectiveFlowControl=="2,2","Comparison must not record or change gain/flow");
  port=new DiagnosticFakePort{RejectTransmitOnly=true};paired=new ShortGreetingComparison();session=new VoiceSession(port,Path.GetTempPath(),300,60000,paired);session.Start();Finish(session);
  Check(session.Result.Status=="failed"&&session.Result.CleanupConfirmed&&!session.Result.TransmitOnlyStarted,"Rejected VTX must restore safely");
  port=new DiagnosticFakePort{RejectDuplex=true};paired=new ShortGreetingComparison();session=new VoiceSession(port,Path.GetTempPath(),300,60000,paired);session.Start();Finish(session);
  Check(session.Result.Status=="failed"&&session.Result.FailureStage=="switch_to_duplex"&&session.Result.CleanupConfirmed,"Failed duplex transition must release call");
  port=new DiagnosticFakePort();paired=new ShortGreetingComparison{PhaseFrames=10000};session=new VoiceSession(port,Path.GetTempPath(),300,60000,paired);session.Start();
  for(int i=0;i<500&&session.Progress!="streaming"&&!session.Completed;i++)Thread.Sleep(5);session.Cancel();Finish(session);
  Check(session.Result.Cancelled&&session.Result.CleanupConfirmed&&session.Result.Trace.Any(t=>t.Command=="<DLE><ETX>"),"Cancellation during VTX must use its correct stop command");
  port=new DiagnosticFakePort();paired=new ShortGreetingComparison();session=new VoiceSession(port,Path.GetTempPath(),300,60000,paired);port.OnTransmitOnlyStop=()=>session.Cancel();session.Start();Finish(session);
  Check(session.Result.Cancelled&&session.Result.CleanupConfirmed&&!session.Result.SwitchedToDuplex&&!port.Commands.Contains("AT+VTR"),"Cancellation between modes must not start duplex");
  return "PASS: identical greeting bytes/amplitude, padding/hash, VTX-to-VTR sequencing, drain-delay accounting, no-recording/no-gain, rejected modes and cancellation cleanup. No modem opened.";
 }
}