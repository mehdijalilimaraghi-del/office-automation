using System;
using System.IO;
using System.Threading;
using System.Collections.Generic;
using OfficeAutomation.Telephony.Diagnostics;
public static class VoicePcm16Checks {
 static void Check(bool value,string why) { if(!value)throw new Exception(why); }
 static void Finish(VoiceSession session) { for(int i=0;i<1600&&!session.Completed;i++)Thread.Sleep(5);if(!session.Completed){session.Cancel();throw new Exception("PCM test timed out");} }
 public static string Run() {
  string formats="0,\"Signed PCM\",16,0,8000,(0,122-148),0\r\n4,\"G.711U\",8,0,8000\r\nOK\r\n";
  Check(VoiceCodec.FindPcm16(formats)==0,"Actual signed PCM16 advertisement not recognized");
  Check(VoiceCodec.FindPcm16("0,\"Unsigned PCM\",16,0,8000\r\nOK\r\n")==-1 && VoiceCodec.FindPcm16("0,\"Signed PCM\",8,0,8000\r\nOK\r\n")==-1,"Unsigned/8-bit must not be guessed as signed PCM16");
  for(int value=0;value<256;value++){
   byte[] pcm=VoiceCodec.Pcm16FromMuLaw(new[]{(byte)value});
   Check(BitConverter.ToInt16(pcm,0)==VoiceCodec.DecodeMuLaw((byte)value),"PCM transmit amplitude or endian mismatch");
   var decoder=new VoiceDecoder(false,true);byte[] wire=VoiceCodec.Escape(pcm);
   foreach(byte b in wire)decoder.Feed(new[]{b},1);decoder.Finish();
   Check(decoder.RawSampleCount==1 && decoder.Audio.Count==1 && decoder.FramingValid && VoiceCodec.DecodeMuLaw(decoder.Audio[0])==VoiceCodec.DecodeMuLaw((byte)value),"Split PCM/DLE roundtrip changed level");
  }
  var partial=new VoiceDecoder(false,true);partial.Feed(new byte[]{1},1);partial.Finish();
  Check(!partial.FramingValid && partial.IncompletePcmSample && partial.RawSampleCount==0,"Incomplete PCM sample was accepted");
  var events=new VoiceDecoder(false,true);events.Feed(new byte[]{16,16,16,(byte)'s',16,16,16,3},8);events.Finish();
  Check(events.Ended && events.Events.Contains("silence") && events.Audio.Count==1 && !events.IncompletePcmSample,"Events interleaved with PCM must not change byte alignment");
  var source=new List<byte>();var random=new Random(761);
  for(int frame=0;frame<85;frame++){
   var block=new byte[60];for(int i=0;i<48;i++)block[i]=VoiceCodec.EncodeMuLaw((short)random.Next(-9000,9001));
   for(int i=48;i<60;i++)block[i]=block[i-10];source.AddRange(block);
  }
  var framed=new VoiceDecoder(true,true);byte[] framedWire=VoiceCodec.Escape(VoiceCodec.Pcm16FromMuLaw(source.ToArray()));
  for(int i=0;i<framedWire.Length;i++)framed.Feed(new[]{framedWire[i]},1);framed.Finish();
  Check(framed.FramingValid && framed.ReceiveFraming=="repeat_60_48_lag10" && framed.RawSampleCount==5100 && framed.DecodedSampleCount==4080,"Repeated framing must be detected after PCM adaptation");
  string folder=Path.Combine(Path.GetTempPath(),"office-pcm-no-record-"+Guid.NewGuid().ToString("N"));
  var port=new DiagnosticFakePort{Formats=formats.Replace("\r\nOK\r\n", "")};var endpoint=new BridgeLifecycleEndpoint();
  var session=new VoiceSession(port,folder,300,60000,endpoint,null,true);session.Start();Finish(session);
  Console.WriteLine("PCM lifecycle: "+session.Result.Status+"/"+session.Result.ErrorCode+"/"+session.Result.FailureStage+"; codec="+session.Result.ModemCodec+"; samples/bytes="+session.Result.TransmittedSamples+"/"+session.Result.ModemTransmitBytes+"; cleanup="+session.Result.CleanupConfirmed);
  Check(session.Result.Status=="bridged" && session.Result.ModemCodec==0 && session.Result.ModemSampleFormat=="pcm16le_8000" && session.Result.ModemTransmitBytes==session.Result.TransmittedSamples*2 && session.Result.CleanupConfirmed && session.RecordingPath==null && !port.Commands.Contains("AT+VGT?"),"PCM bridge must preserve samples, restoration and no-recording/no-gain behavior");
  port=new DiagnosticFakePort();endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,null,true);session.Start();Finish(session);
  Check(!session.Result.Answered && !session.Result.BridgeStarted && session.Result.ErrorCode=="no_supported_8khz_pcm16" && session.Result.CleanupConfirmed,"Unsupported PCM must fail before answer");
  port=new DiagnosticFakePort{Formats=formats.Replace("\r\nOK\r\n", ""),CurrentFormat="4,8000"};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,300,60000,endpoint,null,true);session.Start();Finish(session);
  Check(!session.Result.Answered && session.Result.FailureStage=="verify_modem_sample_format" && session.Result.CleanupConfirmed,"Unconfirmed codec must stop before answer");
  port=new DiagnosticFakePort{Formats=formats.Replace("\r\nOK\r\n", ""),NoRing=true};endpoint=new BridgeLifecycleEndpoint();session=new VoiceSession(port,folder,2000,60000,endpoint,null,true);session.Start();Thread.Sleep(100);session.Cancel();Finish(session);
  Check(session.Result.Cancelled && session.Result.CleanupConfirmed && port.Disposed,"PCM cancellation must restore and release modem");
  port=new DiagnosticFakePort{Formats=formats.Replace("\r\nOK\r\n", ""),AudioWriteDelayMilliseconds=35};endpoint=new BridgeLifecycleEndpoint{EndAfterBlocks=10000};
  session=new VoiceSession(port,folder,300,60000,endpoint,null,true);session.Start();Finish(session);
  Check(session.Result.ErrorCode=="audio_transmit_timing_failed" && session.Result.EndReason=="audio_timing_failure" && session.Result.StreamMilliseconds>250
    && session.Result.MaxSerialWriteMilliseconds>=30 && session.Result.MaxTransmitLagMilliseconds>250 && session.Result.SampleWindows.Count>0
    && session.Result.CleanupConfirmed,"Blocked serial output must report real timing on failure and restore modem");
  return "PASS: PCM16 advertisement, all PCMU amplitudes, split samples/DLE/events, incomplete sample rejection, framing, bridge lifecycle/no recording/no gain, unsupported format and cancellation. No real modem.";
 }
}
