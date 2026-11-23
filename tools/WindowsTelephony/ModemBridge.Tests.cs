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

public sealed class BridgeFakeModem : IVoicePort
{
    private readonly Queue<byte> buffer = new Queue<byte>();
    private readonly Stopwatch clock = new Stopwatch();
    private readonly VoiceDecoder playback = new VoiceDecoder();
    private bool configured, ringSent, duplex;
    private int frames;
    public bool Disposed, Released;
    public readonly List<string> Commands = new List<string>();
    public int PlayedSamples { get { return playback.Audio.Count; } }
    public AudioLevelMeter PlayedLevels { get { var meter = new AudioLevelMeter(); foreach(byte value in playback.Audio) meter.Observe(value); return meter; } }
    private void Enqueue(string value) { foreach (byte b in Encoding.ASCII.GetBytes(value)) buffer.Enqueue(b); }
    public void Open() { }
    private void Generate()
    {
        if (configured && !ringSent && buffer.Count == 0) { ringSent = true; buffer.Enqueue(16); buffer.Enqueue(82); }
        if (!duplex) return;
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
            foreach (byte b in VoiceCodec.Escape(block)) buffer.Enqueue(b);
            frames++;
        }
    }
    public int Available { get { Generate(); return buffer.Count; } }
    public int Read(byte[] data, int offset, int count) { Generate(); int n = Math.Min(count, buffer.Count); for (int i = 0; i < n; i++) data[offset+i] = buffer.Dequeue(); return n; }
    public void Write(byte[] data, int offset, int count)
    {
        if (count == 2 && data[offset] == 16 && data[offset+1] == 94) { duplex = false; Enqueue("\r\nOK\r\n"); return; }
        if (duplex) { var copy = new byte[count]; Array.Copy(data, offset, copy, 0, count); playback.Feed(copy, count); return; }
        string command = Encoding.ASCII.GetString(data, offset, count).Trim(); Commands.Add(command);
        switch (command)
        {
            case "ATI": Enqueue("\r\nSynthetic bridge modem\r\nOK\r\n"); break;
            case "AT+FCLASS?": Enqueue("\r\n0\r\nOK\r\n"); break;
            case "AT+VSM=?": Enqueue("\r\n4,\"G.711U\",8,0,8000\r\nOK\r\n"); break;
            case "AT+VSM?": Enqueue("\r\n0,8000,128,0\r\nOK\r\n"); break;
            case "AT+IFC?": Enqueue("\r\n+IFC:2,2\r\nOK\r\n"); break;
            case "AT+VSM=4,8000": configured = true; Enqueue("\r\nOK\r\n"); break;
            case "AT+VTR": Enqueue("\r\nCONNECT\r\n"); duplex = true; clock.Start(); break;
            default: Enqueue("\r\nOK\r\n"); break;
        }
    }
    public void ReleaseDtr() { Released = true; }
    public void Dispose() { Disposed = true; }
}
public static class ModemBridgeIntegration
{
    public static object Run(int port, string gatewayPassword, string phonePassword)
    {
        string wrongPassword=(gatewayPassword[0]=='a'?"b":"a")+gatewayPassword.Substring(1);
        var rejectedModem=new BridgeFakeModem();
        using(var rejectedEndpoint=new LocalSipAudioEndpoint("127.0.0.1",port,"office_modem",wrongPassword,"9197")){
            var rejectedSession=new VoiceSession(rejectedModem,Path.GetTempPath(),3000,1000,rejectedEndpoint);rejectedSession.Start();var deadline=Stopwatch.StartNew();
            while(!rejectedSession.Completed && deadline.ElapsedMilliseconds<15000)Thread.Sleep(10);
            if(!rejectedSession.Completed){rejectedSession.Cancel();throw new Exception("Authentication rejection test timed out");}
            var rejected=rejectedSession.Result;
            if(rejected.Answered || rejected.BridgeStarted || !rejected.CleanupConfirmed || rejected.FailureStage!="connect_pbx" || !rejected.ErrorCode.StartsWith("PBX rejected gateway call:") || rejectedModem.Commands.Contains("AT+VLS=1"))throw new Exception("Invalid gateway credentials answered the line or skipped restoration");
        }
        Console.WriteLine("PASS: real PBX rejected invalid gateway credentials before modem answer; settings restored.");
        var recipient = Task.Factory.StartNew(() => NativeSipSmoke.ReceiveGateway(port, "1002", phonePassword));
        Thread.Sleep(100);
        var modem = new BridgeFakeModem();
        var endpoint = new LocalSipAudioEndpoint("127.0.0.1", port, "office_modem", gatewayPassword, "9197");
        var session = new VoiceSession(modem, Path.GetTempPath(), 3000, 10000, endpoint);
        session.Start();
        var watch = Stopwatch.StartNew();
        while (!session.Completed && watch.ElapsedMilliseconds < 20000) Thread.Sleep(10);
        if (!session.Completed) { session.Cancel(); throw new Exception("Bridge test timeout"); }
        var result = session.Result;
        Console.WriteLine("Bridge result: " + result.Status + "/" + result.FailureStage + "/" + result.ErrorCode + "/" + result.EndReason + "; RTP " + endpoint.Metrics.SentPackets + "/" + endpoint.Metrics.ReceivedPackets + "; rejected " + endpoint.Metrics.RejectedPackets + "; raw/decoded " + result.RawReceivedSamples + "/" + result.ReceivedSamples + "; framing " + result.ReceiveFraming + "; silence " + endpoint.Metrics.SilenceSamples);
        try { if (!recipient.Wait(10000)) throw new Exception("Recipient did not finish"); } catch (AggregateException error) { throw new Exception("Recipient: " + error.Flatten().InnerExceptions[0].Message); }
        if (!recipient.Result || !result.BridgeStarted || !result.CleanupConfirmed || result.Status != "bridged"
            || result.ReceiveFraming != "repeat_60_48_lag10" || result.RemovedRepeatedSamples < 1000
            || result.EndReason != "pbx_hangup" || !endpoint.Metrics.RemoteHangup || modem.PlayedSamples < 8000
            || endpoint.Metrics.ReceivedPackets < 50 || endpoint.Metrics.SentPackets < 50 || session.RecordingPath != null)
            throw new Exception("Bridge acceptance failed: " + result.Status + "/" + result.FailureStage + "/" + result.ErrorCode + "/" + result.EndReason);
        var measured = modem.PlayedLevels;
        if(measured.Samples != endpoint.Metrics.ModemPlaybackAudio.Samples || measured.Peak != endpoint.Metrics.ModemPlaybackAudio.Peak
            || !measured.RmsDbfs.HasValue || Math.Abs(measured.RmsDbfs.Value-endpoint.Metrics.ModemPlaybackAudio.RmsDbfs.Value)>0.000001
            || !endpoint.Metrics.PbxReceivedAudio.ActiveRmsDbfs.HasValue)
            throw new Exception("Playback telemetry does not match actual decoded serial audio");
        return new { passed = true, serial_port_opened = false, result.BridgeStarted, result.CleanupConfirmed, result.EndReason,
            result.RawReceivedSamples, result.ReceivedSamples, result.RemovedRepeatedSamples, result.ReceiveFraming,
            sent_rtp = endpoint.Metrics.SentPackets, received_rtp = endpoint.Metrics.ReceivedPackets,
            source_level = endpoint.Metrics.PbxReceivedAudio, playback_level = endpoint.Metrics.ModemPlaybackAudio, playback_fill = endpoint.Metrics.ModemPlaybackSilenceFillSamples,
            physical_dtmf_simulated = true, private_credentials_exported = false, audio_recorded = false };
    }
}
