using System;
using System.IO;
using System.Threading;
using OfficeAutomation.Telephony.Diagnostics;
using OfficeAutomation.Telephony.Bridge;
public static class IncomingReceiverChecks {
 static void Check(bool condition,string message){if(!condition)throw new Exception(message);}
 static void Finish(VoiceSession session){var watch=System.Diagnostics.Stopwatch.StartNew();while(!session.Completed&&watch.ElapsedMilliseconds<12000)Thread.Sleep(5);if(!session.Completed){session.Cancel();throw new Exception("Receiver test timeout");}}
 public static string Run(string library){
  for(int call=0;call<2;call++){
   var port=new DiagnosticFakePort();var endpoint=new BridgeLifecycleEndpoint();var echo=new ModemLineEcho(library);
   var session=VoiceSession.ForIncomingCalls(port,Path.GetTempPath(),endpoint,echo);session.Start();Finish(session);
   Check(session.Result.Status=="bridged" && IncomingReception.CanRearm(session.Result,false,true),"Sequential incoming call cannot rearm");
   Check(port.Disposed&&endpoint.Disposed&&session.Result.CleanupConfirmed&&echo.Metrics.Epochs==1&&echo.Metrics.ProcessedSamples>1000,"Each call must own and release a fresh echo/modem/SIP session");
   Check(!IncomingReception.CanRearm(session.Result,true,true),"Operator stop must win over successful hangup");
   Check(!IncomingReception.CanRearm(session.Result,false,false),"Report failure must prevent another answer");
  }
  foreach(bool rejectCleanup in new[]{false,true}){
   var port=new DiagnosticFakePort{RejectHangup=rejectCleanup};var endpoint=new BridgeLifecycleEndpoint{FailReceive=!rejectCleanup};var echo=new ModemLineEcho(library);
   var session=VoiceSession.ForIncomingCalls(port,Path.GetTempPath(),endpoint,echo);session.Start();Finish(session);
   Check(!IncomingReception.CanRearm(session.Result,false,true)&&port.Disposed&&endpoint.Disposed,"Media/cleanup failure must stop reception");
  }
  var idlePort=new DiagnosticFakePort{NoRing=true};var idleEndpoint=new BridgeLifecycleEndpoint();var idle=VoiceSession.ForIncomingCalls(idlePort,Path.GetTempPath(),idleEndpoint,new ModemLineEcho(library));
  idle.Start();Thread.Sleep(150);idle.Cancel();Finish(idle);Check(!idle.Result.Answered&&idle.Result.Cancelled&&idle.Result.CleanupConfirmed&&idlePort.Disposed&&!IncomingReception.CanRearm(idle.Result,false,true),"Stopping an idle receiver must release the port without answering");
  var result=new VoiceResult();for(int second=0;second<=3600;second++)result.AddSampleWindow(new VoiceSampleWindow{Milliseconds=second*1000,TransmittedSamples=second*8000,ReceivedSamples=second*8000,RawReceivedSamples=second*10000});
  Check(result.SampleWindows.Count==120&&result.BridgeTimingReady,"Long-call timing diagnostics must remain bounded and accurate");
  var decoder=new VoiceDecoder();for(int i=0;i<10000;i++)decoder.Feed(new byte[]{16,(byte)'s'},2);Check(decoder.Events.Count==1&&decoder.Events.Contains("silence"),"Repeated modem events must not grow memory");
  bool rejected=false;try{VoiceSession.ForIncomingCalls(new DiagnosticFakePort(),Path.GetTempPath(),null,null);}catch(ArgumentNullException){rejected=true;}Check(rejected,"Receiver must never fall back to recording or omit echo processing");
  return "PASS: sequential real-DSP simulated calls, safe rearm/stop/report failure, media/cleanup failure, idle cancellation and bounded hour-long diagnostics; no physical line opened";
 }
}
