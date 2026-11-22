using System;
using System.IO;
using System.Linq;
using System.Threading;
using OfficeAutomation.Telephony.Diagnostics;
using OfficeAutomation.Telephony.Bridge;

public sealed class BridgeLifecycleEndpoint : IVoiceAudioEndpoint
{
    public bool FailOpen, FailReceive, FailDispose, Disposed;
    public int Blocks, Samples; public int EndAfterBlocks=35;
    public bool Ended { get { return Blocks >= EndAfterBlocks; } }
    public void Open(CancellationToken cancellation) { cancellation.ThrowIfCancellationRequested(); if(FailOpen)throw new InvalidOperationException("synthetic_pbx_failure"); }
    public void ReceiveAudio(byte[] samples) { if(FailReceive)throw new InvalidOperationException("synthetic_media_failure"); Samples+=samples.Length; }
    public byte[] TransmitAudio(int count) { Blocks++; return Enumerable.Repeat((byte)255,count).ToArray(); }
    public void Dispose() { Disposed=true; if(FailDispose)throw new InvalidOperationException("synthetic_dispose_failure"); }
}
public static class BridgeLifecycleChecks
{
    private static void Check(bool condition,string name){if(!condition)throw new Exception(name);}
    private static void Finish(VoiceSession session){for(int n=0;n<2000 && !session.Completed;n++)Thread.Sleep(5);if(!session.Completed){session.Cancel();throw new Exception("Session timed out");}}
    public static string Run()
    {
        string folder=Path.Combine(Path.GetTempPath(),"office-bridge-no-recording-"+Guid.NewGuid().ToString("N"));
        var port=new DiagnosticFakePort();var endpoint=new BridgeLifecycleEndpoint {FailOpen=true};var session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(session.Result.FailureStage=="connect_pbx" && !session.Result.Answered && session.Result.CleanupConfirmed && endpoint.Disposed && port.Disposed && !port.Commands.Contains("AT+VLS=1"),"PBX failure must restore settings without answering line");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Console.WriteLine("Lifecycle: "+session.Result.Status+"/"+session.Result.ErrorCode+"/"+session.Result.EndReason+"; received "+endpoint.Samples);
        Check(session.Result.Status=="bridged" && session.Result.EndReason=="pbx_hangup" && session.Result.CleanupConfirmed && endpoint.Disposed && endpoint.Samples>0,"Remote SIP end must release modem");
        Check(session.RecordingPath==null && session.Result.RecordingFile=="" && !Directory.Exists(folder),"Bridge must not record caller audio");
        Check(session.Result.ReceivedSamples>0 && session.Result.RawReceivedSamples>=session.Result.ReceivedSamples,"Streaming counters must survive buffer draining");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint {FailReceive=true};session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(session.Result.Status=="failed" && session.Result.ErrorCode=="synthetic_media_failure" && session.Result.CleanupConfirmed && endpoint.Disposed && port.Commands.Contains("ATH"),"Media failure must hang up and dispose endpoint");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint {EndAfterBlocks=10000};session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();for(int n=0;n<1000 && session.Progress!="streaming" && !session.Completed;n++)Thread.Sleep(5);session.Cancel();Finish(session);
        Check(session.Result.Cancelled && session.Result.CleanupConfirmed && endpoint.Disposed && port.Disposed,"User cancellation must restore modem and dispose SIP endpoint");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint {FailDispose=true};session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(session.Result.Status=="failed" && session.Result.FailureStage=="cleanup_pbx" && session.Result.ErrorCode=="pbx_cleanup_failed" && session.Result.CleanupConfirmed && port.Disposed,"SIP cleanup failure must be reported after releasing modem");
        bool rejected=false;try{new LocalSipAudioEndpoint("8.8.8.8",5160,"office_modem",new string('a',48),"1002");}catch(ArgumentException){rejected=true;}Check(rejected,"Off-host PBX must be rejected");
        using(var sip=new LocalSipAudioEndpoint("127.0.0.1",5160,"office_modem",new string('a',48),"1002")){
            sip.ReceiveAudio(new byte[8000]);rejected=false;try{sip.ReceiveAudio(new byte[1]);}catch(InvalidOperationException){rejected=true;}Check(rejected,"Outgoing media queue must remain bounded");
        }
        return "PASS: bridge failure/cleanup, remote termination, no call recording, streaming counters, local-only endpoint and bounded media queue. No real serial port opened.";
    }
}
