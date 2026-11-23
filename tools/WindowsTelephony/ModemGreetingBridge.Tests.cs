using System;
using System.IO;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Collections.Generic;
using OfficeAutomation.Telephony.Diagnostics;
using OfficeAutomation.Telephony.Bridge;
public sealed class GreetingAudioFixture : IVoiceAudioEndpoint {
 public bool Opened,Disposed,Received; public bool Ended {get;set;}
 public void Open(CancellationToken c){c.ThrowIfCancellationRequested();Opened=true;}
 public byte[] TransmitAudio(int count){return Enumerable.Repeat((byte)137,count).ToArray();}
 public void ReceiveAudio(byte[] data){Received=true;}
 public void Dispose(){Disposed=true;}
}
public sealed class GreetingControlFixture : IGreetingCallControl {
 public bool Opened,Started,Disposed,RejectOpen,RejectStart;
 public void Open(string channel,string callId,CancellationToken c){c.ThrowIfCancellationRequested();if(RejectOpen)throw new IOException("rejected_control");Opened=true;}
 public void BeginMenu(CancellationToken c){c.ThrowIfCancellationRequested();if(RejectStart)throw new IOException("rejected_menu");Started=true;}
 public void Dispose(){Disposed=true;}
}
public static class ModemGreetingChecks {
 static void Check(bool value,string why){if(!value)throw new Exception(why);}
 static void Wait(VoiceSession session){for(int i=0;i<1800&&!session.Completed;i++)Thread.Sleep(5);if(!session.Completed){session.Cancel();throw new Exception("Session timeout");}}
 public static string Run(){
  string path=Path.Combine(Path.GetTempPath(),"modem-greeting-"+Guid.NewGuid().ToString("N")+".wav");
  try{
   VoiceCodec.SaveWave(path,Enumerable.Repeat((byte)128,237).ToArray());
   var audio=new GreetingAudioFixture();var control=new GreetingControlFixture();
   var endpoint=new ModemGreetingBridge(audio,control,path,"fixture","fixture");endpoint.Open(CancellationToken.None);
   Check(control.Opened&&audio.Opened&&!endpoint.MenuStarted,"Authentication must precede playback");
   endpoint.ReceiveAudio(new byte[160]);Check(!audio.Received,"Caller audio must not enter parked menu");
   var played=new List<byte>();while(!endpoint.NeedsDuplex)played.AddRange(endpoint.TransmitAudio(160));
   var source=new GreetingPrompt(path,30);
   Check(played.Take(8000).All(x=>x==255)&&played.Skip(8000).Take(237).SequenceEqual(source.Audio),"Greeting source was changed");
   Check(!control.Started,"Menu started before modem duplex readiness");
   endpoint.BeginDuplex();Check(endpoint.MenuStarted&&control.Started,"Menu not started at handoff");
   Check(endpoint.TransmitAudio(160).All(x=>x==137),"Conversation audio changed");
   endpoint.ReceiveAudio(new byte[160]);Check(audio.Received,"Caller audio not bridged after greeting");
   endpoint.Dispose();Check(audio.Disposed&&control.Disposed,"Endpoints not closed");
   // Failure before modem answer and after the VTX-to-VTR transition both restore state.
   foreach(bool failOpen in new[]{true,false}){
    audio=new GreetingAudioFixture();control=new GreetingControlFixture{RejectOpen=failOpen,RejectStart=!failOpen};
    endpoint=new ModemGreetingBridge(audio,control,path,"fixture","fixture");var port=new DiagnosticFakePort();
    var session=new VoiceSession(port,Path.GetTempPath(),300,6000,endpoint);session.Start();Wait(session);
    Check(session.Result.Status=="failed"&&session.Result.CleanupConfirmed&&audio.Disposed&&control.Disposed,"Failure cleanup missing");
    Check(session.Result.Answered!=failOpen,"Rejected controller answered the modem");
    if(!failOpen)Check(session.Result.TransmitOnlyStarted&&session.Result.DuplexStarted&&session.Result.FailureStage=="switch_to_duplex","Transition failure stage lost");
   }
   control=new GreetingControlFixture();audio=new GreetingAudioFixture();endpoint=new ModemGreetingBridge(audio,control,path,"fixture","fixture");
   var cancelled=new VoiceSession(new DiagnosticFakePort(),Path.GetTempPath(),300,6000,endpoint);cancelled.Start();
   for(int i=0;i<500&&cancelled.Progress!="streaming"&&!cancelled.Completed;i++)Thread.Sleep(5);
   cancelled.Cancel();Wait(cancelled);Check(cancelled.Result.Cancelled&&cancelled.Result.CleanupConfirmed&&!control.Started,"Cancelled greeting started menu or leaked call");
  }finally{if(File.Exists(path))File.Delete(path);}
  var timing=new VoiceResult{DuplexStartedAtMilliseconds=13000};
  for(int i=0;i<=20;i++)timing.SampleWindows.Add(new VoiceSampleWindow{Milliseconds=i*1000,TransmittedSamples=i*8000,ReceivedSamples=Math.Max(0,i-13)*8000});
  Check(timing.BridgeTimingReady&&timing.ReceiveRateAfterWarmup==8000&&timing.TransmitRateAfterWarmup==8000,"Greeting time polluted duplex rate");
  timing.SampleWindows.RemoveAll(w=>w.Milliseconds>16000);
  Check(!timing.BridgeTimingReady,"Insufficient post-greeting timing was accepted");
  CheckControlProtocol(false);CheckControlProtocol(true);
  return "PASS: exact greeting payload, delayed menu, unchanged duplex audio, pre-answer rejection, handoff failure, cancellation/cleanup; bounded local control rejects dialog mismatch. No physical modem.";
 }
 static void CheckControlProtocol(bool mismatch){
  var listener=new TcpListener(IPAddress.Loopback,0);listener.Start();int port=((IPEndPoint)listener.LocalEndpoint).Port;
  string channel=Guid.NewGuid().ToString(),call=Guid.NewGuid().ToString("N");var commands=new List<string>();
  var server=Task.Factory.StartNew(()=>{
   using(var client=listener.AcceptTcpClient()){
    var stream=client.GetStream();stream.ReadTimeout=3000;
    Action<string> send=s=>{byte[] b=Encoding.ASCII.GetBytes(s);stream.Write(b,0,b.Length);};
    send("Content-Type: auth/request\n\n");
    while(true){var command=new StringBuilder();int b;while((b=stream.ReadByte())>=0){command.Append((char)b);if(command.ToString().EndsWith("\n\n"))break;}if(b<0)break;
     string text=command.ToString().Trim();commands.Add(text);
     if(text.StartsWith("auth ")){send("Content-Type: command/reply\nReply-Text: +OK accepted\n\n");continue;}
     string body=text.EndsWith(" sip_call_id")?(mismatch?"wrong":call):text.EndsWith(" current_application")?"park":"+OK";
     send("Content-Type: api/response\nContent-Length: "+body.Length+"\n\n"+body);
    }
   }
  });
  try{using(var control=new GreetingCallControl(port,new string('a',48))){bool rejected=false;try{control.Open(channel,call,CancellationToken.None);if(!mismatch)control.BeginMenu(CancellationToken.None);}catch(IOException){rejected=true;}Check(rejected==mismatch,"Control dialog validation failed");}}
  finally{listener.Stop();if(!server.Wait(5000))throw new Exception("Control fixture did not close");}
  Check(commands.All(s=>s.StartsWith("auth ")||s=="api uuid_getvar "+channel+" sip_call_id"||s=="api uuid_getvar "+channel+" current_application"||s=="api uuid_transfer "+channel+" 9197 XML office_modem_menu"),"Unexpected controller command");
  Check(commands.Any(s=>s.Contains("uuid_transfer"))!=mismatch,"Mismatched dialog was transferred");
 }
}
