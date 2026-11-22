using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Net;
using System.Net.Sockets;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;

public sealed class NativeSipSmokeResult {
 public int RegisteredEndpoints;
 public bool InviteAuthenticationRequired;
 public bool InternalCallAnswered;
 public int ReceivedPacketsA,ReceivedPacketsB;
 public bool HangupAcknowledged;
}
public static class NativeSipSmoke {
 sealed class Message {
  public string Raw,First,Body;
  public int Code {get{int value;var p=First.Split(' ');return p.Length>1 && p[0]=="SIP/2.0" && int.TryParse(p[1],out value)?value:0;}}
  public string Header(string name){return Headers(name).FirstOrDefault()??"";}
  public string[] Headers(string name){return Raw.Split(new[]{"\r\n"},StringSplitOptions.None).TakeWhile(l=>l.Length>0).Where(l=>l.StartsWith(name+":",StringComparison.OrdinalIgnoreCase)).Select(l=>l.Substring(name.Length+1).Trim()).ToArray();}
  public static Message Parse(byte[] data){string text=Encoding.UTF8.GetString(data);int end=text.IndexOf("\r\n\r\n",StringComparison.Ordinal);return new Message{Raw=text,First=text.Split(new[]{"\r\n"},StringSplitOptions.None)[0],Body=end<0?"":text.Substring(end+4)};}
 }
 sealed class Phone : IDisposable {
  public readonly UdpClient Signal=new UdpClient(new IPEndPoint(IPAddress.Loopback,0));
  public readonly UdpClient Media=new UdpClient(new IPEndPoint(IPAddress.Loopback,0));
  public readonly string User,Password;
  public readonly IPEndPoint Server;
  public int Port {get{return ((IPEndPoint)Signal.Client.LocalEndPoint).Port;}}
  public int MediaPort {get{return ((IPEndPoint)Media.Client.LocalEndPoint).Port;}}
  public string Address {get{return "sip:"+User+"@127.0.0.1";}}
  public string Contact {get{return "<sip:"+User+"@127.0.0.1:"+Port+">";}}
  public Phone(string user,string password,int serverPort){User=user;Password=password;Server=new IPEndPoint(IPAddress.Loopback,serverPort);Signal.Client.ReceiveTimeout=200;Media.Client.ReceiveTimeout=100;}
  public void Send(string text){byte[] b=Encoding.UTF8.GetBytes(text);Signal.Send(b,b.Length,Server);}
  public Message Wait(Func<Message,bool> predicate){var clock=Stopwatch.StartNew();while(clock.ElapsedMilliseconds<7000){try{IPEndPoint source=null;var m=Message.Parse(Signal.Receive(ref source));if(source.Address.Equals(IPAddress.Loopback) && source.Port==Server.Port && predicate(m))return m;}catch(SocketException e){if(e.SocketErrorCode!=SocketError.TimedOut)throw;}}throw new Exception("SIP response timed out");}
  public string Sdp(){return "v=0\r\no="+User+" 1 1 IN IP4 127.0.0.1\r\ns=OfficeLabTest\r\nc=IN IP4 127.0.0.1\r\nt=0 0\r\nm=audio "+MediaPort+" RTP/AVP 0 8 101\r\na=rtpmap:0 PCMU/8000\r\na=rtpmap:8 PCMA/8000\r\na=rtpmap:101 telephone-event/8000\r\na=fmtp:101 0-16\r\na=ptime:20\r\na=sendrecv\r\n";}
  public string Request(string method,string uri,string callId,int sequence,string from,string to,string extra,string body,string branch){return method+" "+uri+" SIP/2.0\r\nVia: SIP/2.0/UDP 127.0.0.1:"+Port+";branch="+branch+";rport\r\nMax-Forwards: 70\r\nFrom: "+from+"\r\nTo: "+to+"\r\nCall-ID: "+callId+"\r\nCSeq: "+sequence+" "+method+"\r\nContact: "+Contact+"\r\n"+extra+(body.Length>0?"Content-Type: application/sdp\r\n":"")+"Content-Length: "+Encoding.UTF8.GetByteCount(body)+"\r\n\r\n"+body;}
  public void Reply(Message request,int code,string phrase,string tag,string body){string to=request.Header("To");if(!to.Contains(";tag="))to+=";tag="+tag;string text="SIP/2.0 "+code+" "+phrase+"\r\n"+string.Join("",request.Headers("Via").Select(v=>"Via: "+v+"\r\n"))+"From: "+request.Header("From")+"\r\nTo: "+to+"\r\nCall-ID: "+request.Header("Call-ID")+"\r\nCSeq: "+request.Header("CSeq")+"\r\nContact: "+Contact+"\r\n"+(body.Length>0?"Content-Type: application/sdp\r\n":"")+"Content-Length: "+Encoding.UTF8.GetByteCount(body)+"\r\n\r\n"+body;Send(text);}
  public void Register(){string id=Guid.NewGuid().ToString("N"),from="<"+Address+">;tag="+id,to="<"+Address+">",uri="sip:127.0.0.1:"+Server.Port;Send(Request("REGISTER",uri,id,1,from,to,"Expires: 120\r\n","",Branch()));var challenge=Wait(m=>m.Code>=200 && m.Header("Call-ID")==id);if(challenge.Code!=401)throw new Exception("REGISTER must require authentication");string auth=Authorization(challenge,User,Password,"REGISTER",uri);Send(Request("REGISTER",uri,id,2,from,to,"Expires: 120\r\n"+auth,"",Branch()));if(Wait(m=>m.Code>=200 && m.Header("Call-ID")==id).Code!=200)throw new Exception("Authenticated REGISTER rejected");}
  public void Dispose(){Signal.Close();Media.Close();}
 }
 static string Branch(){return "z9hG4bK"+Guid.NewGuid().ToString("N");}
 static string Hash(string value){using(var md5=MD5.Create())return string.Concat(md5.ComputeHash(Encoding.UTF8.GetBytes(value)).Select(b=>b.ToString("x2")));}
 static string Parameter(string text,string name){var m=Regex.Match(text,"(?:^|[, ])"+name+"=(?:\"([^\"]*)\"|([^, ]+))",RegexOptions.IgnoreCase);return m.Success?(m.Groups[1].Success?m.Groups[1].Value:m.Groups[2].Value):"";}
 static string Authorization(Message challenge,string user,string password,string method,string uri){string header=challenge.Header("WWW-Authenticate"),kind="Authorization";if(header.Length==0){header=challenge.Header("Proxy-Authenticate");kind="Proxy-Authorization";}string realm=Parameter(header,"realm"),nonce=Parameter(header,"nonce"),qop=Parameter(header,"qop");if(nonce.Length==0)throw new Exception("Missing digest nonce");string cnonce=Guid.NewGuid().ToString("N"),ha1=Hash(user+":"+realm+":"+password),ha2=Hash(method+":"+uri),response=qop.Contains("auth")?Hash(ha1+":"+nonce+":00000001:"+cnonce+":auth:"+ha2):Hash(ha1+":"+nonce+":"+ha2);return kind+": Digest username=\""+user+"\", realm=\""+realm+"\", nonce=\""+nonce+"\", uri=\""+uri+"\", response=\""+response+"\", algorithm=MD5"+(qop.Contains("auth")?", qop=auth, nc=00000001, cnonce=\""+cnonce+"\"":"")+"\r\n";}
 static IPEndPoint MediaTarget(string sdp){if(!Regex.IsMatch(sdp,@"(?m)^c=IN IP4 127\.0\.0\.1\r?$"))throw new Exception("Non-loopback RTP rejected by test");var match=Regex.Match(sdp,@"(?m)^m=audio ([0-9]+) RTP/AVP");if(!match.Success)throw new Exception("No RTP offer");int port=int.Parse(match.Groups[1].Value);if(port<17000||port>17199)throw new Exception("Unexpected RTP port");return new IPEndPoint(IPAddress.Loopback,port);}
 static byte MuLaw(short value){int sample=value,sign=0;if(sample<0){sign=128;sample=-sample;}sample=Math.Min(sample,32635)+132;int exp=7;for(int mask=0x4000;exp>0 && (sample&mask)==0;exp--,mask>>=1){}return (byte)(~(sign|(exp<<4)|((sample>>(exp+3))&15)));}
 static void SendRtp(Phone phone,IPEndPoint target,int frame,int tone){var packet=new byte[172];packet[0]=0x80;packet[1]=0;packet[2]=(byte)(frame>>8);packet[3]=(byte)frame;uint timestamp=(uint)(frame*160);for(int i=0;i<4;i++)packet[4+i]=(byte)(timestamp>>(24-i*8));packet[8]=1;packet[11]=(byte)tone;for(int i=0;i<160;i++)packet[12+i]=MuLaw((short)(3000*Math.Sin(2*Math.PI*tone*(frame*160+i)/8000.0)));phone.Media.Send(packet,packet.Length,target);}
 static int Drain(Phone phone){int count=0;for(int i=0;i<128 && phone.Media.Available>0;i++){IPEndPoint source=null;var packet=phone.Media.Receive(ref source);if(source.Address.Equals(IPAddress.Loopback) && packet.Length>=172 && (packet[0]>>6)==2 && (packet[1]&127)==0)count++;}return count;}
 static string ContactUri(Message message){var match=Regex.Match(message.Header("Contact"),"<([^>]+)>");return match.Success?match.Groups[1].Value:message.Header("Contact").Split(';')[0];}
 public static NativeSipSmokeResult Run(int sipPort,string userA,string passwordA,string userB,string passwordB){
  var result=new NativeSipSmokeResult();using(var a=new Phone(userA,passwordA,sipPort))using(var b=new Phone(userB,passwordB,sipPort)){
   a.Register();b.Register();result.RegisteredEndpoints=2;
   string id=Guid.NewGuid().ToString("N"),from="<"+a.Address+">;tag="+id,to="<"+b.Address+">",uri="sip:"+userB+"@127.0.0.1:"+sipPort,branch=Branch();int sequence=1;
   a.Send(a.Request("INVITE",uri,id,sequence,from,to,"",a.Sdp(),branch));var challenge=a.Wait(m=>m.Code>=200 && m.Header("Call-ID")==id);if(challenge.Code!=401 && challenge.Code!=407)throw new Exception("INVITE must require authentication");result.InviteAuthenticationRequired=true;
   a.Send(a.Request("ACK",uri,id,sequence,from,challenge.Header("To"),"","",branch));sequence++;
   a.Send(a.Request("INVITE",uri,id,sequence,from,to,Authorization(challenge,userA,passwordA,"INVITE",uri),a.Sdp(),Branch()));
   var incoming=b.Wait(m=>m.First.StartsWith("INVITE "));string tag=Guid.NewGuid().ToString("N");b.Reply(incoming,180,"Ringing",tag,"");b.Reply(incoming,200,"OK",tag,b.Sdp());
   var answer=a.Wait(m=>m.Code>=200 && m.Header("Call-ID")==id);if(answer.Code!=200)throw new Exception("Internal call was not answered: "+answer.Code);result.InternalCallAnswered=true;
   string contact=ContactUri(answer);a.Send(a.Request("ACK",contact,id,sequence,from,answer.Header("To"),"","",Branch()));
   var targetA=MediaTarget(answer.Body);var targetB=MediaTarget(incoming.Body);var timer=Stopwatch.StartNew();int frame=0;
   while(timer.ElapsedMilliseconds<1800){if(timer.ElapsedMilliseconds>=frame*20){SendRtp(a,targetA,frame,440);SendRtp(b,targetB,frame,660);frame++;}result.ReceivedPacketsA+=Drain(a);result.ReceivedPacketsB+=Drain(b);Thread.Sleep(2);}
   if(result.ReceivedPacketsA<10 || result.ReceivedPacketsB<10)throw new Exception("Two-way RTP did not pass");
   a.Send(a.Request("BYE",contact,id,sequence+1,from,answer.Header("To"),"","",Branch()));var bye=b.Wait(m=>m.First.StartsWith("BYE "));b.Reply(bye,200,"OK",tag,"");result.HangupAcknowledged=a.Wait(m=>m.Code>=200 && m.Header("Call-ID")==id).Code==200;
   if(!result.HangupAcknowledged)throw new Exception("BYE not acknowledged");return result;
  }
 }
 public static string RunIvr(int sipPort,string userA,string passwordA,string userB,string passwordB){
  using(var a=new Phone(userA,passwordA,sipPort))using(var b=new Phone(userB,passwordB,sipPort)){
   a.Register();b.Register();string id=Guid.NewGuid().ToString("N"),from="<"+a.Address+">;tag="+id,to="<sip:9197@127.0.0.1>",uri="sip:9197@127.0.0.1:"+sipPort,branch=Branch();
   a.Send(a.Request("INVITE",uri,id,1,from,to,"",a.Sdp(),branch));var challenge=a.Wait(m=>m.Code>=200 && m.Header("Call-ID")==id);if(challenge.Code!=401 && challenge.Code!=407)throw new Exception("IVR requires authentication");
   a.Send(a.Request("ACK",uri,id,1,from,challenge.Header("To"),"","",branch));a.Send(a.Request("INVITE",uri,id,2,from,to,Authorization(challenge,userA,passwordA,"INVITE",uri),a.Sdp(),Branch()));
   var answer=a.Wait(m=>m.Code>=200 && m.Header("Call-ID")==id);if(answer.Code!=200)throw new Exception("IVR did not answer");a.Send(a.Request("ACK",ContactUri(answer),id,2,from,answer.Header("To"),"","",Branch()));
   var target=MediaTarget(answer.Body);int received=0;for(int f=0;f<60;f++){SendRtp(a,target,f,440);Thread.Sleep(20);received+=Drain(a);}
   for(int n=0;n<8;n++){
    var p=new byte[16];p[0]=0x80;p[1]=(byte)(n==0?229:101);p[2]=1;p[3]=(byte)n;p[5]=1;p[8]=1;p[11]=42;p[12]=1;p[13]=(byte)(n>=5?138:10);int duration=Math.Min(800,(n+1)*160);p[14]=(byte)(duration>>8);p[15]=(byte)duration;a.Media.Send(p,p.Length,target);Thread.Sleep(20);
   }
   var incoming=b.Wait(m=>m.First.StartsWith("INVITE "));b.Reply(incoming,486,"Busy Here",Guid.NewGuid().ToString("N"),"");
   a.Send(a.Request("BYE",ContactUri(answer),id,3,from,answer.Header("To"),"","",Branch()));
   var ended=a.Wait(m=>(m.Code>=200 || m.First.StartsWith("BYE ")) && m.Header("Call-ID")==id);
   if(ended.First.StartsWith("BYE "))a.Reply(ended,200,"OK",id,"");
   if(received<5)throw new Exception("No IVR greeting RTP received");
   return "PASS: authenticated IVR answered, greeting RTP received ("+received+" packets), RFC2833 key 1 routed to "+userB;
  }
 } public static bool ReceiveGateway(int sipPort,string user,string password){
  using(var b=new Phone(user,password,sipPort)){
   b.Register();var incoming=b.Wait(m=>m.First.StartsWith("INVITE "));string tag=Guid.NewGuid().ToString("N");b.Reply(incoming,180,"Ringing",tag,"");b.Reply(incoming,200,"OK",tag,b.Sdp());
   var ack=b.Wait(m=>m.First.StartsWith("ACK "));var target=MediaTarget(incoming.Body.Length>0?incoming.Body:ack.Body);int received=0;for(int n=0;n<75;n++){SendRtp(b,target,n,220);Thread.Sleep(20);received+=Drain(b);}
   string from=incoming.Header("To");if(!from.Contains(";tag="))from+=";tag="+tag;
   b.Send(b.Request("BYE",ContactUri(incoming),incoming.Header("Call-ID"),1,from,incoming.Header("From"),"","",Branch()));
   return received>=10 && b.Wait(m=>m.Code>=200 && m.Header("Call-ID")==incoming.Header("Call-ID")).Code==200;
  }
 }}