using System;
using System.Diagnostics;
using System.Linq;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using OfficeAutomation.Telephony.Bridge;

public static class LocalSipProtocolChecks
{
    private static string Header(string text,string name) { return text.Split(new[]{"\r\n"},StringSplitOptions.None).First(l=>l.StartsWith(name+":",StringComparison.OrdinalIgnoreCase)).Substring(name.Length+1).Trim(); }
    private static void Reply(UdpClient server,IPEndPoint peer,string request,int code,string extra,string body)
    {
        string text="SIP/2.0 "+code+(code==401?" Unauthorized":" OK")+"\r\nVia: "+Header(request,"Via")+"\r\nFrom: "+Header(request,"From")+"\r\nTo: "+Header(request,"To")+(Header(request,"To").Contains(";tag=")?"":";tag=test")+
            "\r\nCall-ID: "+Header(request,"Call-ID")+"\r\nCSeq: "+Header(request,"CSeq")+"\r\n"+extra+(body.Length>0?"Content-Type: application/sdp\r\n":"")+"Content-Length: "+Encoding.UTF8.GetByteCount(body)+"\r\n\r\n"+body;
        byte[] bytes=Encoding.UTF8.GetBytes(text);server.Send(bytes,bytes.Length,peer);
    }
    public static string Run()
    {
        var meter = new AudioLevelMeter();
        if (meter.RmsDbfs.HasValue || meter.Samples != 0) throw new Exception("Empty audio must not report a level");
        for(int i=0;i<8000;i++) meter.Observe(255);
        if (meter.RmsDbfs.HasValue || meter.Peak != 0 || meter.ActiveSamples != 0) throw new Exception("Silence meter is incorrect");
        meter = new AudioLevelMeter();
        byte known = OfficeAutomation.Telephony.Diagnostics.VoiceCodec.EncodeMuLaw(4096);
        int decoded = OfficeAutomation.Telephony.Diagnostics.VoiceCodec.DecodeMuLaw(known);
        for(int i=0;i<8000;i++) meter.Observe(known);
        double expected = 20*Math.Log10(Math.Abs(decoded)/32768.0);
        if(meter.Samples!=8000 || meter.ActiveSamples!=8000 || meter.Peak!=Math.Abs(decoded) || Math.Abs(meter.RmsDbfs.Value-expected)>0.00001 || Math.Abs(meter.ActiveRmsDbfs.Value-expected)>0.00001) throw new Exception("Known PCMU level is incorrect");
        for(int i=0;i<8000;i++) meter.Observe(255);
        if(Math.Abs(meter.RmsDbfs.Value-(expected-10*Math.Log10(2)))>0.00001 || Math.Abs(meter.ActiveRmsDbfs.Value-expected)>0.00001) throw new Exception("Silence fill must change total RMS but preserve active RMS");
        foreach(bool badContact in new[]{false,true})
        {
            using(var server=new UdpClient(new IPEndPoint(IPAddress.Loopback,0)))
            {
                server.Client.ReceiveTimeout=200;int port=((IPEndPoint)server.Client.LocalEndPoint).Port;bool bye=false;
                var worker=Task.Factory.StartNew(()=>{
                    var clock=Stopwatch.StartNew();
                    while(clock.ElapsedMilliseconds<5000)
                    {
                        IPEndPoint peer=null;string text;
                        try{text=Encoding.UTF8.GetString(server.Receive(ref peer));}catch(SocketException error){if(error.SocketErrorCode==SocketError.TimedOut)continue;throw;}
                        if(!peer.Address.Equals(IPAddress.Loopback))throw new Exception("Nonlocal test peer");
                        if(text.StartsWith("INVITE ") && Header(text,"CSeq")=="1 INVITE")Reply(server,peer,text,401,"WWW-Authenticate: Digest realm=\"127.0.0.1\", nonce=\"unit-nonce\", algorithm=MD5, qop=\"auth\"\r\n","");
                        else if(text.StartsWith("INVITE ") && Header(text,"CSeq")=="2 INVITE")
                        {
                            string target=badContact?"127.0.0.1":"8.8.8.8";string contact=badContact?"8.8.8.8":"127.0.0.1";
                            string sdp="v=0\r\no=test 1 1 IN IP4 "+target+"\r\ns=test\r\nc=IN IP4 "+target+"\r\nt=0 0\r\nm=audio 17000 RTP/AVP 0\r\na=rtpmap:0 PCMU/8000\r\n";
                            Reply(server,peer,text,200,"Contact: <sip:test@"+contact+":"+port+">\r\n",sdp);
                        }
                        else if(text.StartsWith("BYE "))
                        {
                            if(text.Split(' ')[1].Contains("8.8.8.8"))throw new Exception("Unsafe contact URI reused during cleanup");
                            Reply(server,peer,text,200,"","");bye=true;return;
                        }
                    }
                    throw new Exception("Test dialog did not close");
                });
                var endpoint=new LocalSipAudioEndpoint("127.0.0.1",port,"office_modem",new string('a',48),"1002");bool rejected=false;
                try{endpoint.Open(CancellationToken.None);}catch(InvalidOperationException error){rejected=error.Message.Contains(badContact?"contact":"media");}finally{endpoint.Dispose();}
                if(!worker.Wait(6000) || !rejected || !bye || !endpoint.Metrics.LocalHangupAcknowledged)throw new Exception("Invalid SIP target was not rejected and cleaned up");
            }
        }
        using(var server=new UdpClient(new IPEndPoint(IPAddress.Loopback,0)))using(var cancel=new CancellationTokenSource()){
            int port=((IPEndPoint)server.Client.LocalEndPoint).Port;cancel.Cancel();bool rejected=false;
            using(var endpoint=new LocalSipAudioEndpoint("127.0.0.1",port,"office_modem",new string('a',48),"1002")){try{endpoint.Open(cancel.Token);}catch(OperationCanceledException){rejected=true;}}
            if(!rejected || server.Available!=0)throw new Exception("Cancelled connection emitted SIP traffic");
        }
        return "PASS: off-host SDP/contact rejected, ACK/BYE cleanup remains local and acknowledged; no physical modem.";
    }
}
