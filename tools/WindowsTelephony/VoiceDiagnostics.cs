using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Ports;
using System.Linq;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;

namespace OfficeAutomation.Telephony.Diagnostics
{
    public interface IVoiceAudioEndpoint : IDisposable
    {
        void Open(CancellationToken cancellation);
        void ReceiveAudio(byte[] samples);
        byte[] TransmitAudio(int count);
        bool Ended { get; }
    }
    public interface ITransmitGreetingEndpoint : IVoiceAudioEndpoint
    {
        bool NeedsDuplex { get; }
        void BeginDuplex();
    }
    public interface IGreetingModeComparison : ITransmitGreetingEndpoint { }
    public interface IVoicePort : IDisposable
    {
        void Open();
        int Available { get; }
        int Read(byte[] buffer, int offset, int count);
        void Write(byte[] buffer, int offset, int count);
        void ReleaseDtr();
    }
    public interface IVoiceFlowControlPort
    {
        void SetFlowControl(string setting);
    }
    public sealed class SerialVoicePort : IVoicePort, IVoiceFlowControlPort
    {
        private readonly SerialPort port;
        public SerialVoicePort(string name) : this(name, 115200) { }
        public SerialVoicePort(string name, int baudRate)
        {
            if (!Regex.IsMatch(name, @"^COM[1-9][0-9]{0,4}$")) throw new ArgumentException("Invalid COM port");
            if (baudRate != 115200 && baudRate != 230400) throw new ArgumentOutOfRangeException("baudRate");
            port = new SerialPort(name, baudRate, Parity.None, 8, StopBits.One);
            port.ReadTimeout = 100; port.WriteTimeout = 1000;
            port.DtrEnable = true; port.RtsEnable = true; port.Handshake = Handshake.None;
        }
        public void Open() { port.Open(); }
        public void SetFlowControl(string setting)
        {
            if (setting != "0,0" && setting != "2,2") throw new InvalidOperationException("unsupported_serial_flow_control");
            port.Handshake = setting == "2,2" ? Handshake.RequestToSend : Handshake.None;
            if (setting == "0,0") port.RtsEnable = true;
        }
        public int Available { get { return port.BytesToRead; } }
        public int Read(byte[] b, int o, int n) { return port.Read(b, o, n); }
        public void Write(byte[] b, int o, int n) { port.Write(b, o, n); }
        public void ReleaseDtr() { if (port.IsOpen) port.DtrEnable = false; }
        public void Dispose() { port.Dispose(); }
    }
    public static class VoiceCodec
    {
        public static short DecodeMuLaw(byte value)
        {
            int u = (~value) & 255;
            int sample = (((u & 15) << 3) + 132) << ((u >> 4) & 7);
            sample -= 132;
            return (short)((u & 128) != 0 ? -sample : sample);
        }
        public static byte EncodeMuLaw(short value)
        {
            int sample = value, sign = 0;
            if (sample < 0) { sign = 128; sample = -sample; }
            sample = Math.Min(sample, 32635) + 132;
            int exponent = 7;
            for (int mask = 0x4000; exponent > 0 && (sample & mask) == 0; exponent--, mask >>= 1) { }
            return (byte)(~(sign | (exponent << 4) | ((sample >> (exponent + 3)) & 15)));
        }
        public static byte[] Pcm16FromMuLaw(byte[] data)
        {
            var result = new byte[data.Length * 2];
            for (int i = 0; i < data.Length; i++) { short value = DecodeMuLaw(data[i]); result[i*2] = (byte)(value & 255); result[i*2+1] = (byte)((value >> 8) & 255); }
            return result;
        }
        public static int FindPcm16(string reply)
        {
            foreach (string line in Regex.Split(reply, @"[\r\n]+"))
            {
                Match match = Regex.Match(line, "^\\s*(?:\\+VSM:\\s*)?([0-9]{1,3}),\\s*\"Signed PCM\",\\s*16,\\s*0,\\s*8000(?:\\s*,|\\s*$)", RegexOptions.IgnoreCase);
                if (match.Success) return int.Parse(match.Groups[1].Value, CultureInfo.InvariantCulture);
            }
            return -1;
        }
        public static byte[] Escape(byte[] data)
        {
            var result = new List<byte>(data.Length + 16);
            foreach (byte b in data) { result.Add(b); if (b == 16) result.Add(16); }
            return result.ToArray();
        }
        public static void SaveWave(string path, byte[] muLaw)
        {
            using (var writer = new BinaryWriter(File.Create(path)))
            {
                writer.Write(Encoding.ASCII.GetBytes("RIFF")); writer.Write(36 + muLaw.Length * 2);
                writer.Write(Encoding.ASCII.GetBytes("WAVEfmt ")); writer.Write(16);
                writer.Write((short)1); writer.Write((short)1); writer.Write(8000); writer.Write(16000);
                writer.Write((short)2); writer.Write((short)16);
                writer.Write(Encoding.ASCII.GetBytes("data")); writer.Write(muLaw.Length * 2);
                foreach (byte b in muLaw) writer.Write(DecodeMuLaw(b));
            }
        }
        public static int FindMuLaw(string reply)
        {
            foreach (string line in Regex.Split(reply, @"[\r\n]+"))
            {
                Match match = Regex.Match(line, "^\\s*(?:\\+VSM:\\s*)?([0-9]{1,3}),\\s*\"([^\"]+)\",(.*)$", RegexOptions.IgnoreCase);
                if (!match.Success) continue;
                string codec = match.Groups[2].Value.ToLowerInvariant();
                if (!(codec.Contains("g.711u") || codec.Contains("u-law") || codec.Contains("mu-law") || codec.Contains("mulaw"))) continue;
                if (!Regex.IsMatch(match.Groups[3].Value, @"(?:^|[^0-9])8000(?:[^0-9]|$)")) continue;
                return int.Parse(match.Groups[1].Value, CultureInfo.InvariantCulture);
            }
            return -1;
        }
    }
    // Recognize repeated trailing data only from stream evidence, never from a modem name.
    // Detection requires 80 complete frames with exact trailing matches and nonperiodic payload.
    public sealed class VoiceReceiveFraming
    {
        private readonly Action<byte> emit;
        private readonly List<byte> probe = new List<byte>();
        private readonly byte[] frame = new byte[60];
        private int count;
        private bool failed;
        public string Mode { get; private set; }
        public bool Valid { get { return !failed; } }
        public VoiceReceiveFraming(Action<byte> emit) { this.emit = emit; Mode = "detecting"; }
        public void Feed(byte value)
        {
            if (Mode == "linear") { emit(value); return; }
            if (Mode == "detecting")
            {
                probe.Add(value);
                if (probe.Count < 4800) return;
                int trailingMatches = 0, payloadMatches = 0;
                for (int start = 0; start < probe.Count; start += 60)
                {
                    for (int i = 48; i < 60; i++) if (probe[start + i] == probe[start + i - 10]) trailingMatches++;
                    for (int i = 10; i < 48; i++) if (probe[start + i] == probe[start + i - 10]) payloadMatches++;
                }
                Mode = trailingMatches == 80 * 12 && payloadMatches < 80 * 38 / 2 ? "repeat_60_48_lag10" : "linear";
                if (Mode == "linear") foreach (byte sample in probe) emit(sample);
                else for (int start = 0; start < probe.Count; start += 60) for (int i = 0; i < 48; i++) emit(probe[start + i]);
                probe.Clear(); return;
            }
            frame[count++] = value;
            if (count != 60) return;
            for (int i = 48; i < 60; i++) if (frame[i] != frame[i - 10]) { failed = true; throw new InvalidDataException("voice_receive_framing_changed"); }
            for (int i = 0; i < 48; i++) emit(frame[i]);
            count = 0;
        }
        public void Finish()
        {
            if (failed) return;
            if (Mode == "detecting") { foreach (byte sample in probe) emit(sample); probe.Clear(); Mode = "linear"; }
            if (Mode == "repeat_60_48_lag10")
            {
                // On a cut-off frame, validate any available repeated tail before emitting its payload.
                for (int i = 48; i < count; i++) if (frame[i] != frame[i - 10]) { failed = true; return; }
                for (int i = 0; i < Math.Min(count, 48); i++) emit(frame[i]);
                count = 0;
            }
        }
    }
    public sealed class VoiceDecoder
    {
        private bool dle, finished;
        private readonly bool pcm16;
        private int lowByte = -1;
        public bool IncompletePcmSample { get { return finished && pcm16 && lowByte >= 0; } }
        public readonly List<byte> Audio = new List<byte>();
        private readonly VoiceReceiveFraming framing;
        public int RawSampleCount { get; private set; }
        public int DecodedSampleCount { get; private set; }
        public bool FramingValid { get { return (framing == null || framing.Valid) && !IncompletePcmSample; } }
        public string ReceiveFraming { get { return framing == null ? "linear" : framing.Mode; } }
        public VoiceDecoder() : this(false) { }
        public VoiceDecoder(bool detectFraming) : this(detectFraming, false) { }
        public VoiceDecoder(bool detectFraming, bool pcm16) { this.pcm16 = pcm16; if (detectFraming) framing = new VoiceReceiveFraming(EmitAudio); }
        private void EmitAudio(byte value) { DecodedSampleCount++; Audio.Add(value); }
        private void AddAudio(byte value)
        {
            if (pcm16)
            {
                if (lowByte < 0) { lowByte = value; return; }
                value = VoiceCodec.EncodeMuLaw((short)(lowByte | (value << 8))); lowByte = -1;
            }
            RawSampleCount++; if (framing == null) EmitAudio(value); else framing.Feed(value);
        }
        public byte[] DrainAudio() { var data = Audio.ToArray(); Audio.Clear(); return data; }
        public void Finish() { finished = true; if (framing != null) framing.Finish(); }
        public readonly List<string> Events = new List<string>();
        public bool Ended { get; private set; }
        public void Feed(byte[] data, int count)
        {
            for (int i = 0; i < count; i++)
            {
                byte b = data[i];
                if (dle)
                {
                    dle = false;
                    if (b == 16) AddAudio(16);
                    else if (b == 3) { Events.Add("stream_end"); Ended = true; return; }
                    else if (b == (byte)'b') Events.Add("busy_tone");
                    else if (b == (byte)'h') Events.Add("local_on_hook");
                    else if (b == (byte)'s') Events.Add("silence");
                    else Events.Add("dle_" + b.ToString("X2"));
                }
                else if (b == 16) dle = true;
                else AddAudio(b);
                if (Audio.Count > 240000) throw new InvalidDataException("Audio size limit exceeded");
            }
        }
    }
    public sealed class RingDetector
    {
        private bool dle;
        private readonly StringBuilder line = new StringBuilder();
        public bool Detected { get; private set; }
        public string Signal { get; private set; }
        public void Feed(byte value)
        {
            if (Detected) return;
            if (dle)
            {
                dle = false;
                if (value == (byte)'R') { Detected = true; Signal = "dle_ring"; }
                return; // Escaped DLE and other voice events are not ring indications.
            }
            if (value == 16) { dle = true; line.Clear(); line.Append('!'); return; }
            if (value == 13 || value == 10)
            {
                if (Regex.IsMatch(line.ToString(), @"^[ \t]*RING[ \t]*$", RegexOptions.IgnoreCase))
                { Detected = true; Signal = "text_ring"; }
                line.Clear();
            }
            else
            {
                line.Append((char)value);
                if (line.Length > 256) { line.Clear(); line.Append('!'); }
            }
        }
    }
    public static class VoiceTransmitLevel
    {
        static bool Ok(string reply) { return Regex.IsMatch(reply, @"(?:^|[\r\n])OK[ \t]*(?:$|[\r\n])") && !Regex.IsMatch(reply, @"(?:^|[\r\n])(?:ERROR|NO CARRIER)[ \t]*(?:$|[\r\n])"); }
        public static string Current(string reply)
        {
            if (!Ok(reply)) return "";
            string value = "";
            foreach (string raw in Regex.Split(reply, @"[\r\n]+"))
            {
                string line = Regex.Replace(raw.Trim(), @"^\+VGT[ \t]*:[ \t]*", "", RegexOptions.IgnoreCase);
                if (!Regex.IsMatch(line, @"^[0-9]{1,3}$")) continue;
                int number = int.Parse(line, CultureInfo.InvariantCulture);
                if (number > 255 || (value.Length > 0 && value != number.ToString(CultureInfo.InvariantCulture))) return "";
                value = number.ToString(CultureInfo.InvariantCulture);
            }
            return value;
        }
        public static bool Supports(string reply, int wanted)
        {
            if (wanted < 0 || wanted > 255 || !Ok(reply)) return false;
            foreach (string raw in Regex.Split(reply, @"[\r\n]+"))
            {
                string line = Regex.Replace(raw.Trim(), @"^\+VGT[ \t]*:[ \t]*", "", RegexOptions.IgnoreCase);
                if (!Regex.IsMatch(line, @"^\(?[0-9]{1,3}(?:[ \t]*-[ \t]*[0-9]{1,3})?(?:[ \t]*,[ \t]*[0-9]{1,3}(?:[ \t]*-[ \t]*[0-9]{1,3})?)*\)?$")) continue;
                if (line.StartsWith("(") != line.EndsWith(")")) return false;
                bool found = false;
                foreach (string item in line.Trim('(', ')').Split(','))
                {
                    string[] bounds = item.Split('-');
                    int low = int.Parse(bounds[0].Trim(), CultureInfo.InvariantCulture);
                    int high = bounds.Length == 1 ? low : int.Parse(bounds[1].Trim(), CultureInfo.InvariantCulture);
                    if (low > high || high > 255) return false;
                    if (wanted >= low && wanted <= high) found = true;
                }
                return found;
            }
            return false;
        }
    }
    public static class VoiceSettings
    {
        // Some drivers include codec metadata in VSM?; only codec/rate/sensitivity/expansion are settable.
        public static string ParseFormat(string reply)
        {
            var lines = new List<string>();
            foreach (string raw in Regex.Split(reply, @"[\r\n]+"))
            {
                Match described = Regex.Match(raw.Trim(),
                    @"^(?:\+VSM[ \t]*[:=][ \t]*)?([0-9]{1,5})[ \t]*,[ \t]*""[^""\r\n]{1,80}""[ \t]*,[ \t]*([0-9]{1,5})[ \t]*,[ \t]*([0-9]{1,5})[ \t]*,[ \t]*([0-9]{1,5})[ \t]*,[ \t]*([0-9]{1,5})[ \t]*,[ \t]*([0-9]{1,5})$",
                    RegexOptions.IgnoreCase);
                if (!described.Success) { lines.Add(raw); continue; }
                for (int i = 1; i <= 6; i++)
                    if (int.Parse(described.Groups[i].Value, CultureInfo.InvariantCulture) > 65535) return "";
                lines.Add(described.Groups[1].Value + "," + described.Groups[4].Value + ","
                    + described.Groups[5].Value + "," + described.Groups[6].Value);
            }
            return Parse(string.Join("\r\n", lines.ToArray()), "VSM", 2, 4);
        }

        // Accept only complete numeric current-value replies, never a codec inventory or command echo.
        public static string Parse(string reply, string name, int minimum, int maximum)
        {
            if (!Regex.IsMatch(reply, @"(?:^|[\r\n])OK[ \t]*(?:$|[\r\n])")
                || Regex.IsMatch(reply, @"(?:^|[\r\n])(?:ERROR|NO CARRIER|BUSY)[ \t]*(?:$|[\r\n])")) return "";
            string snapshot = "";
            foreach (string raw in Regex.Split(reply, @"[\r\n]+"))
            {
                string line = raw.Trim();
                line = Regex.Replace(line, @"^\+" + Regex.Escape(name) + @"[ \t]*[:=][ \t]*", "", RegexOptions.IgnoreCase);
                if (!Regex.IsMatch(line, @"^[0-9]{1,5}(?:[ \t]*,[ \t]*[0-9]{1,5})+$")) continue;
                string[] values = line.Split(',');
                if (values.Length < minimum || values.Length > maximum) return "";
                if (values.Any(v => int.Parse(v.Trim(), CultureInfo.InvariantCulture) > 65535)) return "";
                string normalized = string.Join(",", values.Select(v => v.Trim()).ToArray());
                if (snapshot.Length > 0 && snapshot != normalized) return "";
                snapshot = normalized;
            }
            return snapshot;
        }
    }
    public sealed class CommandTrace
    {
        public string Command = "";
        public string Response = "";
        public string Error = "";
        public long DurationMilliseconds;
    }
    public sealed class VoiceSampleWindow
    {
        public int Milliseconds;
        public int TransmittedSamples;
        public int ReceivedSamples;
        public int RawReceivedSamples;
    }
    public static class VoiceTiming
    {
        public static double Rate(List<VoiceSampleWindow> windows, bool received)
        {
            var usable = windows.Where(w => w.Milliseconds >= 2000).ToArray();
            if (usable.Length < 2) return 0;
            var first = usable[0]; var last = usable[usable.Length - 1];
            int duration = last.Milliseconds - first.Milliseconds;
            if (duration < 3000) return 0;
            return 1000.0 * ((received ? last.ReceivedSamples : last.TransmittedSamples) - (received ? first.ReceivedSamples : first.TransmittedSamples)) / duration;
        }
        public static bool Ready(List<VoiceSampleWindow> windows)
        {
            double rx = Rate(windows, true), tx = Rate(windows, false);
            return rx >= 7600 && rx <= 8400 && tx >= 7600 && tx <= 8400;
        }
    }
    public sealed class VoiceResult
    {
        public string DiagnosticVersion = "2026.11.23.6";
        public bool TransmitOnlyStarted, SwitchedToDuplex;
        public int ModeTransitionMilliseconds;
        public string EffectiveFlowControl = "";
        public bool HostFlowControlSynchronized;
        public int MaxSerialAvailabilityMilliseconds, MaxSerialReadMilliseconds, MaxSerialWriteMilliseconds;
        public int MaxDecodeMilliseconds, MaxEncodeMilliseconds, MaxEndpointMilliseconds, MaxTransmitLagMilliseconds;
        public string ModemSampleFormat = "g711u_8000";
        public int ModemCodec;
        public int ModemTransmitBytes;
        public string OriginalTransmitLevel = "";
        public int? RequestedTransmitLevel;
        public bool TransmitLevelApplied, TransmitLevelRestored;
        public string FailureStage = "";
        public string FailureError = "";
        public string CleanupError = "";
        public string CurrentFormatReply = "";
        public string CurrentFlowReply = "";
        public string OriginalFormat = "";
        public string OriginalFlow = "";
        public readonly List<CommandTrace> Trace = new List<CommandTrace>();
        public string Status = "not_started";
        public string ErrorCode = "";
        public bool RingDetected;
        public string RingSignal = "";
        public string RingInputHex = "";
        public int RingBytesObserved;
        public bool RingInputTruncated;
        public bool Answered;
        public bool DuplexStarted;
        public bool BridgeStarted;
        public string EndReason = "";
        public bool CleanupConfirmed;
        public bool Cancelled;
        public int TransmittedSamples;
        public int ReceivedSamples;
        public int RawReceivedSamples;
        public int RemovedRepeatedSamples;
        public string ReceiveFraming = "linear";
        public int ReceivedPeak;
        public double ReceivedRms;
        public int StreamMilliseconds, DuplexStartedAtMilliseconds;
        public readonly List<VoiceSampleWindow> SampleWindows = new List<VoiceSampleWindow>();
        private List<VoiceSampleWindow> TimingWindows { get { return DuplexStartedAtMilliseconds == 0 ? SampleWindows : SampleWindows.Where(w => w.Milliseconds >= DuplexStartedAtMilliseconds).Select(w => new VoiceSampleWindow { Milliseconds=w.Milliseconds-DuplexStartedAtMilliseconds, TransmittedSamples=w.TransmittedSamples, ReceivedSamples=w.ReceivedSamples, RawReceivedSamples=w.RawReceivedSamples }).ToList(); } }
        public double ReceiveRateAfterWarmup { get { return VoiceTiming.Rate(TimingWindows, true); } }
        public double TransmitRateAfterWarmup { get { return VoiceTiming.Rate(TimingWindows, false); } }
        public bool BridgeTimingReady { get { return VoiceTiming.Ready(TimingWindows); } }
        public string RecordingFile = "";
        public string[] Events = new string[0];
        public string Identity = "";
        public string Formats = "";
        public bool RemoteToneConfirmed;
        public bool RecordingSpeechConfirmed;
        public bool CompatibleForNextStep
        {
            get
            {
                return Status == "captured" && DuplexStarted && CleanupConfirmed
                    && TransmittedSamples >= 24000 && ReceivedSamples >= 24000
                    && ReceivedRms > 0.003 && RemoteToneConfirmed && RecordingSpeechConfirmed;
            }
        }
    }
    public sealed class VoiceSession
    {
        private readonly IVoicePort port;
        private readonly IVoiceAudioEndpoint endpoint;
        private readonly int? transmitLevel;
        private readonly bool pcm16;
        private bool changedTransmitLevel;
        private readonly string folder;
        private readonly int ringTimeout;
        private readonly int captureDuration;
        private readonly CancellationTokenSource stop = new CancellationTokenSource();
        private Task worker;
        private readonly VoiceResult result = new VoiceResult();
        private volatile string progress = "ready";
        private string stage = "open_port";
        private bool opened, changedMode, duplex, transmitOnly, answerAttempted;
        private string originalClass = "", originalFormat = "", originalFlow = "";
        public string Progress { get { return progress; } }
        public bool Started { get { return worker != null; } }
        public bool Completed { get { return worker != null && worker.IsCompleted; } }
        public VoiceResult Result { get { if (!Completed) throw new InvalidOperationException("Test not finished"); return result; } }
        public string RecordingPath { get; private set; }
        public VoiceSession(string name, string folder) : this(new SerialVoicePort(name), folder, 60000, 12000) { }
        public VoiceSession(IVoicePort port, string folder, int ringTimeout, int captureDuration) : this(port, folder, ringTimeout, captureDuration, null) { }
        public VoiceSession(IVoicePort port, string folder, int ringTimeout, int captureDuration, IVoiceAudioEndpoint endpoint) : this(port, folder, ringTimeout, captureDuration, endpoint, null) { }
        public VoiceSession(IVoicePort port, string folder, int ringTimeout, int captureDuration, IVoiceAudioEndpoint endpoint, int? transmitLevel) : this(port, folder, ringTimeout, captureDuration, endpoint, transmitLevel, false) { }
        public VoiceSession(IVoicePort port, string folder, int ringTimeout, int captureDuration, IVoiceAudioEndpoint endpoint, int? transmitLevel, bool pcm16)
        {
            this.pcm16 = pcm16; result.ModemSampleFormat = pcm16 ? "pcm16le_8000" : "g711u_8000";
            if (transmitLevel.HasValue && (transmitLevel.Value < 0 || transmitLevel.Value > 255)) throw new ArgumentOutOfRangeException("transmitLevel");
            this.transmitLevel = transmitLevel; result.RequestedTransmitLevel = transmitLevel;
            int maximum = endpoint == null ? 30000 : 120000;
            if (ringTimeout < 100 || ringTimeout > 60000 || captureDuration < 100 || captureDuration > maximum) throw new ArgumentOutOfRangeException();
            this.port = port; this.folder = folder; this.ringTimeout = ringTimeout; this.captureDuration = captureDuration; this.endpoint = endpoint;
        }
        public void Start()
        {
            if (worker != null) throw new InvalidOperationException("Test already started");
            worker = Task.Factory.StartNew(Run, CancellationToken.None, TaskCreationOptions.LongRunning, TaskScheduler.Default);
        }
        public void Cancel() { stop.Cancel(); }
        private void Raw(byte[] bytes) { port.Write(bytes, 0, bytes.Length); }
        private string Reply(int timeout, bool cancellable, CommandTrace trace)
        {
            var watch = Stopwatch.StartNew(); var text = new StringBuilder();
            try
            {
                while (watch.ElapsedMilliseconds < timeout)
                {
                    if (cancellable) stop.Token.ThrowIfCancellationRequested();
                    if (port.Available > 0)
                    {
                        byte[] b = new byte[1];
                        port.Read(b, 0, 1); text.Append((char)b[0]);
                        if (text.Length > 8192) throw new InvalidDataException("Reply size limit exceeded");
                        if (Regex.IsMatch(text.ToString(), @"(?:^|[\r\n])(OK|ERROR|CONNECT|NO CARRIER|BUSY)\r?\n$")) return text.ToString();
                    }
                    else Thread.Sleep(5);
                }
                throw new TimeoutException("modem_response_timeout");
            }
            finally
            {
                // Drain voice-stop replies without copying residual call audio into the text report.
                trace.Response = trace.Command == "<DLE>^" || trace.Command == "<DLE><ETX>"
                    ? Regex.Match(text.ToString(), @"(?:^|[\r\n])(OK|ERROR|NO CARRIER|BUSY)[ \t]*(?:$|[\r\n])").Value.Trim()
                    : text.ToString();
            }
        }
        private string Exchange(string command, byte[] bytes, bool connect, bool cancellable)
        {
            if (result.Trace.Count >= 64) throw new InvalidDataException("command_trace_limit");
            var trace = new CommandTrace { Command = command };
            result.Trace.Add(trace);
            var watch = Stopwatch.StartNew();
            try
            {
                Raw(bytes);
                string reply = Reply(2500, cancellable, trace);
                string expected = connect ? "CONNECT" : "OK";
                if (!Regex.IsMatch(reply, @"(?:^|[\r\n])" + expected + @"[ \t]*(?:$|[\r\n])")) throw new InvalidOperationException("command_rejected:" + command);
                return reply;
            }
            catch (Exception exception) { trace.Error = exception.Message; throw; }
            finally { trace.DurationMilliseconds = watch.ElapsedMilliseconds; }
        }
        private string Command(string command, bool connect, bool cancellable)
        {
            return Exchange(command, Encoding.ASCII.GetBytes(command + "\r"), connect, cancellable);
        }
        private void ReadRingDiagnostics()
        {
            // Read-only queries: unsupported settings must not prevent an otherwise valid test.
            foreach (string query in new[] { "AT+VEM?", "AT+VDR?", "AT+VLS?" })
            {
                try { Command(query, false, true); }
                catch (InvalidOperationException) { }
                catch (TimeoutException) { }
            }
        }
        private static int PhaseMilliseconds(long start) { return (int)Math.Ceiling((Stopwatch.GetTimestamp() - start) * 1000.0 / Stopwatch.Frequency); }
        private void Run()
        {
            Stopwatch streamClock = null;
            VoiceDecoder decoder = new VoiceDecoder(true, pcm16);
            var ringInput = new List<byte>();
            try
            {
                progress = "preparing"; port.Open(); opened = true;
                Command("AT", false, true);
                result.Identity = Command("ATI", false, true);
                stage = "read_original_class";
                string classes = Command("AT+FCLASS?", false, true);
                Match original = Regex.Match(classes, @"(?:^|[\r\n])\s*(?:\+FCLASS:\s*)?([0-9]+(?:\.[0-9]+)?)\s*(?:$|[\r\n])");
                if (!original.Success) throw new InvalidOperationException("cannot_preserve_original_mode");
                originalClass = original.Groups[1].Value;
                stage = "enter_voice_class";
                changedMode = true; Command("AT+FCLASS=8", false, true);
                stage = "read_supported_formats";
                result.Formats = Command("AT+VSM=?", false, true);
                int codec = pcm16 ? VoiceCodec.FindPcm16(result.Formats) : VoiceCodec.FindMuLaw(result.Formats);
                if (codec < 0) throw new InvalidOperationException(pcm16 ? "no_supported_8khz_pcm16" : "no_supported_8khz_mulaw");
                result.ModemCodec = codec;
                stage = "read_current_format";
                result.CurrentFormatReply = Command("AT+VSM?", false, true);
                originalFormat = VoiceSettings.ParseFormat(result.CurrentFormatReply);
                result.OriginalFormat = originalFormat;
                if (originalFormat.Length == 0) throw new InvalidOperationException("cannot_preserve_original_format");
                stage = "read_flow_control";
                result.CurrentFlowReply = Command("AT+IFC?", false, true);
                originalFlow = VoiceSettings.Parse(result.CurrentFlowReply, "IFC", 2, 2);
                result.OriginalFlow = originalFlow;
                if (originalFlow.Length == 0) throw new InvalidOperationException("cannot_preserve_flow_control");
                stage = "inspect_ring_settings";
                ReadRingDiagnostics();
                if (transmitLevel.HasValue)
                {
                    stage = "read_transmit_level";
                    result.OriginalTransmitLevel = VoiceTransmitLevel.Current(Command("AT+VGT?", false, true));
                    if (result.OriginalTransmitLevel.Length == 0) throw new InvalidOperationException("cannot_preserve_transmit_level");
                    string supported = Command("AT+VGT=?", false, true);
                    if (!VoiceTransmitLevel.Supports(supported, transmitLevel.Value)
                        || !VoiceTransmitLevel.Supports(supported, int.Parse(result.OriginalTransmitLevel, CultureInfo.InvariantCulture)))
                        throw new InvalidOperationException("unsupported_transmit_level");
                    stage = "set_transmit_level"; changedTransmitLevel = true;
                    Command("AT+VGT=" + transmitLevel.Value.ToString(CultureInfo.InvariantCulture), false, true);
                    // Read back after codec setup before accepting the trial.
                }
                stage = "configure_audio";
                var flowPort = port as IVoiceFlowControlPort;
                if (flowPort != null)
                {
                    stage = "synchronize_flow_control";
                    if (originalFlow != "0,0" && originalFlow != "2,2") throw new InvalidOperationException("unsupported_serial_flow_control");
                    // Acknowledge the modem setting before enabling RTS/CTS on the host.
                    Command("AT+IFC=" + originalFlow, false, true);
                    flowPort.SetFlowControl(originalFlow);
                    result.EffectiveFlowControl = originalFlow;
                    result.HostFlowControlSynchronized = true;
                }
                else throw new InvalidOperationException("serial_flow_control_adapter_missing");
                stage = "configure_audio";
                Command("AT+VSM=" + codec.ToString(CultureInfo.InvariantCulture) + ",8000", false, true);
                if (transmitLevel.HasValue)
                {
                    stage = "verify_transmit_level";
                    if (VoiceTransmitLevel.Current(Command("AT+VGT?", false, true)) != transmitLevel.Value.ToString(CultureInfo.InvariantCulture))
                        throw new InvalidOperationException("transmit_level_not_confirmed");
                    result.TransmitLevelApplied = true;
                }
                if (pcm16)
                {
                    stage = "verify_modem_sample_format";
                    string actual = VoiceSettings.ParseFormat(Command("AT+VSM?", false, true));
                    if (!Regex.IsMatch(actual, "^" + codec.ToString(CultureInfo.InvariantCulture) + ",8000(?:,|$)"))
                        throw new InvalidOperationException("pcm16_format_not_confirmed");
                }
                progress = "waiting_ring"; stage = "wait_for_incoming_call";
                var ringWatch = Stopwatch.StartNew(); var ring = new RingDetector();
                while (ringWatch.ElapsedMilliseconds < ringTimeout)
                {
                    stop.Token.ThrowIfCancellationRequested();
                    if (port.Available > 0)
                    {
                        byte[] b = new byte[1]; port.Read(b, 0, 1);
                        result.RingBytesObserved++;
                        if (ringInput.Count < 4096) ringInput.Add(b[0]); else result.RingInputTruncated = true;
                        ring.Feed(b[0]);
                        if (ring.Detected) { result.RingDetected = true; result.RingSignal = ring.Signal; break; }
                    }
                    else Thread.Sleep(10);
                }
                if (!result.RingDetected) throw new TimeoutException("incoming_call_timeout");
                stop.Token.ThrowIfCancellationRequested();
                if (endpoint != null)
                {
                    stage = "connect_pbx"; progress = "connecting_pbx";
                    endpoint.Open(stop.Token); result.BridgeStarted = true;
                }
                stage = "answer_incoming_call";
                progress = "answering"; answerAttempted = true; Command("AT+VLS=1", false, true); result.Answered = true;
                var comparison = endpoint as ITransmitGreetingEndpoint;
                if (comparison != null)
                {
                    stage = "start_transmit_only";
                    Command("AT+VTX", true, true); transmitOnly = true; result.TransmitOnlyStarted = true;
                }
                else { stage = "start_duplex"; Command("AT+VTR", true, true); duplex = true; result.DuplexStarted = true; }
                progress = "streaming"; stage = "stream_audio";
                var clock = streamClock = Stopwatch.StartNew(); int frame = 0, nextWindow = 1000; long controlPause = 0;
                while (clock.ElapsedMilliseconds < captureDuration && !decoder.Ended && (endpoint == null || !endpoint.Ended))
                {
                    stop.Token.ThrowIfCancellationRequested();
                    if (comparison != null && transmitOnly && comparison.NeedsDuplex)
                    {
                        progress = "switching_playback_mode"; stage = "switch_to_duplex";
                        long transition = clock.ElapsedMilliseconds;
                        Exchange("<DLE><ETX>", new byte[] { 16, 3 }, false, false); transmitOnly = false;
                        stop.Token.ThrowIfCancellationRequested();
                        Command("AT+VTR", true, true); duplex = true; result.DuplexStarted = true;
                        comparison.BeginDuplex(); result.SwitchedToDuplex = true;
                        result.ModeTransitionMilliseconds = (int)(clock.ElapsedMilliseconds - transition);
                        controlPause += result.ModeTransitionMilliseconds;
                        result.DuplexStartedAtMilliseconds=(int)clock.ElapsedMilliseconds;
                        progress = "streaming"; stage = "stream_audio";
                    }
                    long phase = Stopwatch.GetTimestamp();
                    int available = port.Available;
                    result.MaxSerialAvailabilityMilliseconds = Math.Max(result.MaxSerialAvailabilityMilliseconds, PhaseMilliseconds(phase));
                    if (available > 0)
                    {
                        byte[] input = new byte[Math.Min(available, 4096)];
                        phase = Stopwatch.GetTimestamp();
                        int read = port.Read(input, 0, input.Length);
                        result.MaxSerialReadMilliseconds = Math.Max(result.MaxSerialReadMilliseconds, PhaseMilliseconds(phase));
                        phase = Stopwatch.GetTimestamp(); decoder.Feed(input, read);
                        result.MaxDecodeMilliseconds = Math.Max(result.MaxDecodeMilliseconds, PhaseMilliseconds(phase));
                        if (endpoint != null && decoder.Audio.Count > 0) endpoint.ReceiveAudio(decoder.DrainAudio());
                        if (endpoint != null && (decoder.Events.Contains("busy_tone") || decoder.Events.Contains("local_on_hook"))) { result.EndReason = "modem_hangup"; break; }
                    }
                    if (clock.ElapsedMilliseconds >= frame * 20L + controlPause)
                    {
                        byte[] audio = new byte[160];
                        if (endpoint == null) for (int i = 0; i < audio.Length; i++)
                        {
                            int position = frame * 160 + i;
                            bool beep = position < 32000 && position % 8000 < 1600;
                            short sample = beep ? (short)(3000 * Math.Sin(2 * Math.PI * 660 * position / 8000.0)) : (short)0;
                            audio[i] = VoiceCodec.EncodeMuLaw(sample);
                        }
                        if (endpoint != null) { phase = Stopwatch.GetTimestamp(); audio = endpoint.TransmitAudio(160); result.MaxEndpointMilliseconds = Math.Max(result.MaxEndpointMilliseconds, PhaseMilliseconds(phase)); }
                        if (audio == null || audio.Length != 160) throw new InvalidDataException("invalid_bridge_audio_block");
                        phase = Stopwatch.GetTimestamp();
                        byte[] modemAudio = pcm16 ? VoiceCodec.Pcm16FromMuLaw(audio) : audio;
                        byte[] wireAudio = VoiceCodec.Escape(modemAudio);
                        result.MaxEncodeMilliseconds = Math.Max(result.MaxEncodeMilliseconds, PhaseMilliseconds(phase));
                        phase = Stopwatch.GetTimestamp(); Raw(wireAudio);
                        result.MaxSerialWriteMilliseconds = Math.Max(result.MaxSerialWriteMilliseconds, PhaseMilliseconds(phase));
                        result.ModemTransmitBytes += modemAudio.Length; result.TransmittedSamples += audio.Length; frame++;
                        int lag = (int)Math.Max(0, clock.ElapsedMilliseconds - frame * 20L - controlPause);
                        result.MaxTransmitLagMilliseconds = Math.Max(result.MaxTransmitLagMilliseconds, lag);
                        if (lag > 250) { result.EndReason = "audio_timing_failure"; throw new IOException("audio_transmit_timing_failed"); }
                    }
                    else Thread.Sleep(2);
                    if (clock.ElapsedMilliseconds >= nextWindow)
                    {
                        result.SampleWindows.Add(new VoiceSampleWindow { Milliseconds = (int)clock.ElapsedMilliseconds, TransmittedSamples = result.TransmittedSamples, ReceivedSamples = decoder.DecodedSampleCount, RawReceivedSamples = decoder.RawSampleCount });
                        nextWindow += 1000;
                    }
                }
                result.StreamMilliseconds = (int)clock.ElapsedMilliseconds;
                result.Status = endpoint is IGreetingModeComparison ? (endpoint.Ended ? "comparison_completed" : "failed") : endpoint == null ? "captured" : "bridged";
                if (endpoint is IGreetingModeComparison && !endpoint.Ended) { result.ErrorCode="comparison_not_completed"; result.FailureStage=stage; result.FailureError=result.ErrorCode; }
                if (result.EndReason.Length == 0) result.EndReason = endpoint is IGreetingModeComparison && endpoint.Ended ? "comparison_completed" : endpoint != null && endpoint.Ended ? "pbx_hangup" : decoder.Ended ? "modem_stream_end" : "duration_limit";
            }
            catch (OperationCanceledException) { result.Cancelled = true; result.EndReason = "user_cancelled"; result.Status = "cancelled"; result.ErrorCode = "cancelled_by_user"; result.FailureStage = stage; result.FailureError = result.ErrorCode; }
            catch (Exception exception) { result.Status = "failed"; result.ErrorCode = exception.Message; result.FailureStage = stage; result.FailureError = exception.Message; }
            finally
            {
                if (streamClock != null) { result.StreamMilliseconds = (int)streamClock.ElapsedMilliseconds; streamClock.Stop(); }
                result.RingInputHex = BitConverter.ToString(ringInput.ToArray());
                progress = "hanging_up";
                if (opened)
                {
                    try
                    {
                        if (transmitOnly) Exchange("<DLE><ETX>", new byte[] { 16, 3 }, false, false);
                        if (duplex) Exchange("<DLE>^", new byte[] { 16, 94 }, false, false);
                        if (answerAttempted) Command("ATH", false, false);

                        if (changedMode && originalFormat.Length > 0) Command("AT+VSM=" + originalFormat, false, false);
                        if (originalFlow.Length > 0) Command("AT+IFC=" + originalFlow, false, false);
                        if (changedTransmitLevel)
                        {
                            try
                            {
                                Command("AT+VGT=" + result.OriginalTransmitLevel, false, false);
                                if (VoiceTransmitLevel.Current(Command("AT+VGT?", false, false)) != result.OriginalTransmitLevel)
                                    throw new InvalidOperationException("transmit_level_restore_not_confirmed");
                                result.TransmitLevelRestored = true;
                            }
                            catch (Exception exception) { result.CleanupError = exception.Message; }
                        }
                        if (changedMode && originalClass.Length > 0) Command("AT+FCLASS=" + originalClass, false, false);
                        result.CleanupConfirmed = result.CleanupError.Length == 0;
                    }
                    catch (Exception exception)
                    {
                        result.CleanupError = exception.Message;
                        try { port.ReleaseDtr(); } catch { }
                        result.CleanupConfirmed = false;
                    }
                }
                try { port.Dispose(); } catch { }
                decoder.Finish();
                result.ReceivedSamples = decoder.DecodedSampleCount;
                result.RawReceivedSamples = decoder.RawSampleCount;
                result.RemovedRepeatedSamples = decoder.RawSampleCount - decoder.DecodedSampleCount;
                if (streamClock != null && (result.SampleWindows.Count == 0 || result.SampleWindows[result.SampleWindows.Count - 1].Milliseconds != result.StreamMilliseconds))
                    result.SampleWindows.Add(new VoiceSampleWindow { Milliseconds = result.StreamMilliseconds, TransmittedSamples = result.TransmittedSamples, ReceivedSamples = result.ReceivedSamples, RawReceivedSamples = result.RawReceivedSamples });
                result.ReceiveFraming = decoder.ReceiveFraming;
                if (!decoder.FramingValid)
                {
                    result.Status = "failed"; result.ErrorCode = decoder.IncompletePcmSample ? "voice_pcm16_partial_sample" : "voice_receive_framing_changed";
                    if (result.FailureStage.Length == 0) { result.FailureStage = "stream_audio"; result.FailureError = result.ErrorCode; }
                }
                result.Events = decoder.Events.Distinct().ToArray();
                if (endpoint == null && decoder.Audio.Count > 0)
                {
                    try
                    {
                        double squares = 0; int peak = 0;
                        foreach (byte b in decoder.Audio) { int sample = VoiceCodec.DecodeMuLaw(b); squares += (double)sample * sample; peak = Math.Max(peak, Math.Abs(sample)); }
                        result.ReceivedPeak = peak;
                        result.ReceivedRms = Math.Sqrt(squares / decoder.Audio.Count) / 32768.0;
                        Directory.CreateDirectory(folder);
                        result.RecordingFile = "modem-audio-" + Guid.NewGuid().ToString("N") + ".wav";
                        RecordingPath = Path.Combine(folder, result.RecordingFile);
                        VoiceCodec.SaveWave(RecordingPath, decoder.Audio.ToArray());
                    }
                    catch
                    {
                        result.Status = "failed"; result.ErrorCode = "recording_write_failed";
                        if (result.FailureStage.Length == 0) { result.FailureStage = "save_recording"; result.FailureError = result.ErrorCode; }
                    }
                }
                if (!result.CleanupConfirmed && opened)
                {
                    result.Status = "failed"; result.ErrorCode = "hangup_or_restore_not_confirmed";
                    if (result.FailureStage.Length == 0) { result.FailureStage = "cleanup"; result.FailureError = result.CleanupError; }
                }
                if (endpoint != null) try { endpoint.Dispose(); } catch { result.Status = "failed"; result.ErrorCode = "pbx_cleanup_failed"; if (result.FailureStage.Length == 0) { result.FailureStage = "cleanup_pbx"; result.FailureError = result.ErrorCode; } }
                progress = "finished";
            }
        }
    }
}
