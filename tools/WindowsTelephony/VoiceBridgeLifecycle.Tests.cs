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
public sealed class LifecycleDuplexProcessor : IVoiceDuplexProcessor {
 public bool Started,Disposed,FailReceive;public int Sent,Received;readonly DiagnosticFakePort port;
 public LifecycleDuplexProcessor(DiagnosticFakePort port){this.port=port;}
 public void StartDuplex(){if(!port.Commands.Contains("AT+VTR"))throw new Exception("Processor started before duplex acknowledgment");Started=true;}
 public void ObserveTransmit(byte[] samples){if(!Started)throw new Exception("Reference before duplex");Sent+=samples.Length;}
 public byte[] ProcessReceive(byte[] samples){if(!Started)throw new Exception("Receive before duplex");if(FailReceive)throw new Exception("synthetic_echo_failure");Received+=samples.Length;return samples;}
 public void Dispose(){Disposed=true;}
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
        Check(!port.Commands.Contains("AT+VGT?") && !port.Commands.Contains("AT+VGT=128"),"Normal bridge must leave gain untouched");
        Check(session.RecordingPath==null && session.Result.RecordingFile=="" && !Directory.Exists(folder),"Bridge must not record caller audio");
        Check(session.Result.ReceivedSamples>0 && session.Result.RawReceivedSamples>=session.Result.ReceivedSamples,"Streaming counters must survive buffer draining");
        Check(port.HostFlow=="2,2" && session.Result.EffectiveFlowControl=="2,2" && session.Result.HostFlowControlSynchronized && !port.Commands.Contains("AT+IFC=0,0"),"Hardware flow setting must be preserved and synchronized");
        port=new DiagnosticFakePort{FlowSetting="0,0"};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(port.HostFlow=="0,0" && session.Result.Status=="bridged" && session.Result.HostFlowControlSynchronized,"A modem already using no flow must keep that mode");
        port=new DiagnosticFakePort{FlowSetting="1,1"};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(!session.Result.Answered && session.Result.ErrorCode=="unsupported_serial_flow_control" && session.Result.CleanupConfirmed && !port.Commands.Contains("AT+IFC=0,0"),"Unknown flow must not be silently disabled");
        port=new DiagnosticFakePort{RejectHostFlow=true};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(!session.Result.Answered && !session.Result.HostFlowControlSynchronized && session.Result.CleanupConfirmed && session.Result.FailureStage=="synchronize_flow_control","Host flow failure must stop before answering and restore state");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint {FailReceive=true};session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(session.Result.Status=="failed" && session.Result.ErrorCode=="synthetic_media_failure" && session.Result.CleanupConfirmed && endpoint.Disposed && port.Commands.Contains("ATH"),"Media failure must hang up and dispose endpoint");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint {EndAfterBlocks=10000};session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();for(int n=0;n<1000 && session.Progress!="streaming" && !session.Completed;n++)Thread.Sleep(5);session.Cancel();Finish(session);
        Check(session.Result.Cancelled && session.Result.CleanupConfirmed && endpoint.Disposed && port.Disposed,"User cancellation must restore modem and dispose SIP endpoint");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint {FailDispose=true};session=new VoiceSession(port,folder,300,60000,endpoint);session.Start();Finish(session);
        Check(session.Result.Status=="failed" && session.Result.FailureStage=="cleanup_pbx" && session.Result.ErrorCode=="pbx_cleanup_failed" && session.Result.CleanupConfirmed && port.Disposed,"SIP cleanup failure must be reported after releasing modem");
        Check(VoiceTransmitLevel.Current("AT+VGT?\r\r\n0\r\nOK\r\n")=="0","Actual scalar zero snapshot must be preserved");
        Check(VoiceTransmitLevel.Supports("(0,126-130)\r\nOK\r\n",128) && !VoiceTransmitLevel.Supports("(0,126-130)\r\nOK\r\n",131),"Actual advertised range must be enforced");
        Check(VoiceTransmitLevel.Current("0\r\n128\r\nOK\r\n")=="" && !VoiceTransmitLevel.Supports("(0,130-126)\r\nOK\r\n",128) && !VoiceTransmitLevel.Supports("(0,126-130)\r\nERROR\r\n",128),"Conflicting or rejected gain evidence must fail");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,128);session.Start();Finish(session);
        Check(session.Result.TransmitLevelApplied && session.Result.TransmitLevelRestored && session.Result.OriginalTransmitLevel=="0" && session.Result.CleanupConfirmed && port.Commands.Contains("AT+VGT=128") && port.Commands.Contains("AT+VGT=0"),"Opt-in gain must be set then restored after call");
        port=new DiagnosticFakePort {SupportedTransmitLevels="(0,126-127)"};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,128);session.Start();Finish(session);
        Check(!session.Result.Answered && !port.Commands.Contains("AT+VGT=128") && session.Result.CleanupConfirmed,"Unsupported gain must fail before setting or answering");
        port=new DiagnosticFakePort {CurrentTransmitLevel="unknown"};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,128);session.Start();Finish(session);
        Check(!session.Result.Answered && !port.Commands.Contains("AT+VGT=128") && session.Result.CleanupConfirmed,"Missing original gain must fail before setting");
        port=new DiagnosticFakePort {RejectTransmitLevel=true};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,128);session.Start();Finish(session);
        Check(!session.Result.Answered && session.Result.TransmitLevelRestored && port.Commands.Contains("AT+FCLASS=0"),"Rejected setting must still attempt original gain and class restoration");
        port=new DiagnosticFakePort {IgnoreTransmitLevel=true};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,128);session.Start();Finish(session);
        Check(!session.Result.Answered && !session.Result.TransmitLevelApplied && session.Result.TransmitLevelRestored && session.Result.FailureStage=="verify_transmit_level","Unconfirmed readback must fail before answering and restore gain");
        port=new DiagnosticFakePort {RejectTransmitRestore=true};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,128);session.Start();Finish(session);
        Check(!session.Result.TransmitLevelRestored && !session.Result.CleanupConfirmed && session.Result.Status=="failed" && port.Commands.Contains("AT+FCLASS=0"),"Gain restore failure must report failure but still restore class");
        port=new DiagnosticFakePort {NoRing=true};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,2000,60000,endpoint,128);session.Start();Thread.Sleep(100);session.Cancel();Finish(session);
        Check(session.Result.Cancelled && session.Result.TransmitLevelRestored && session.Result.CleanupConfirmed,"Cancelled gain trial must restore original gain");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint();var processor=new LifecycleDuplexProcessor(port);session=new VoiceSession(port,folder,300,60000,endpoint,null,false,processor);session.Start();Finish(session);
        Check(processor.Started && processor.Disposed && processor.Sent==session.Result.TransmittedSamples && processor.Received==endpoint.Samples && session.Result.Status=="bridged","Optional DSP must receive actual duplex samples and be disposed");
        port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint();processor=new LifecycleDuplexProcessor(port){FailReceive=true};session=new VoiceSession(port,folder,300,60000,endpoint,null,false,processor);session.Start();Finish(session);
        Check(session.Result.ErrorCode=="synthetic_echo_failure" && session.Result.CleanupConfirmed && processor.Disposed && endpoint.Disposed && port.Disposed,"DSP failure must still release modem, PBX and native resources");
        port=new DiagnosticFakePort{NoRing=true};endpoint=new BridgeLifecycleEndpoint();processor=new LifecycleDuplexProcessor(port);session=new VoiceSession(port,folder,2000,60000,endpoint,null,false,processor);session.Start();Thread.Sleep(100);session.Cancel();Finish(session);
        Check(!processor.Started && processor.Sent==0 && processor.Disposed && session.Result.CleanupConfirmed,"Cancellation before answer must dispose unstarted DSP");
        port=new DiagnosticFakePort{RejectAudioWrite=true};endpoint=new BridgeLifecycleEndpoint();processor=new LifecycleDuplexProcessor(port);session=new VoiceSession(port,folder,300,60000,endpoint,null,false,processor);session.Start();Finish(session);
        Check(processor.Sent==0 && processor.Disposed && session.Result.ErrorCode=="synthetic_serial_write_failure" && session.Result.CleanupConfirmed,"Failed serial write must never become echo reference and must release the line");
        bool rejected=false;try{new LocalSipAudioEndpoint("8.8.8.8",5160,"office_modem",new string('a',48),"1002");}catch(ArgumentException){rejected=true;}Check(rejected,"Off-host PBX must be rejected");
        using(var sip=new LocalSipAudioEndpoint("127.0.0.1",5160,"office_modem",new string('a',48),"1002")){
            sip.ReceiveAudio(new byte[8000]);rejected=false;try{sip.ReceiveAudio(new byte[1]);}catch(InvalidOperationException){rejected=true;}Check(rejected,"Outgoing media queue must remain bounded");
        }
        return "PASS: bridge failure/cleanup, remote termination, no call recording, streaming counters, local-only endpoint and bounded media queue. No real serial port opened.";
    }
}
