using System;
using System.IO;
using System.Net.Sockets;
using System.Collections.Generic;
using System.Diagnostics;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using OfficeAutomation.Telephony.Diagnostics;
namespace OfficeAutomation.Telephony.Bridge {
 public interface IGreetingCallControl : IDisposable {
  void Open(string channel,string callId,CancellationToken cancellation);
  void BeginMenu(CancellationToken cancellation,bool hardwareDigits);
  int RelayDigits(string digits,CancellationToken cancellation);
 }
 // Fixed loopback-only commands for this authenticated SIP dialog. No general API exposed.
 public sealed class GreetingCallControl : IGreetingCallControl {
  readonly int port; readonly string secret;
  TcpClient client; NetworkStream stream; string channel; CancellationToken cancellation; Stopwatch deadline;
  sealed class Frame { public Dictionary<string,string> Headers=new Dictionary<string,string>(StringComparer.OrdinalIgnoreCase); public string Body; }
  public GreetingCallControl(int port,string secret) {
   if(port<1024||port>65535||!Regex.IsMatch(secret??"","\\A[a-f0-9]{48}\\z"))throw new ArgumentException("Invalid local control settings");
   this.port=port;this.secret=secret;
  }
  int ReadByte() {
   while(true) {
    cancellation.ThrowIfCancellationRequested();
    if(deadline.ElapsedMilliseconds>5000)throw new TimeoutException("local_greeting_control_timeout");
    try {int value=stream.ReadByte();if(value<0)throw new IOException("local_greeting_control_closed");return value;}
    catch(IOException error) {var socket=error.InnerException as SocketException;if(socket==null||(socket.SocketErrorCode!=SocketError.TimedOut&&socket.SocketErrorCode!=SocketError.WouldBlock))throw;}
   }
  }
  Frame Read() {
   var header=new StringBuilder();int value;
   while(true){value=ReadByte();if(value!=13)header.Append((char)value);if(header.Length>16384)throw new IOException("local_control_header_too_large");if(header.Length>=2&&header[header.Length-1]=='\n'&&header[header.Length-2]=='\n')break;}
   var frame=new Frame();foreach(string line in header.ToString().Split('\n')){int colon=line.IndexOf(':');if(colon>0)frame.Headers[line.Substring(0,colon).Trim()]=line.Substring(colon+1).Trim();}
   string raw;int length=0;
   if(frame.Headers.TryGetValue("Content-Length",out raw)&&(!int.TryParse(raw,out length)||length<0||length>65536))throw new IOException("invalid_local_control_body_length");
   byte[] body=new byte[length];for(int i=0;i<length;i++)body[i]=(byte)ReadByte();
   frame.Body=Encoding.UTF8.GetString(body);return frame;
  }
  static string Header(Frame frame,string name){string value;return frame.Headers.TryGetValue(name,out value)?value:"";}
  void Send(string command){cancellation.ThrowIfCancellationRequested();byte[] data=Encoding.ASCII.GetBytes(command+"\n\n");stream.Write(data,0,data.Length);}
  string Api(string command) {
   deadline=Stopwatch.StartNew();Send("api "+command);Frame reply=Read();
   if(Header(reply,"Content-Type")!="api/response")throw new IOException("unexpected_local_control_reply");return reply.Body.Trim();
  }
  public void Open(string channel,string callId,CancellationToken cancellation) {
   if(client!=null||!Regex.IsMatch(channel??"","\\A[a-fA-F0-9]{8}(?:-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}\\z")||!Regex.IsMatch(callId??"","\\A[a-f0-9]{32}\\z"))throw new ArgumentException("Invalid local greeting dialog");
   this.channel=channel;this.cancellation=cancellation;deadline=Stopwatch.StartNew();client=new TcpClient();
   var pending=client.BeginConnect("127.0.0.1",port,null,null);
   try {while(!pending.AsyncWaitHandle.WaitOne(50)){cancellation.ThrowIfCancellationRequested();if(deadline.ElapsedMilliseconds>2000)throw new TimeoutException("local_control_connect_timeout");}client.EndConnect(pending);}
   finally{pending.AsyncWaitHandle.Close();}
   stream=client.GetStream();stream.ReadTimeout=200;stream.WriteTimeout=1000;
   if(Header(Read(),"Content-Type")!="auth/request")throw new IOException("unexpected_local_control_greeting");
   Send("auth "+secret);if(Header(Read(),"Reply-Text")!="+OK accepted")throw new IOException("local_control_authentication_rejected");
   // A response UUID alone is insufficient: bind it to our random SIP Call-ID.
   if(Api("uuid_getvar "+channel+" sip_call_id")!=callId)throw new IOException("local_greeting_dialog_mismatch");
   var watch=Stopwatch.StartNew();
   while(Api("uuid_getvar "+channel+" current_application")!="park"){cancellation.ThrowIfCancellationRequested();if(watch.ElapsedMilliseconds>2000)throw new IOException("local_greeting_channel_not_parked");Thread.Sleep(20);}
  }
  public void BeginMenu(CancellationToken cancellation,bool hardwareDigits) {
   this.cancellation=cancellation;
   if(Api("uuid_getvar "+channel+" current_application")!="park")throw new IOException("local_greeting_park_lost");
   if(!Api("uuid_transfer "+channel+" 9197 XML "+(hardwareDigits?"office_modem_keypad":"office_modem_menu")).StartsWith("+OK",StringComparison.Ordinal))throw new IOException("local_greeting_menu_transfer_failed");
   var watch=Stopwatch.StartNew();
   while(Api("uuid_getvar "+channel+" current_application")!="ivr"){cancellation.ThrowIfCancellationRequested();if(watch.ElapsedMilliseconds>2000)throw new IOException("local_greeting_menu_not_ready");Thread.Sleep(10);}
  }
  public int RelayDigits(string digits,CancellationToken cancellation) {
   if(!Regex.IsMatch(digits??"","\\A[0-9A-D*#]{1,32}\\z"))throw new ArgumentException("Invalid bounded modem keys");
   this.cancellation=cancellation;
   if(Api("uuid_getvar "+channel+" current_application")!="ivr")return 0;
   if(!Api("uuid_recv_dtmf "+channel+" "+digits).StartsWith("+OK",StringComparison.Ordinal))throw new IOException("modem_key_delivery_failed");
   return digits.Length;
  }
  public void Dispose(){if(client!=null)client.Close();stream=null;}
 }
 public sealed class ModemGreetingBridge : ITransmitGreetingEndpoint, IVoiceDigitEndpoint {
  readonly LocalSipAudioEndpoint sip;readonly IVoiceAudioEndpoint audio;readonly IGreetingCallControl control;readonly GreetingPrompt prompt;
  CancellationToken cancellation;int position,duplexSamples;bool opened,duplex,disposed;
  readonly StringBuilder pendingDigits=new StringBuilder();
  public bool BargeInRequested {get;private set;}
  public bool HardwareKeypadMode {get;private set;}
  public int BufferedDigitCount {get;private set;}
  public int RelayedDigitCount {get;private set;}
  public bool DuplexReady {get{return duplex;}}
  public bool MenuStarted {get;private set;}
  public int GreetingSamples {get;private set;}
  public AudioLevelMeter GreetingPlaybackAudio {get;private set;}
  public int PromptSamples {get{return prompt.Audio.Length;}}
  public string PromptSha256 {get{return prompt.Sha256;}}
  public SipBridgeMetrics Metrics {get{return sip==null?null:sip.Metrics;}}
  public bool NeedsDuplex {get{return !duplex&&(pendingDigits.Length>0||position>=8000+((prompt.Audio.Length+159)/160)*160+8000);}}
  public bool Ended {get{return disposed||audio.Ended;}}
  public ModemGreetingBridge(LocalSipAudioEndpoint sip,IGreetingCallControl control,string path):this(sip,control,path,sip.GreetingChannel,sip.DialogCallId){this.sip=sip;}
  // The identifiers are read after Open; the overload permits deterministic audio/control fixtures.
  readonly string fixtureChannel,fixtureCallId;
  public ModemGreetingBridge(IVoiceAudioEndpoint audio,IGreetingCallControl control,string path,string channel,string callId) {
   if(audio==null||control==null)throw new ArgumentNullException();this.audio=audio;this.control=control;prompt=new GreetingPrompt(path,30);GreetingPlaybackAudio=new AudioLevelMeter();fixtureChannel=channel;fixtureCallId=callId;
  }
  public void Open(CancellationToken cancellation) {
   if(opened||disposed)throw new InvalidOperationException("Invalid greeting bridge lifecycle");
   this.cancellation=cancellation;audio.Open(cancellation);control.Open(sip==null?fixtureChannel:sip.GreetingChannel,sip==null?fixtureCallId:sip.DialogCallId,cancellation);opened=true;
  }
  public void ReceiveDigit(char digit) {
   if(disposed||!opened||(MenuStarted&&!HardwareKeypadMode))return;
   if(!((digit>='0'&&digit<='9')||digit=='*'||digit=='#'||(digit>='A'&&digit<='D')))return;
   if(pendingDigits.Length>=32)throw new InvalidDataException("modem_key_queue_limit");
   pendingDigits.Append(digit);BufferedDigitCount++;
   if(!duplex)BargeInRequested=true;
  }
  public void ReceiveAudio(byte[] data){if(MenuStarted)audio.ReceiveAudio(data);}
  public byte[] TransmitAudio(int count) {
   if(!opened||disposed||count!=160||NeedsDuplex)throw new InvalidOperationException("Invalid greeting bridge phase");
   if(duplex){
    if(!MenuStarted&&duplexSamples>=4000){
     HardwareKeypadMode=pendingDigits.Length>0;control.BeginMenu(cancellation,HardwareKeypadMode);MenuStarted=true;
    }
    if(MenuStarted&&HardwareKeypadMode&&pendingDigits.Length>0){
     string digits=pendingDigits.ToString();pendingDigits.Clear();RelayedDigitCount+=control.RelayDigits(digits,cancellation);
    }
    duplexSamples+=count;return audio.TransmitAudio(count);
   }
   if(sip!=null)sip.ServiceParkedDialog();else audio.TransmitAudio(count);
   byte[] result=new byte[count];
   for(int i=0;i<count;i++){int sample=position++-8000;result[i]=sample>=0&&sample<prompt.Audio.Length?prompt.Audio[sample] :(byte)255;GreetingPlaybackAudio.Observe(result[i]);}
   GreetingSamples+=count;return result;
  }
  public void BeginDuplex(){if(!opened||disposed||!NeedsDuplex)throw new InvalidOperationException("Unexpected greeting transition");cancellation.ThrowIfCancellationRequested();duplex=true;}
  public void Dispose(){if(disposed)return;disposed=true;try{audio.Dispose();}finally{control.Dispose();}}
 }
}
