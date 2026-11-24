using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using OfficeAutomation.Telephony.Diagnostics;
using OfficeAutomation.Telephony.Bridge;

public sealed class BridgeFakeModem : IVoicePort, IVoiceFlowControlPort
{
    private readonly Queue<byte> buffer = new Queue<byte>();
    private readonly Stopwatch clock = new Stopwatch();
    private VoiceDecoder playback = new VoiceDecoder();
    private bool pcm16Mode;
    private bool configured, ringSent, duplex, transmitOnly;
    private readonly VoiceDecoder greetingPlayback = new VoiceDecoder();
    public byte[] GreetingPlayed { get { return greetingPlayback.Audio.ToArray(); } }
    private int frames,greetingFrames;public bool BargeIn;
    public bool Disposed, Released;
    public readonly List<string> Commands = new List<string>();
    public int PlayedSamples { get { return playback.Audio.Count; } }
    public AudioLevelMeter PlayedLevels { get { var meter = new AudioLevelMeter(); foreach(byte value in playback.Audio) meter.Observe(value); return meter; } }
    private void Enqueue(string value) { foreach (byte b in Encoding.ASCII.GetBytes(value)) buffer.Enqueue(b); }
    public string HostFlow;
    public void SetFlowControl(string setting) { HostFlow=setting; }
    public void Open() { }
    private void Generate()
    {
        if (configured && !ringSent && buffer.Count == 0) { ringSent = true; buffer.Enqueue(16); buffer.Enqueue(82); }
        if (!duplex || transmitOnly) return;
        int ready = (int)(clock.ElapsedMilliseconds / 6);
        while (frames < ready)
        {
            var block = new byte[60];
            for (int i = 0; i < 48; i++)
            {
                int sample = frames * 48 + i; double time = sample / 8000.0;
                double value = time >= 3 && time < 3.15 ? 5000 * (Math.Sin(2*Math.PI*697*time) + Math.Sin(2*Math.PI*1209*time)) : time < 1 ? 2000*Math.Sin(2*Math.PI*440*time) : 0;
                block[i] = VoiceCodec.EncodeMuLaw((short)value);
            }
            for (int i = 48; i < 60; i++) block[i] = block[i-10];
            foreach (byte b in VoiceCodec.Escape(pcm16Mode ? VoiceCodec.Pcm16FromMuLaw(block) : block)) buffer.Enqueue(b);
            frames++;
        }
    }
    public int Available { get { Generate(); return buffer.Count; } }
    public int Read(byte[] data, int offset, int count) { Generate(); int n = Math.Min(count, buffer.Count); for (int i = 0; i < n; i++) data[offset+i] = buffer.Dequeue(); return n; }
    public void Write(byte[] data, int offset, int count)
    {
        if (count == 2 && data[offset] == 16 && (data[offset+1] == 94 || data[offset+1] == 3)) { duplex = false; Enqueue("\r\nOK\r\n"); return; }
        if (duplex) { var copy = new byte[count]; Array.Copy(data, offset, copy, 0, count); if(transmitOnly){greetingPlayback.Feed(copy,count);if(BargeIn&&++greetingFrames==65)Enqueue(string.Concat((char)16,"/",(char)16,"2",(char)16,"2",(char)16,"~"));}else playback.Feed(copy, count); return; }
        string command = Encoding.ASCII.GetString(data, offset, count).Trim(); Commands.Add(command);
        switch (command)
        {
            case "ATI": Enqueue("\r\nSynthetic bridge modem\r\nOK\r\n"); break;
            case "AT+FCLASS?": Enqueue("\r\n0\r\nOK\r\n"); break;
            case "AT+VSM=?": Enqueue("\r\n0,\"Signed PCM\",16,0,8000,(0,122-148),0\r\n4,\"G.711U\",8,0,8000\r\nOK\r\n"); break;
            case "AT+VSM?": Enqueue("\r\n0,8000,128,0\r\nOK\r\n"); break;
            case "AT+IFC?": Enqueue("\r\n+IFC:2,2\r\nOK\r\n"); break;
            case "AT+VSM=0,8000": if(!configured){pcm16Mode=true;playback=new VoiceDecoder(false,true);} configured=true;Enqueue("\r\nOK\r\n");break;
            case "AT+VSM=4,8000": configured = true; Enqueue("\r\nOK\r\n"); break;
            case "AT+VTX": Enqueue("\r\nCONNECT\r\n");duplex=true;transmitOnly=true;break;
            case "AT+VTR": transmitOnly=false;Enqueue("\r\nCONNECT\r\n"); duplex = true; clock.Start(); break;
            default: Enqueue("\r\nOK\r\n"); break;
        }
    }
    public void ReleaseDtr() { Released = true; }
    public void Dispose() { Disposed = true; }
}
public static class ModemBridgeIntegration
{
    public static object Run(int port, string gatewayPassword, string phonePassword) { return Run(port, gatewayPassword, phonePassword, false); }
    public static object Run(int port, string gatewayPassword, string phonePassword, bool pcm16){return Run(port,gatewayPassword,phonePassword,pcm16,0,null,null);}
    public static object Run(int port,string gatewayPassword,string phonePassword,bool pcm16,int eslPort,string eslSecret,string promptPath){return Run(port,gatewayPassword,phonePassword,pcm16,eslPort,eslSecret,promptPath,false);}
    public static object Run(int port,string gatewayPassword,string phonePassword,bool pcm16,int eslPort,string eslSecret,string promptPath,bool bargeIn) { return Run(port,gatewayPassword,phonePassword,pcm16,eslPort,eslSecret,promptPath,bargeIn,null); }
    public static object Run(int port,string gatewayPassword,string phonePassword,bool pcm16,int eslPort,string eslSecret,string promptPath,bool bargeIn,IVoiceDuplexProcessor processor)
    {
        bool localGreeting=!String.IsNullOrEmpty(promptPath);
        string wrongPassword=(gatewayPassword[0]=='a'?"b":"a")+gatewayPassword.Substring(1);
        var rejectedModem=new BridgeFakeModem();
        using(var rejectedEndpoint=new LocalSipAudioEndpoint("127.0.0.1",port,"office_modem",wrongPassword,"9197",localGreeting)){
            var rejectedSession=new VoiceSession(rejectedModem,Path.GetTempPath(),3000,1000,rejectedEndpoint);rejectedSession.Start();var deadline=Stopwatch.StartNew();
            while(!rejectedSession.Completed && deadline.ElapsedMilliseconds<15000)Thread.Sleep(10);
            if(!rejectedSession.Completed){rejectedSession.Cancel();throw new Exception("Authentication rejection test timed out");}
            var rejected=rejectedSession.Result;
            if(rejected.Answered || rejected.BridgeStarted || !rejected.CleanupConfirmed || rejected.FailureStage!="connect_pbx" || !rejected.ErrorCode.StartsWith("PBX rejected gateway call:") || rejectedModem.Commands.Contains("AT+VLS=1"))throw new Exception("Invalid gateway credentials answered the line or skipped restoration");
        }
        Console.WriteLine("PASS: real PBX rejected invalid gateway credentials before modem answer; settings restored.");
        var recipient = Task.Factory.StartNew(() => NativeSipSmoke.ReceiveGateway(port, "1002", phonePassword,localGreeting?15000:7000));
        Thread.Sleep(100);
        var modem = new BridgeFakeModem{BargeIn=bargeIn};
        var endpoint = new LocalSipAudioEndpoint("127.0.0.1", port, "office_modem", gatewayPassword, "9197",localGreeting);
        ModemGreetingBridge greeting=null;
        if(localGreeting)greeting=new ModemGreetingBridge(endpoint,new GreetingCallControl(eslPort,eslSecret),promptPath);
        var session = new VoiceSession(modem, Path.GetTempPath(), 3000, localGreeting?20000:10000, (IVoiceAudioEndpoint)greeting??endpoint, null, pcm16,processor);
        session.Start();
        var watch = Stopwatch.StartNew();
        while (!session.Completed && watch.ElapsedMilliseconds < 30000) Thread.Sleep(10);
        if (!session.Completed) { session.Cancel(); throw new Exception("Bridge test timeout"); }
        var result = session.Result;
        Console.WriteLine("Bridge result: " + result.Status + "/" + result.FailureStage + "/" + result.ErrorCode + "/" + result.EndReason + "; RTP " + endpoint.Metrics.SentPackets + "/" + endpoint.Metrics.ReceivedPackets + "; rejected " + endpoint.Metrics.RejectedPackets + "; raw/decoded " + result.RawReceivedSamples + "/" + result.ReceivedSamples + "; framing " + result.ReceiveFraming + "; silence " + endpoint.Metrics.SilenceSamples);
        try { if (!recipient.Wait(10000)) throw new Exception("Recipient did not finish"); } catch (AggregateException error) { throw new Exception("Recipient: " + error.Flatten().InnerExceptions[0].Message); }
        if (!recipient.Result || !result.BridgeStarted || !result.CleanupConfirmed || result.Status != "bridged"
            || result.ReceiveFraming != "repeat_60_48_lag10" || result.RemovedRepeatedSamples < 1000
            || result.EndReason != "pbx_hangup" || !endpoint.Metrics.RemoteHangup || modem.PlayedSamples < 8000
            || endpoint.Metrics.ReceivedPackets < 50 || endpoint.Metrics.SentPackets < 50 || session.RecordingPath != null)
            throw new Exception("Bridge acceptance failed: " + result.Status + "/" + result.FailureStage + "/" + result.ErrorCode + "/" + result.EndReason);
        if(result.ModemTransmitBytes != result.TransmittedSamples*(pcm16 ? 2 : 1) || (pcm16 && result.ModemCodec!=0))throw new Exception("Modem codec or wire length mismatch");
        if(localGreeting){
            var source=new GreetingPrompt(promptPath,30);var expected=Enumerable.Repeat((byte)255,8000).Concat(source.Audio).Concat(Enumerable.Repeat((byte)255,8000+((160-source.Audio.Length%160)%160))).ToArray();
            if(!modem.GreetingPlayed.SequenceEqual(bargeIn?expected.Take(modem.GreetingPlayed.Length):expected)||!greeting.MenuStarted||!result.TransmitOnlyStarted||!result.SwitchedToDuplex)throw new Exception("Native initial greeting or handoff mismatch");
            if(bargeIn&&(!greeting.BargeInRequested||greeting.GreetingSamples>=expected.Length||greeting.RelayedDigitCount!=1||!greeting.HardwareKeypadMode))throw new Exception("Barge-in was lost, duplicated or failed to interrupt");
            Console.WriteLine("PASS: VTX greeting exact serial bytes, authenticated parked dialog, VTR handoff, no initial PBX replay, DTMF routing and two-way call.");
        }
        if(endpoint.Metrics.Playout.TimestampResets!=0)throw new Exception("PBX output timestamps are discontinuous");
        var measured = modem.PlayedLevels;
        if(measured.Samples != endpoint.Metrics.ModemPlaybackAudio.Samples || measured.Peak != endpoint.Metrics.ModemPlaybackAudio.Peak
            || !measured.RmsDbfs.HasValue || Math.Abs(measured.RmsDbfs.Value-endpoint.Metrics.ModemPlaybackAudio.RmsDbfs.Value)>0.000001
            || !endpoint.Metrics.PbxReceivedAudio.ActiveRmsDbfs.HasValue)
            throw new Exception("Playback telemetry mismatch: serial="+measured.Samples+"/"+measured.Peak+"/"+measured.RmsDbfs+" meter="+endpoint.Metrics.ModemPlaybackAudio.Samples+"/"+endpoint.Metrics.ModemPlaybackAudio.Peak+"/"+endpoint.Metrics.ModemPlaybackAudio.RmsDbfs+" sourceActive="+endpoint.Metrics.PbxReceivedAudio.ActiveSamples+" late="+endpoint.Metrics.Playout.LatePackets+" duplicate="+endpoint.Metrics.Playout.DuplicatePackets+" changes="+endpoint.Metrics.Playout.SourceChanges+" media="+endpoint.Metrics.Playout.MediaResets+" reset="+endpoint.Metrics.Playout.TimestampResets);
        return new { passed = true, serial_port_opened = false, result.ModemSampleFormat, result.ModemCodec, result.ModemTransmitBytes, result.BridgeStarted, result.CleanupConfirmed, result.EndReason,
            result.RawReceivedSamples, result.ReceivedSamples, result.RemovedRepeatedSamples, result.ReceiveFraming,
            sent_rtp = endpoint.Metrics.SentPackets, received_rtp = endpoint.Metrics.ReceivedPackets,
            local_greeting=localGreeting, barge_in=bargeIn, relayed_keys=greeting==null?0:greeting.RelayedDigitCount, greeting_samples=modem.GreetingPlayed.Length,
            playout = new {endpoint.Metrics.Playout.AcceptedPackets,endpoint.Metrics.Playout.LatePackets,endpoint.Metrics.Playout.DuplicatePackets,endpoint.Metrics.Playout.SourceChanges,endpoint.Metrics.Playout.MediaResets,endpoint.Metrics.Playout.TimestampResets,endpoint.Metrics.Playout.ConcealedSamples,endpoint.Metrics.Playout.RebufferEvents},
            source_level = endpoint.Metrics.PbxReceivedAudio, playback_level = endpoint.Metrics.ModemPlaybackAudio, playback_fill = endpoint.Metrics.ModemPlaybackSilenceFillSamples,
            physical_dtmf_simulated = true, private_credentials_exported = false, audio_recorded = false };
    }
}
