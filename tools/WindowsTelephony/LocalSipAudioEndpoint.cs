using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using OfficeAutomation.Telephony.Diagnostics;

namespace OfficeAutomation.Telephony.Bridge
{
    public sealed class AudioLevelMeter
    {
        public long Samples { get; private set; }
        public int Peak { get; private set; }
        public long ActiveSamples { get; private set; }
        private double squares, activeSquares;
        public double? RmsDbfs { get { return Samples == 0 || squares == 0 ? (double?)null : 10 * Math.Log10(squares / Samples / (32768.0 * 32768.0)); } }
        public double? ActiveRmsDbfs { get { return ActiveSamples == 0 ? (double?)null : 10 * Math.Log10(activeSquares / ActiveSamples / (32768.0 * 32768.0)); } }
        public void Observe(byte value)
        {
            int sample = VoiceCodec.DecodeMuLaw(value), magnitude = Math.Abs(sample);
            Samples++; Peak = Math.Max(Peak, magnitude); squares += (double)sample * sample;
            if (magnitude > 500) { ActiveSamples++; activeSquares += (double)sample * sample; }
        }
    }

    public sealed class PlayoutWindow {
        public int Milliseconds;
        public long Packets,SourceSamples,WaitingSamples,ConcealedSamples,LatePackets,ReorderedPackets,DuplicatePackets;
        public int BufferedSamples,SourcePeak,PlaybackPeak;
        public double? SourceActiveRmsDbfs,PlaybackActiveRmsDbfs;
    }
    public sealed class PlayoutMetrics {
        public long AcceptedPackets,DuplicatePackets,LatePackets,ReorderedPackets,SourceChanges,TimestampResets,RebufferEvents,MediaResets;
        public long WaitingSamples,ConcealedSamples,ConcealmentSilentSamples,RenderedSourceSamples;
        public int BufferedSamples,MaxBufferedSamples;
        public readonly List<PlayoutWindow> Windows=new List<PlayoutWindow>();
    }
    // 8kHz PCMU clock: 60ms target, at most 120ms priming and 60ms faded concealment per gap.
    public sealed class RtpPlayoutBuffer {
        const int Capacity=8192,MaxAhead=4800,Target=480;
        readonly byte[] data=new byte[Capacity],previous=new byte[160];
        readonly long[] tags=new long[Capacity];
        readonly HashSet<long> sequences=new HashSet<long>();
        readonly Queue<long> sequenceOrder=new Queue<long>();
        readonly PlayoutMetrics metrics;
        AudioLevelMeter sourceWindow=new AudioLevelMeter(),playbackWindow=new AudioLevelMeter();
        bool clock,playing;uint source,oldSource;bool hasOldSource;
        long head,latestTimestamp,lastSequence,latestEnd;
        int waitFrames,missing,renderFrames;
        long lastPackets,lastSource,lastWaiting,lastConcealed,lastLate,lastReordered,lastDuplicate;
        public PlayoutMetrics Metrics {get{return metrics;}}
        public RtpPlayoutBuffer(PlayoutMetrics metrics){this.metrics=metrics;Clear();}
        void Clear(){
            for(int i=0;i<tags.Length;i++)tags[i]=long.MinValue;
            for(int i=0;i<previous.Length;i++)previous[i]=255;
            sequences.Clear();sequenceOrder.Clear();clock=false;playing=false;waitFrames=0;missing=0;metrics.BufferedSamples=0;
        }
        public void Reset(){Clear();hasOldSource=false;}
        void Start(uint timestamp,ushort sequence,uint ssrc){clock=true;source=ssrc;head=latestTimestamp=timestamp;latestEnd=head;lastSequence=sequence;}
        public bool Add(ushort sequence,uint timestamp,uint ssrc,byte[] payload) {
            if(payload==null||payload.Length<1||payload.Length>480)throw new ArgumentException("Invalid PCMU packet size");
            if(!clock){if(hasOldSource&&ssrc==oldSource){metrics.LatePackets++;return false;}Start(timestamp,sequence,ssrc);}
            else if(ssrc!=source){
                if(hasOldSource&&ssrc==oldSource){metrics.LatePackets++;return false;}
                oldSource=source;hasOldSource=true;metrics.SourceChanges++;Clear();Start(timestamp,sequence,ssrc);
            }
            long seq=lastSequence+unchecked((short)(sequence-(ushort)lastSequence));
            if(sequences.Contains(seq)){metrics.DuplicatePackets++;return false;}
            long time=latestTimestamp+unchecked((int)(timestamp-(uint)latestTimestamp));
            if(!playing&&time<head&&latestEnd-time<=MaxAhead)head=time;
            if(time+payload.Length<=head){metrics.LatePackets++;return false;}
            if(time+payload.Length-head>MaxAhead){metrics.TimestampResets++;Clear();Start(timestamp,sequence,ssrc);time=timestamp;seq=sequence;}
            if(seq<lastSequence)metrics.ReorderedPackets++;
            lastSequence=Math.Max(lastSequence,seq);latestTimestamp=Math.Max(latestTimestamp,time);
            sequences.Add(seq);sequenceOrder.Enqueue(seq);if(sequenceOrder.Count>128)sequences.Remove(sequenceOrder.Dequeue());
            for(int i=0;i<payload.Length;i++){
                long position=time+i;if(position<head||position>=head+MaxAhead)continue;
                int index=(int)(position&(Capacity-1));
                if(tags[index]!=position){tags[index]=position;data[index]=payload[i];metrics.BufferedSamples++;}
            }
            latestEnd=Math.Max(latestEnd,time+payload.Length);metrics.AcceptedPackets++;foreach(byte sample in payload)sourceWindow.Observe(sample);
            metrics.MaxBufferedSamples=Math.Max(metrics.MaxBufferedSamples,metrics.BufferedSamples);return true;
        }
        public byte[] Read() {
            var result=new byte[160];
            if(!clock||(!playing&&(++waitFrames<3||latestEnd-head<Target)&&waitFrames<6)){
                for(int i=0;i<160;i++)result[i]=255;metrics.WaitingSamples+=160;Window(result);return result;
            }
            playing=true;
            for(int i=0;i<160;i++){
                int index=(int)(head&(Capacity-1));
                if(tags[index]==head){result[i]=data[index];tags[index]=long.MinValue;metrics.BufferedSamples--;metrics.RenderedSourceSamples++;missing=0;}
                else{
                    missing++;metrics.ConcealedSamples++;
                    double gain=Math.Max(0,1-missing/480.0);
                    result[i]=VoiceCodec.EncodeMuLaw((short)(VoiceCodec.DecodeMuLaw(previous[i])*gain));
                    if(VoiceCodec.DecodeMuLaw(result[i])==0)metrics.ConcealmentSilentSamples++;
                }
                head++;
            }
            Array.Copy(result,previous,160);
            if(missing>=480){metrics.RebufferEvents++;Clear();}
            Window(result);return result;
        }
        void Window(byte[] audio){
            foreach(byte sample in audio)playbackWindow.Observe(sample);
            renderFrames++;if(renderFrames%50!=0||metrics.Windows.Count>=120)return;
            metrics.Windows.Add(new PlayoutWindow{Milliseconds=renderFrames*20,Packets=metrics.AcceptedPackets-lastPackets,
                SourceSamples=metrics.RenderedSourceSamples-lastSource,WaitingSamples=metrics.WaitingSamples-lastWaiting,
                ConcealedSamples=metrics.ConcealedSamples-lastConcealed,LatePackets=metrics.LatePackets-lastLate,
                ReorderedPackets=metrics.ReorderedPackets-lastReordered,DuplicatePackets=metrics.DuplicatePackets-lastDuplicate,
                BufferedSamples=metrics.BufferedSamples,SourcePeak=sourceWindow.Peak,PlaybackPeak=playbackWindow.Peak,
                SourceActiveRmsDbfs=sourceWindow.ActiveRmsDbfs,PlaybackActiveRmsDbfs=playbackWindow.ActiveRmsDbfs});
            sourceWindow=new AudioLevelMeter();playbackWindow=new AudioLevelMeter();
            lastPackets=metrics.AcceptedPackets;lastSource=metrics.RenderedSourceSamples;lastWaiting=metrics.WaitingSamples;
            lastConcealed=metrics.ConcealedSamples;lastLate=metrics.LatePackets;lastReordered=metrics.ReorderedPackets;lastDuplicate=metrics.DuplicatePackets;
        }
    }

    public sealed class SipBridgeMetrics
    {
        public int SentPackets, ReceivedPackets, SilenceSamples, ReceiveQueueDrops, RejectedPackets;
        public readonly PlayoutMetrics Playout=new PlayoutMetrics();
        public bool Authenticated, Answered, RemoteHangup, LocalHangupAcknowledged;
        public long ModemPlaybackSilenceFillSamples;
        public readonly AudioLevelMeter PbxReceivedAudio = new AudioLevelMeter();
        public readonly AudioLevelMeter ModemPlaybackAudio = new AudioLevelMeter();
    }

    // A single, bounded, local-PC SIP/PCMU dialog. No registration, external dialing or credential logging.
    public sealed class LocalSipAudioEndpoint : IVoiceAudioEndpoint
    {
        private readonly UdpClient signal, media;
        private readonly IPEndPoint server;
        private readonly string host, username, password, destination;
        private readonly bool deferGreeting;
        public string GreetingChannel { get; private set; }
        public string DialogCallId { get { return callId; } }
        private readonly Queue<byte> outgoing = new Queue<byte>();
        private readonly RtpPlayoutBuffer playout;
        private readonly string callId = Guid.NewGuid().ToString("N"), tag = Guid.NewGuid().ToString("N");
        private readonly uint ssrc;
        private IPEndPoint remoteMedia;
        private string remoteTo, remoteContact, inviteBranch, remoteInviteSequence;
        private int inviteSequence = 1, localSequence = 2, sequence;
        private uint timestamp;
        private bool opened, disposed, invitePending;
        private byte[] lastInviteOk;
        private readonly SipBridgeMetrics metrics = new SipBridgeMetrics();
        public SipBridgeMetrics Metrics { get { return metrics; } }
        public bool Ended { get; private set; }

        public LocalSipAudioEndpoint(string host, int port, string username, string password, string destination) : this(host,port,username,password,destination,false) { }
        public LocalSipAudioEndpoint(string host, int port, string username, string password, string destination, bool deferGreeting)
        {
            IPAddress address;
            if (!IPAddress.TryParse(host, out address) || address.AddressFamily != AddressFamily.InterNetwork
                || !NetworkInterface.GetAllNetworkInterfaces().SelectMany(n => n.GetIPProperties().UnicastAddresses).Any(a => a.Address.Equals(address)))
                throw new ArgumentException("PBX must be an IPv4 address assigned to this computer");
            if (port < 1024 || port > 65535 || !Regex.IsMatch(username, @"\A[A-Za-z0-9_]{1,32}\z")
                || !Regex.IsMatch(password, @"\A[a-f0-9]{48}\z") || !Regex.IsMatch(destination, @"\A[1-9][0-9]{2,5}\z"))
                throw new ArgumentException("Invalid local gateway settings");
            if(deferGreeting && (username != "office_modem" || destination != "9197"))throw new ArgumentException("Local greeting requires the configured modem IVR");
            this.deferGreeting=deferGreeting;playout=new RtpPlayoutBuffer(metrics.Playout);
            this.host = host; this.username = username; this.password = password; this.destination = destination;
            server = new IPEndPoint(address, port);
            signal = new UdpClient(new IPEndPoint(address, 0));
            try { media = new UdpClient(new IPEndPoint(address, 0)); }
            catch { signal.Close(); throw; }
            var random = new byte[4]; using (var rng = RandomNumberGenerator.Create()) rng.GetBytes(random);
            ssrc = BitConverter.ToUInt32(random, 0);
        }

        private int SignalPort { get { return ((IPEndPoint)signal.Client.LocalEndPoint).Port; } }
        private int MediaPort { get { return ((IPEndPoint)media.Client.LocalEndPoint).Port; } }
        private string LocalFrom { get { return "<sip:" + username + "@" + host + ">;tag=" + tag; } }
        private string Target { get { return "sip:" + destination + "@" + host + ":" + server.Port; } }
        private string InitialTo { get { return "<sip:" + destination + "@" + host + ">"; } }
        private string Contact { get { return "<sip:" + username + "@" + host + ":" + SignalPort + ">"; } }
        private static string Branch() { return "z9hG4bK" + Guid.NewGuid().ToString("N"); }
        private string Sdp()
        {
            return "v=0\r\no=office_modem 1 1 IN IP4 " + host + "\r\ns=Office modem bridge\r\nc=IN IP4 " + host
                + "\r\nt=0 0\r\nm=audio " + MediaPort + " RTP/AVP 0\r\na=rtpmap:0 PCMU/8000\r\na=ptime:20\r\na=sendrecv\r\n";
        }
        private byte[] Request(string method, string uri, int cseq, string to, string extra, string body, string branch)
        {
            return Encoding.UTF8.GetBytes(method + " " + uri + " SIP/2.0\r\nVia: SIP/2.0/UDP " + host + ":" + SignalPort
                + ";branch=" + branch + ";rport\r\nMax-Forwards: 5\r\nFrom: " + LocalFrom + "\r\nTo: " + to
                + "\r\nCall-ID: " + callId + "\r\nCSeq: " + cseq + " " + method + "\r\nContact: " + Contact
                + "\r\n" + extra + (body.Length > 0 ? "Content-Type: application/sdp\r\n" : "")
                + "Content-Length: " + Encoding.UTF8.GetByteCount(body) + "\r\n\r\n" + body);
        }
        private void Send(byte[] bytes) { signal.Send(bytes, bytes.Length, server); }

        private sealed class Message
        {
            public string First, Body;
            public string[] Lines;
            public int Code { get { int code; var words = First.Split(' '); return words.Length > 1 && words[0] == "SIP/2.0" && int.TryParse(words[1], out code) ? code : 0; } }
            public string Header(string name) { var line = Lines.FirstOrDefault(l => l.StartsWith(name + ":", StringComparison.OrdinalIgnoreCase)); return line == null ? "" : line.Substring(name.Length + 1).Trim(); }
            public IEnumerable<string> Headers(string name) { return Lines.Where(l => l.StartsWith(name + ":", StringComparison.OrdinalIgnoreCase)).Select(l => l.Substring(name.Length + 1).Trim()); }
            public static Message Parse(byte[] bytes)
            {
                if (bytes.Length > 8192) throw new InvalidOperationException("SIP packet too large");
                int end = -1;
                for (int i = 0; i + 3 < bytes.Length; i++) if (bytes[i] == 13 && bytes[i+1] == 10 && bytes[i+2] == 13 && bytes[i+3] == 10) { end = i; break; }
                if (end < 0) throw new InvalidOperationException("Invalid SIP framing");
                var lines = Encoding.UTF8.GetString(bytes, 0, end).Split(new[] { "\r\n" }, StringSplitOptions.None);
                var result = new Message { First = lines[0], Lines = lines, Body = "" };
                int length = 0; string raw = result.Header("Content-Length");
                if (raw.Length > 0 && (!int.TryParse(raw, out length) || length < 0 || length > 8192)) throw new InvalidOperationException("Invalid SIP body length");
                if (bytes.Length - end - 4 != length) throw new InvalidOperationException("Truncated SIP body");
                result.Body = Encoding.UTF8.GetString(bytes, end + 4, length);
                return result;
            }
        }
        private Message Read()
        {
            if (signal.Available == 0) return null;
            IPEndPoint source = null; byte[] data;
            try { data = signal.Receive(ref source); } catch (SocketException error) { if (error.SocketErrorCode == SocketError.ConnectionReset) { metrics.RejectedPackets++; return null; } throw; }
            if (!source.Equals(server)) { metrics.RejectedPackets++; return null; }
            try { return Message.Parse(data); } catch (InvalidOperationException) { metrics.RejectedPackets++; return null; }
        }
        private void Reply(Message request, int code, string phrase, string body)
        {
            var response = Encoding.UTF8.GetBytes("SIP/2.0 " + code + " " + phrase + "\r\n"
                + string.Concat(request.Headers("Via").Select(v => "Via: " + v + "\r\n"))
                + "From: " + request.Header("From") + "\r\nTo: " + request.Header("To")
                + "\r\nCall-ID: " + request.Header("Call-ID") + "\r\nCSeq: " + request.Header("CSeq")
                + "\r\nContact: " + Contact + "\r\n" + (body.Length > 0 ? "Content-Type: application/sdp\r\n" : "")
                + "Content-Length: " + Encoding.UTF8.GetByteCount(body) + "\r\n\r\n" + body);
            Send(response);
        }
        private Message Exchange(byte[] request, CancellationToken cancellation)
        {
            var watch = Stopwatch.StartNew(); long next = 500; int interval = 500; Send(request);
            while (watch.ElapsedMilliseconds < 8000)
            {
                cancellation.ThrowIfCancellationRequested();
                var message = Read();
                if (message != null && message.Header("Call-ID") == callId && message.Code >= 200
                    && message.Header("CSeq") == inviteSequence + " INVITE") return message;
                if (watch.ElapsedMilliseconds >= next) { Send(request); interval = Math.Min(4000, interval * 2); next = watch.ElapsedMilliseconds + interval; }
                Thread.Sleep(5);
            }
            throw new TimeoutException("sip_invite_timeout");
        }
        private static string Hash(string value) { using (var md5 = MD5.Create()) return string.Concat(md5.ComputeHash(Encoding.UTF8.GetBytes(value)).Select(b => b.ToString("x2"))); }
        private static string Parameter(string header, string name)
        {
            var match = Regex.Match(header, "(?:^|[, ])" + name + "=(?:\"([^\"]*)\"|([^, ]+))", RegexOptions.IgnoreCase);
            return match.Success ? (match.Groups[1].Success ? match.Groups[1].Value : match.Groups[2].Value) : "";
        }
        private string Authorization(Message challenge)
        {
            string value = challenge.Header("WWW-Authenticate"), name = "Authorization";
            if (value.Length == 0) { value = challenge.Header("Proxy-Authenticate"); name = "Proxy-Authorization"; }
            string realm = Parameter(value, "realm"), nonce = Parameter(value, "nonce"), qop = Parameter(value, "qop"), algorithm = Parameter(value, "algorithm");
            if (nonce.Length == 0 || (algorithm.Length > 0 && !algorithm.Equals("MD5", StringComparison.OrdinalIgnoreCase))
                || realm.Contains("\r") || realm.Contains("\n") || nonce.Contains("\r") || nonce.Contains("\n"))
                throw new InvalidOperationException("Unsupported SIP authentication challenge");
            string cnonce = Guid.NewGuid().ToString("N"), ha1 = Hash(username + ":" + realm + ":" + password), ha2 = Hash("INVITE:" + Target);
            bool useQop = qop.Split(',').Select(v => v.Trim()).Contains("auth");
            if (qop.Length > 0 && !useQop) throw new InvalidOperationException("Unsupported SIP auth quality");
            string response = useQop ? Hash(ha1 + ":" + nonce + ":00000001:" + cnonce + ":auth:" + ha2) : Hash(ha1 + ":" + nonce + ":" + ha2);
            return name + ": Digest username=\"" + username + "\", realm=\"" + realm + "\", nonce=\"" + nonce
                + "\", uri=\"" + Target + "\", response=\"" + response + "\", algorithm=MD5"
                + (useQop ? ", qop=auth, nc=00000001, cnonce=\"" + cnonce + "\"" : "") + "\r\n";
        }
        private void SetMedia(string sdp)
        {
            var connection = Regex.Match(sdp, @"(?m)^c=IN IP4 ([0-9.]+)\r?$");
            var audio = Regex.Match(sdp, @"(?m)^m=audio ([0-9]+) RTP/AVP ([0-9 ]+)\r?$");
            int port;
            if (!connection.Success || connection.Groups[1].Value != host || !audio.Success
                || !int.TryParse(audio.Groups[1].Value, out port) || port < 17000 || port > 17199
                || !audio.Groups[2].Value.Split(' ').Contains("0")) throw new InvalidOperationException("PBX media must be local G711 PCMU in the lab range");
            var nextMedia=new IPEndPoint(server.Address,port);
            if(remoteMedia!=null)metrics.Playout.MediaResets++;playout.Reset();
            remoteMedia=nextMedia;
        }
        public void Open(CancellationToken cancellation)
        {
            if (disposed || opened) throw new InvalidOperationException("SIP endpoint already used");
            cancellation.ThrowIfCancellationRequested();
            inviteBranch = Branch(); invitePending = true;
            var first = Exchange(Request("INVITE", Target, 1, InitialTo, deferGreeting ? "X-Office-Modem-Greeting: local-v1\r\n" : "", Sdp(), inviteBranch), cancellation);
            if (first.Code != 401 && first.Code != 407)
            {
                Send(Request("ACK", Target, 1, first.Header("To"), "", "", first.Code == 200 ? Branch() : inviteBranch)); invitePending = false;
                if (first.Code == 200) { remoteTo = first.Header("To"); remoteContact = Target; opened = true; }
                throw new InvalidOperationException("PBX must require gateway authentication");
            }
            Send(Request("ACK", Target, 1, first.Header("To"), "", "", inviteBranch));
            inviteSequence = 2; inviteBranch = Branch();
            var answer = Exchange(Request("INVITE", Target, 2, InitialTo, Authorization(first)+(deferGreeting ? "X-Office-Modem-Greeting: local-v1\r\n" : ""), Sdp(), inviteBranch), cancellation);
            if (answer.Code != 200) { Send(Request("ACK", Target, 2, answer.Header("To"), "", "", inviteBranch)); invitePending = false; throw new InvalidOperationException("PBX rejected gateway call: " + answer.Code); }
            remoteTo = answer.Header("To"); remoteContact = Target; opened = true; invitePending = false;
            var contact = Regex.Match(answer.Header("Contact"), "<(sip:[^>]+)>");
            string candidateContact = contact.Success ? contact.Groups[1].Value : answer.Header("Contact");
            if (!Regex.IsMatch(candidateContact, @"\Asip:[A-Za-z0-9_.-]+@" + Regex.Escape(host) + ":" + server.Port + @"(?:;[A-Za-z0-9=._-]+)*\z"))
            { Send(Request("ACK", Target, 2, remoteTo, "", "", Branch())); throw new InvalidOperationException("PBX contact must remain local"); }
            remoteContact = candidateContact;
            opened = true; invitePending = false; metrics.Authenticated = true;
            lastInviteOk = Request("ACK", remoteContact, 2, remoteTo, "", "", Branch()); Send(lastInviteOk);
            SetMedia(answer.Body); metrics.Answered = true;
            if(deferGreeting){GreetingChannel=answer.Header("X-Office-Modem-Channel");if(!Regex.IsMatch(GreetingChannel,@"\A[a-fA-F0-9]{8}(?:-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}\z"))throw new InvalidOperationException("PBX local greeting support is missing; stop and restart the lab");}
        }
        public void ReceiveAudio(byte[] samples)
        {
            if (samples == null || samples.Length > 8192 || outgoing.Count + samples.Length > 8000) throw new InvalidOperationException("modem_to_pbx_audio_backlog");
            foreach (byte sample in samples) outgoing.Enqueue(sample);
        }
        private void Pump()
        {
            for (int n = 0; n < 32 && signal.Available > 0; n++)
            {
                var message = Read(); if (message == null) continue;
                if (message.First.StartsWith("OPTIONS ")) { Reply(message, 200, "OK", ""); continue; }
                if (message.Header("Call-ID") != callId) { if (message.First.StartsWith("INVITE ")) Reply(message, 486, "Busy Here", ""); continue; }
                if (message.First.StartsWith("BYE ")) { Reply(message, 200, "OK", ""); metrics.RemoteHangup = true; Ended = true; }
                else if (message.First.StartsWith("INVITE ")) { string cseq=message.Header("CSeq");if(cseq!=remoteInviteSequence){if(message.Body.Length>0)SetMedia(message.Body);remoteInviteSequence=cseq;} Reply(message, 200, "OK", Sdp()); }
                else if (message.Code == 200 && message.Header("CSeq") == "2 INVITE" && lastInviteOk != null) Send(lastInviteOk);
                else if (message.First.StartsWith("CANCEL ")) Reply(message, 481, "Call Does Not Exist", "");
            }
            for (int n = 0; n < 128 && media.Available > 0; n++)
            {
                IPEndPoint source = null; byte[] packet;
                try { packet = media.Receive(ref source); } catch (SocketException error) { if (error.SocketErrorCode == SocketError.ConnectionReset) { metrics.RejectedPackets++; continue; } throw; }
                if (remoteMedia == null || !source.Equals(remoteMedia) || packet.Length < 12 || packet.Length > 2048 || packet[0] >> 6 != 2 || (packet[1] & 127) != 0) { metrics.RejectedPackets++; continue; }
                int header = 12 + (packet[0] & 15) * 4, end = packet.Length;
                if ((packet[0] & 16) != 0) { if (header + 4 > end) { metrics.RejectedPackets++; continue; } header += 4 + ((packet[header+2] << 8) | packet[header+3]) * 4; }
                if ((packet[0] & 32) != 0) { int padding = packet[end-1]; if (padding < 1) { metrics.RejectedPackets++; continue; } end -= padding; }
                if (header >= end || end-header > 480) { metrics.RejectedPackets++; continue; }
                var payload=new byte[end-header];Array.Copy(packet,header,payload,0,payload.Length);
                ushort remoteSequence=(ushort)((packet[2]<<8)|packet[3]);
                uint remoteTimestamp=((uint)packet[4]<<24)|((uint)packet[5]<<16)|((uint)packet[6]<<8)|packet[7];
                uint remoteSource=((uint)packet[8]<<24)|((uint)packet[9]<<16)|((uint)packet[10]<<8)|packet[11];
                if(playout.Add(remoteSequence,remoteTimestamp,remoteSource,payload))foreach(byte sample in payload)metrics.PbxReceivedAudio.Observe(sample);
                metrics.ReceivedPackets++;
            }
        }
        public byte[] TransmitAudio(int count) { return TransmitAudio(count,true); }
        public void ServiceParkedDialog() { if(!deferGreeting)throw new InvalidOperationException("Not a deferred greeting dialog");TransmitAudio(160,false); }
        private byte[] TransmitAudio(int count,bool render)
        {
            if (!opened || disposed || count != 160) throw new InvalidOperationException("Invalid SIP audio state");
            Pump();
            long waiting=metrics.Playout.WaitingSamples,silent=metrics.Playout.ConcealmentSilentSamples;
            byte[] result;
            if(render){result=playout.Read();metrics.ModemPlaybackSilenceFillSamples+=metrics.Playout.WaitingSamples-waiting+metrics.Playout.ConcealmentSilentSamples-silent;foreach(byte sample in result)metrics.ModemPlaybackAudio.Observe(sample);}
            else{playout.Reset();result=new byte[count];}
            if (Ended) return result;
            var packet = new byte[172]; packet[0] = 128; packet[1] = 0;
            packet[2] = (byte)(sequence >> 8); packet[3] = (byte)sequence; sequence++;
            for (int i = 0; i < 4; i++) { packet[4+i] = (byte)(timestamp >> (24-8*i)); packet[8+i] = (byte)(ssrc >> (24-8*i)); }
            timestamp += 160;
            for (int i = 0; i < 160; i++) { if (outgoing.Count > 0) packet[12+i] = outgoing.Dequeue(); else { packet[12+i] = 255; metrics.SilenceSamples++; } }
            media.Send(packet, packet.Length, remoteMedia); metrics.SentPackets++;
            return result;
        }
        public void Dispose()
        {
            if (disposed) return;
            try
            {
                if (opened && !Ended)
                {
                    int cseq = ++localSequence; byte[] bye = Request("BYE", remoteContact, cseq, remoteTo, "", "", Branch()); Send(bye);
                    var clock = Stopwatch.StartNew();
                    while (clock.ElapsedMilliseconds < 1000) { var response = Read(); if (response != null && response.Header("Call-ID") == callId && response.First.StartsWith("BYE ")) { Reply(response, 200, "OK", ""); metrics.RemoteHangup = true; } if (response != null && response.Header("Call-ID") == callId && response.Code == 200 && response.Header("CSeq") == cseq + " BYE") { metrics.LocalHangupAcknowledged = true; break; } Thread.Sleep(5); }
                }
                else if (invitePending) Send(Request("CANCEL", Target, inviteSequence, InitialTo, "", "", inviteBranch));
            }
            finally { disposed = true; Ended = true; signal.Close(); media.Close(); }
        }
    }
}
