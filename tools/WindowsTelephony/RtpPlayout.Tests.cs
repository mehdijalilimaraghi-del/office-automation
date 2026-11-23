using System;
using System.Linq;
using System.Collections.Generic;
using OfficeAutomation.Telephony.Bridge;
public static class RtpPlayoutChecks {
 static void Check(bool ok,string why){if(!ok)throw new Exception(why);}
 static byte[] Frame(int id,int length=160){return Enumerable.Repeat((byte)(128+id%80),length).ToArray();}
 public static string Run(){
  var metrics=new PlayoutMetrics();var buffer=new RtpPlayoutBuffer(metrics);var rendered=new List<byte>();var expected=new List<byte>();var oldQueue=new Queue<byte>();int oldSilence=0;
  for(int i=0;i<100;i++)expected.AddRange(Frame(i));
  // Burst/reordering jitter that causes the previous empty-FIFO renderer to inject gaps.
  for(int tick=0;tick<150&&metrics.RenderedSourceSamples<16000;tick++){
   for(int id=0;id<100;id++){
    int due=id+(id%6==1||id%6==3?1:id%6==4?-1:0);
    if(due==tick){foreach(byte value in Frame(id))oldQueue.Enqueue(value);buffer.Add((ushort)id,(uint)(id*160),1,Frame(id));if(id%10==0)buffer.Add((ushort)id,(uint)(id*160),1,Frame(id));}
   }
   for(int i=0;i<160;i++){if(oldQueue.Count>0)oldQueue.Dequeue();else oldSilence++;}
   long wait=metrics.WaitingSamples;byte[] audio=buffer.Read();if(metrics.WaitingSamples==wait)rendered.AddRange(audio);
  }
  Check(oldSilence>0,"Jitter fixture must reproduce the old FIFO underrun");
  Check(metrics.ConcealedSamples==0&&rendered.SequenceEqual(expected),"Jitter/reordering inserted gaps, reordered or changed valid samples");
  Check(metrics.DuplicatePackets==10&&metrics.ReorderedPackets>0,"Duplicate/reordering counters incorrect");
  Check(metrics.Windows.Count==2&&metrics.Windows.All(w=>w.SourceSamples+w.WaitingSamples+w.ConcealedSamples==8000),"One-second diagnostic windows invalid");
  Check(metrics.Windows.All(w=>w.SourcePeak>0&&w.PlaybackPeak>0&&w.SourceActiveRmsDbfs.HasValue&&w.PlaybackActiveRmsDbfs.HasValue),"Interval audio levels missing");
  metrics=new PlayoutMetrics();buffer=new RtpPlayoutBuffer(metrics);
  buffer.Add(1,160,1,Frame(1));buffer.Add(0,0,1,Frame(0));buffer.Add(2,320,1,Frame(2));
  buffer.Read();buffer.Read();Check(buffer.Read().SequenceEqual(Frame(0)),"First arrivals out of order lost the earliest frame");
  metrics=new PlayoutMetrics();buffer=new RtpPlayoutBuffer(metrics);buffer.Add(0,0,1,Frame(0));
  for(int i=0;i<5;i++)buffer.Read();Check(buffer.Read().SequenceEqual(Frame(0)),"Single packet startup waited indefinitely");
  metrics=new PlayoutMetrics();buffer=new RtpPlayoutBuffer(metrics);
  for(int i=0;i<2000;i++)buffer.Add((ushort)i,(uint)(i*160),1,Frame(i));
  Check(metrics.BufferedSamples<=4800&&metrics.MaxBufferedSamples<=4800&&metrics.TimestampResets>0,"Packet flood exceeded bounded memory/delay");
  Check(metrics.MaxBufferedSamples<=4800,"Unbounded packet queue");
  // A genuinely absent packet gets bounded concealment; following source samples retain their timestamps.
  metrics=new PlayoutMetrics();buffer=new RtpPlayoutBuffer(metrics);
  foreach(int id in new[]{0,1,3,4})buffer.Add((ushort)id,(uint)(id*160),1,Frame(id));
  buffer.Read();buffer.Read();Check(buffer.Read().SequenceEqual(Frame(0)),"Startup source changed");
  Check(buffer.Read().SequenceEqual(Frame(1)),"Second frame changed");byte[] hidden=buffer.Read();
  Check(metrics.ConcealedSamples==160&&hidden.Any(b=>b!=255),"Packet loss was replaced with abrupt silence");
  Check(buffer.Read().SequenceEqual(Frame(3)),"Missing packet shifted the next source frame");
  Check(!buffer.Add(2,320,1,Frame(2))&&metrics.LatePackets==1,"Late packet replayed after playout");
  // Timestamp/sequence wrap and mixed packet lengths must preserve the sample clock.
  metrics=new PlayoutMetrics();buffer=new RtpPlayoutBuffer(metrics);
  buffer.Add(65535,uint.MaxValue-79,5,Frame(0,80));buffer.Add(0,0,5,Frame(1,160));
  buffer.Add(1,160,5,Frame(2,160));buffer.Add(2,320,5,Frame(3,80));
  buffer.Read();buffer.Read();var mixed=buffer.Read().Concat(buffer.Read()).Concat(buffer.Read()).ToArray();
  Check(mixed.SequenceEqual(Frame(0,80).Concat(Frame(1)).Concat(Frame(2)).Concat(Frame(3,80)))&&metrics.ConcealedSamples==0,"Clock wrap or packet duration broke audio");
  // A prolonged gap must recover when new audio arrives, not remain permanently late.
  for(int i=0;i<3;i++)buffer.Read();Check(metrics.RebufferEvents==1,"Long gap did not trigger recovery");
  for(int i=0;i<3;i++)buffer.Add((ushort)(10+i),(uint)(10000+i*160),5,Frame(10+i));
  buffer.Read();buffer.Read();Check(buffer.Read().SequenceEqual(Frame(10)),"Audio did not recover after long gap");
  buffer.Add(40,900000,6,Frame(40));Check(metrics.SourceChanges==1&&metrics.BufferedSamples<=4800,"Source switch was not bounded");
  Check(!buffer.Add(41,900160,5,Frame(41))&&metrics.SourceChanges==1,"Old source resurrected after switch");
  buffer.Reset();Check(metrics.BufferedSamples==0,"Media reset left stale audio");
  return "PASS: deterministic burst/reordered/duplicate RTP, exact source bytes, bounded faded concealment, late rejection, clock wrap, mixed durations, long-gap recovery, source changes and one-second counters. No modem/audio recording.";
 }
}
