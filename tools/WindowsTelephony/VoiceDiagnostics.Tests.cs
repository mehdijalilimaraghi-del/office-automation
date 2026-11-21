using System;
using System.Collections.Generic;
using System.IO;
using System.Text;
using System.Threading;
using OfficeAutomation.Telephony.Diagnostics;

public sealed class DiagnosticFakePort : IVoicePort
{
    private readonly Queue<byte> input = new Queue<byte>();
    private bool configured, ringSent, duplex;
    public bool NoRing, RejectDuplex, RejectHangup, Released, Disposed, RejectFormatQuery, PartialFormatReply, ResidualAudioOnStop, RejectRingQueries;
    public string RingReply = "\r\nRING\r\n";
    public string CurrentFormat = "0,8000";
    public readonly List<string> Commands = new List<string>();
    private void Enqueue(string value) { foreach(byte b in Encoding.ASCII.GetBytes(value)) input.Enqueue(b); }
    public void Open() { }
    public int Available
    {
        get {
            if(configured && !ringSent && !NoRing && input.Count == 0) { ringSent=true; Enqueue(RingReply); }
            return input.Count;
        }
    }
    public int Read(byte[] b,int o,int n) { int count=0;while(count<n && input.Count>0)b[o+count++]=input.Dequeue();return count; }
    public void Write(byte[] b,int o,int n)
    {
        if(duplex && n==2 && b[o]==16 && b[o+1]==94) { duplex=false; if(ResidualAudioOnStop)Enqueue("residual-private-audio");Enqueue("\r\nOK\r\n");return; }
        if(duplex) {
            byte[] audio=new byte[160];for(int i=0;i<audio.Length;i++)audio[i]=VoiceCodec.EncodeMuLaw((short)(3000*Math.Sin(2*Math.PI*440*i/8000)));
            foreach(byte sample in VoiceCodec.Escape(audio))input.Enqueue(sample);return;
        }
        string command=Encoding.ASCII.GetString(b,o,n).Trim();Commands.Add(command);
        if(RejectRingQueries && (command=="AT+VEM?" || command=="AT+VDR?" || command=="AT+VLS?")){Enqueue("\r\nERROR\r\n");return;}
        switch(command) {
            case "ATI": Enqueue("\r\nLSI Test Fixture\r\nOK\r\n");break;
            case "AT+FCLASS?": Enqueue("\r\n0\r\nOK\r\n");break;
            case "AT+VSM=?": Enqueue("\r\n4,\"G.711U\",8,0,8000\r\nOK\r\n");break;
            case "AT+VSM?": Enqueue(RejectFormatQuery ? "\r\nERROR\r\n" : "AT+VSM?\r\r\n" + CurrentFormat + (PartialFormatReply ? "\r\n" : "\r\nOK\r\n"));break;
            case "AT+IFC?": Enqueue("\r\n+IFC: 2,2\r\nOK\r\n");break;
            case "AT+VSM=4,8000": configured=true; Enqueue("\r\nOK\r\n");break;
            case "AT+VTR": duplex=!RejectDuplex;Enqueue(RejectDuplex?"\r\nERROR\r\n":"\r\nCONNECT\r\n");break;
            case "ATH": Enqueue(RejectHangup?"\r\nERROR\r\n":"\r\nOK\r\n");break;
            default: Enqueue("\r\nOK\r\n");break;
        }
    }
    public void ReleaseDtr() { Released=true; }
    public void Dispose() { Disposed=true; }
}
public static class VoiceDiagnosticChecks
{
    private static void Check(bool value,string message) { if(!value)throw new Exception(message); }
    private static void Finish(VoiceSession session) { for(int i=0;i<1200 && !session.Completed;i++)Thread.Sleep(5);if(!session.Completed){session.Cancel();throw new Exception("Fixture test timed out");} }
    public static string Run()
    {
        Check(VoiceCodec.DecodeMuLaw(255)==0 && VoiceCodec.DecodeMuLaw(127)==0,"silence decoding");
        Check(VoiceCodec.DecodeMuLaw(0)==-32124 && VoiceCodec.DecodeMuLaw(128)==32124,"standard mu-law vectors");
        foreach(short value in new short[]{-30000,-10000,-1000,0,1000,10000,30000})
            Check(Math.Abs(VoiceCodec.DecodeMuLaw(VoiceCodec.EncodeMuLaw(value))-value)<1000,"codec roundtrip");
        Check(VoiceCodec.FindMuLaw("4,\"G.711U\",8,0,8000\r\nOK")==4,"actual LSI format");
        Check(VoiceCodec.FindMuLaw("131,\"8-bit mu-law\",8000\r\nOK")==131,"alternate codec ID");
        Check(VoiceCodec.FindMuLaw("0,\"Signed PCM\",16,0,8000")==-1,"unsupported serial bandwidth format");
        Check(VoiceSettings.Parse("AT+VSM?\r\r\n+VSM: 0, 8000, 7, 9\r\nOK\r\n","VSM",2,4)=="0,8000,7,9","all four settings preserved");
        Check(VoiceSettings.Parse("+vsm = 4,8000\r\nOK\r\n","VSM",2,4)=="4,8000","prefix delimiter and whitespace");
        Check(VoiceSettings.Parse("0,8000\r\n1,8000\r\nOK\r\n","VSM",2,4)=="","ambiguous current values rejected");
        Check(VoiceSettings.Parse("0,8000,7,9,2\r\nOK\r\n","VSM",2,4)=="","extra parameters never silently dropped");
        Check(VoiceSettings.Parse("0,8000 trailing\r\nOK\r\n","VSM",2,4)=="","trailing garbage rejected");
        Check(VoiceSettings.Parse("0,8000\r\nERROR\r\n","VSM",2,4)=="","rejected query cannot become a snapshot");
        Check(VoiceSettings.Parse("0,8000\r\n","VSM",2,4)=="","partial query cannot become a snapshot");
        Check(VoiceSettings.Parse("0,\"Signed PCM\",16,0,8000\r\nOK\r\n","VSM",2,4)=="","codec inventory cannot become current settings");
        Check(VoiceSettings.Parse("+IFC: 2,2\r\nOK\r\n","IFC",2,2)=="2,2","flow snapshot");
        const string actualLsiCurrent = "AT+VSM?\r\r\n0,\"Signed PCM\",16,0,8000,128,0\r\n\r\nOK\r\n";
        Check(VoiceSettings.ParseFormat(actualLsiCurrent)=="0,8000,128,0","actual owner descriptive current reply");
        Check(VoiceSettings.ParseFormat("+VSM: 4,\"G.711U\",8,0,8000,122,1\r\nOK\r\n")=="4,8000,122,1","descriptive reply not tied to model/codec");
        Check(VoiceSettings.ParseFormat("0,\"Signed PCM\",16,0,8000,(0,122-148),0\r\nOK\r\n")=="","supported range is not a current setting");
        Check(VoiceSettings.ParseFormat("0,\"Signed PCM\",16,0,8000,128\r\nOK\r\n")=="","incomplete descriptive reply rejected");
        Check(VoiceSettings.ParseFormat(actualLsiCurrent.Replace("OK", "ERROR"))=="","failed descriptive reply rejected");
        Check(VoiceSettings.ParseFormat(actualLsiCurrent.Replace("OK", "4,8000,128,0\r\nOK"))=="","mixed conflicting replies rejected");
        Check(VoiceSettings.ParseFormat("+VSM: 0,8000,128,0\r\nOK\r\n")=="0,8000,128,0","numeric format remains supported");
        var ringParser=new RingDetector();ringParser.Feed(16);Check(!ringParser.Detected,"incomplete DLE does not answer");ringParser.Feed((byte)'R');Check(ringParser.Detected && ringParser.Signal=="dle_ring","split DLE ring event");
        ringParser=new RingDetector();foreach(byte b in Encoding.ASCII.GetBytes("\r\n RING \r"))ringParser.Feed(b);Check(ringParser.Detected,"text ring whitespace and CR termination");
        foreach(string input in new[]{"SPRING\r\n","RINGBACK\r\n","2\r\n","\u0010r","\u0010\u0010R\r\n","NMBR=123456\r\n"})
        { ringParser=new RingDetector();foreach(byte b in Encoding.ASCII.GetBytes(input))ringParser.Feed(b);Check(!ringParser.Detected,"non-ring input cannot answer"); }
        var decoder=new VoiceDecoder();
        decoder.Feed(new byte[]{255,16},2);
        decoder.Feed(new byte[]{16,16,(byte)'b',127,16},5);
        decoder.Feed(new byte[]{3},1);
        Check(decoder.Audio.Count==3 && decoder.Audio[1]==16 && decoder.Ended && decoder.Events.Contains("busy_tone"),"cross-frame DLE parsing");
        byte[] escaped=VoiceCodec.Escape(new byte[]{1,16,2});Check(escaped.Length==4 && escaped[2]==16,"transmit DLE escaping");
        var result=new VoiceResult {Status="captured",DuplexStarted=true,CleanupConfirmed=true,TransmittedSamples=30000,ReceivedSamples=30000,ReceivedRms=0.1};
        Check(!result.CompatibleForNextStep,"data counters are not physical proof");
        result.RemoteToneConfirmed=true;Check(!result.CompatibleForNextStep,"both human checks required");
        result.RecordingSpeechConfirmed=true;Check(result.CompatibleForNextStep,"complete assessment");
        result.CleanupConfirmed=false;Check(!result.CompatibleForNextStep,"cleanup required");
        string folder=Path.Combine(Path.GetTempPath(),"OfficeAudioChecks-"+Guid.NewGuid().ToString("N"));Directory.CreateDirectory(folder);
        try {
            var port=new DiagnosticFakePort();var session=new VoiceSession(port,folder,300,200);session.Start();Finish(session);
            Check(session.Result.Status=="captured" && session.Result.CleanupConfirmed && session.Result.ReceivedSamples>0,"simulated duplex capture");
            Check(!session.Result.CompatibleForNextStep,"fixture never certifies real hardware");
            Check(port.Commands.Contains("AT+VSM=0,8000") && port.Commands.Contains("AT+IFC=2,2") && port.Commands.Contains("AT+FCLASS=0"),"restore original settings");
            Check(port.Disposed && !port.Commands.Exists(c=>c.StartsWith("ATD")),"no outbound dialing");
            byte[] wave=File.ReadAllBytes(session.RecordingPath);
            Check(Encoding.ASCII.GetString(wave,0,4)=="RIFF" && BitConverter.ToInt32(wave,24)==8000 && wave.Length==44+session.Result.ReceivedSamples*2,"valid PCM wave file");
            var missing=new DiagnosticFakePort {NoRing=true};session=new VoiceSession(missing,folder,100,100);session.Start();Finish(session);
            Check(session.Result.ErrorCode=="incoming_call_timeout" && !missing.Commands.Contains("AT+VLS=1") && !missing.Commands.Contains("ATH") && missing.Disposed,"no ring does not answer");
            var rejected=new DiagnosticFakePort {RejectDuplex=true};session=new VoiceSession(rejected,folder,300,100);session.Start();Finish(session);
            Check(session.Result.Status=="failed" && !session.Result.DuplexStarted && session.Result.CleanupConfirmed,"unsupported duplex safe cleanup");
            var failed=new DiagnosticFakePort {RejectHangup=true};session=new VoiceSession(failed,folder,300,100);session.Start();Finish(session);
            Check(!session.Result.CleanupConfirmed && failed.Released && !session.Result.CompatibleForNextStep,"unconfirmed hangup cannot pass");
            var cancelled=new DiagnosticFakePort {NoRing=true};session=new VoiceSession(cancelled,folder,2000,100);session.Start();
            for(int i=0;i<300 && session.Progress!="waiting_ring" && !session.Completed;i++)Thread.Sleep(5);
            Check(session.Progress=="waiting_ring","fixture reached waiting stage");
            session.Cancel();Finish(session);
            Check(session.Result.Cancelled && !cancelled.Commands.Contains("AT+VLS=1") && cancelled.Disposed,"cancellation does not answer");
            Check(session.Result.FailureStage=="wait_for_incoming_call","cancel stage retained");
            var extended=new DiagnosticFakePort {CurrentFormat="+VSM: 0, 8000, 7, 9",ResidualAudioOnStop=true};session=new VoiceSession(extended,folder,300,100);session.Start();Finish(session);
            Check(session.Result.CleanupConfirmed && extended.Commands.Contains("AT+VSM=0,8000,7,9"),"entire original format restored");
            Check(session.Result.Trace.Exists(t=>t.Command=="AT+VSM?" && t.Response.Contains("7, 9")) && session.Result.Trace.Exists(t=>t.Command=="<DLE>^" && t.Response=="OK"),"raw query and stop acknowledgement traced");
            var lsi=new DiagnosticFakePort {CurrentFormat="0,\"Signed PCM\",16,0,8000,128,0"};session=new VoiceSession(lsi,folder,300,100);session.Start();Finish(session);
            Check(session.Result.Status=="captured" && session.Result.DuplexStarted && session.Result.CleanupConfirmed,"actual LSI reply passes preparation in fixture");
            Check(session.Result.OriginalFormat=="0,8000,128,0" && lsi.Commands.Contains("AT+VSM=0,8000,128,0"),"LSI restoration preserves settable values without metadata");
            Check(!lsi.Commands.Exists(c=>c.StartsWith("AT+VSM=0,\"")),"descriptive labels are never sent as settings");
            var shielded=new DiagnosticFakePort {RingReply="\r\n\u0010R",RejectRingQueries=true};session=new VoiceSession(shielded,folder,300,100);session.Start();Finish(session);
            Check(session.Result.RingDetected && session.Result.RingSignal=="dle_ring" && session.Result.DuplexStarted && session.Result.CleanupConfirmed,"voice ring event answers in simulated session");
            Check(session.Result.RingInputHex.Contains("10-52"),"ring control bytes preserved");
            Check(session.Result.Trace.Exists(t=>t.Command=="AT+VEM?" && t.Error.Length>0),"unsupported ring query recorded without blocking");
            var noisy=new DiagnosticFakePort {RingReply=new string('X',5000)+"\r\n\u0010R"};session=new VoiceSession(noisy,folder,1000,100);session.Start();Finish(session);
            Check(session.Result.RingDetected && session.Result.RingInputTruncated && session.Result.RingInputHex.Length==4096*3-1 && session.Result.RingBytesObserved>4096,"bounded capture continues detecting after overflow");
            var unrelated=new DiagnosticFakePort {RingReply="\r\nRINGBACK\r\n\u0010r"};session=new VoiceSession(unrelated,folder,100,100);session.Start();Finish(session);
            Check(!session.Result.RingDetected && !unrelated.Commands.Contains("AT+VLS=1") && session.Result.RingBytesObserved>0 && session.Result.RingInputHex.Length>0,"non-ring timeout retains input without answering");
            var unknown=new DiagnosticFakePort {CurrentFormat="not-a-numeric-snapshot"};session=new VoiceSession(unknown,folder,300,100);session.Start();Finish(session);
            Check(session.Result.FailureStage=="read_current_format" && session.Result.CurrentFormatReply.Contains("not-a-numeric-snapshot"),"failed current-format response retained");
            Check(!unknown.Commands.Contains("AT+VSM=4,8000") && !unknown.Commands.Contains("AT+IFC=0,0") && !unknown.Commands.Contains("AT+VLS=1") && session.Result.CleanupConfirmed,"unknown settings stop before audio changes or answering");
            var rejectedQuery=new DiagnosticFakePort {RejectFormatQuery=true};session=new VoiceSession(rejectedQuery,folder,300,100);session.Start();Finish(session);
            Check(session.Result.Trace.Exists(t=>t.Command=="AT+VSM?" && t.Response.Contains("ERROR") && t.Error.Length>0),"rejected replies traced");
            var partial=new DiagnosticFakePort {PartialFormatReply=true};session=new VoiceSession(partial,folder,300,100);session.Start();Finish(session);
            Check(session.Result.FailureError=="modem_response_timeout" && session.Result.Trace.Exists(t=>t.Command=="AT+VSM?" && t.Response.Contains("0,8000") && t.Error=="modem_response_timeout"),"timeout retains partial response");
            var streaming=new DiagnosticFakePort();session=new VoiceSession(streaming,folder,300,2000);session.Start();
            for(int i=0;i<400 && session.Progress!="streaming" && !session.Completed;i++)Thread.Sleep(5);
            session.Cancel();Finish(session);
            Check(session.Result.Cancelled && session.Result.DuplexStarted && session.Result.CleanupConfirmed && streaming.Commands.Contains("ATH"),"streaming cancellation restores and hangs up");
            return "PASS: settings snapshots, raw/partial/rejected traces, codec/framing/WAV/acceptance gates and fourteen simulated session outcomes; no real port opened.";
        } finally { foreach(string file in Directory.GetFiles(folder))File.Delete(file);Directory.Delete(folder); }
    }
}
