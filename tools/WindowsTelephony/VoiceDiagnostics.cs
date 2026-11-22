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
    public interface IVoicePort : IDisposable
    {
        void Open();
        int Available { get; }
        int Read(byte[] buffer, int offset, int count);
        void Write(byte[] buffer, int offset, int count);
        void ReleaseDtr();
    }
    public sealed class SerialVoicePort : IVoicePort
    {
        private readonly SerialPort port;
        public SerialVoicePort(string name)
        {
            if (!Regex.IsMatch(name, @"^COM[1-9][0-9]{0,4}$")) throw new ArgumentException("Invalid COM port");
            port = new SerialPort(name, 115200, Parity.None, 8, StopBits.One);
            port.ReadTimeout = 100; port.WriteTimeout = 1000;
            port.DtrEnable = true; port.RtsEnable = true; port.Handshake = Handshake.None;
        }
        public void Open() { port.Open(); }
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
        private bool dle;
        public readonly List<byte> Audio = new List<byte>();
        private readonly VoiceReceiveFraming framing;
        public int RawSampleCount { get; private set; }
        public bool FramingValid { get { return framing == null || framing.Valid; } }
        public string ReceiveFraming { get { return framing == null ? "linear" : framing.Mode; } }
        public VoiceDecoder() : this(false) { }
        public VoiceDecoder(bool detectFraming) { if (detectFraming) framing = new VoiceReceiveFraming(value => Audio.Add(value)); }
        private void AddAudio(byte value) { RawSampleCount++; if (framing == null) Audio.Add(value); else framing.Feed(value); }
        public void Finish() { if (framing != null) framing.Finish(); }
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
        public string DiagnosticVersion = "2026.11.22.2";
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
        public bool CleanupConfirmed;
        public bool Cancelled;
        public int TransmittedSamples;
        public int ReceivedSamples;
        public int RawReceivedSamples;
        public int RemovedRepeatedSamples;
        public string ReceiveFraming = "linear";
        public int ReceivedPeak;
        public double ReceivedRms;
        public int StreamMilliseconds;
        public readonly List<VoiceSampleWindow> SampleWindows = new List<VoiceSampleWindow>();
        public double ReceiveRateAfterWarmup { get { return VoiceTiming.Rate(SampleWindows, true); } }
        public double TransmitRateAfterWarmup { get { return VoiceTiming.Rate(SampleWindows, false); } }
        public bool BridgeTimingReady { get { return VoiceTiming.Ready(SampleWindows); } }
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
        private readonly string folder;
        private readonly int ringTimeout;
        private readonly int captureDuration;
        private readonly CancellationTokenSource stop = new CancellationTokenSource();
        private Task worker;
        private readonly VoiceResult result = new VoiceResult();
        private volatile string progress = "ready";
        private string stage = "open_port";
        private bool opened, changedMode, duplex, answerAttempted;
        private string originalClass = "", originalFormat = "", originalFlow = "";
        public string Progress { get { return progress; } }
        public bool Completed { get { return worker != null && worker.IsCompleted; } }
        public VoiceResult Result { get { if (!Completed) throw new InvalidOperationException("Test not finished"); return result; } }
        public string RecordingPath { get; private set; }
        public VoiceSession(string name, string folder) : this(new SerialVoicePort(name), folder, 60000, 12000) { }
        public VoiceSession(IVoicePort port, string folder, int ringTimeout, int captureDuration)
        {
            if (ringTimeout < 100 || ringTimeout > 60000 || captureDuration < 100 || captureDuration > 30000) throw new ArgumentOutOfRangeException();
            this.port = port; this.folder = folder; this.ringTimeout = ringTimeout; this.captureDuration = captureDuration;
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
                trace.Response = trace.Command == "<DLE>^"
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
        private void Run()
        {
            VoiceDecoder decoder = new VoiceDecoder(true);
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
                int codec = VoiceCodec.FindMuLaw(result.Formats);
                if (codec < 0) throw new InvalidOperationException("no_supported_8khz_mulaw");
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
                stage = "configure_audio";
                Command("AT+IFC=0,0", false, true);
                Command("AT+VSM=" + codec.ToString(CultureInfo.InvariantCulture) + ",8000", false, true);
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
                stage = "answer_incoming_call";
                progress = "answering"; answerAttempted = true; Command("AT+VLS=1", false, true); result.Answered = true;
                stage = "start_duplex";
                Command("AT+VTR", true, true); duplex = true; result.DuplexStarted = true;
                progress = "streaming"; stage = "stream_audio";
                var clock = Stopwatch.StartNew(); int frame = 0, nextWindow = 1000;
                while (clock.ElapsedMilliseconds < captureDuration && !decoder.Ended)
                {
                    stop.Token.ThrowIfCancellationRequested();
                    int available = port.Available;
                    if (available > 0)
                    {
                        byte[] input = new byte[Math.Min(available, 4096)];
                        int read = port.Read(input, 0, input.Length); decoder.Feed(input, read);
                    }
                    if (clock.ElapsedMilliseconds >= frame * 20L)
                    {
                        byte[] audio = new byte[160];
                        for (int i = 0; i < audio.Length; i++)
                        {
                            int position = frame * 160 + i;
                            bool beep = position < 32000 && position % 8000 < 1600;
                            short sample = beep ? (short)(3000 * Math.Sin(2 * Math.PI * 660 * position / 8000.0)) : (short)0;
                            audio[i] = VoiceCodec.EncodeMuLaw(sample);
                        }
                        Raw(VoiceCodec.Escape(audio)); result.TransmittedSamples += audio.Length; frame++;
                        if (clock.ElapsedMilliseconds - frame * 20L > 250) throw new IOException("audio_transmit_timing_failed");
                    }
                    else Thread.Sleep(2);
                    if (clock.ElapsedMilliseconds >= nextWindow)
                    {
                        result.SampleWindows.Add(new VoiceSampleWindow { Milliseconds = (int)clock.ElapsedMilliseconds, TransmittedSamples = result.TransmittedSamples, ReceivedSamples = decoder.Audio.Count, RawReceivedSamples = decoder.RawSampleCount });
                        nextWindow += 1000;
                    }
                }
                result.StreamMilliseconds = (int)clock.ElapsedMilliseconds;
                result.Status = "captured";
            }
            catch (OperationCanceledException) { result.Cancelled = true; result.Status = "cancelled"; result.ErrorCode = "cancelled_by_user"; result.FailureStage = stage; result.FailureError = result.ErrorCode; }
            catch (Exception exception) { result.Status = "failed"; result.ErrorCode = exception.Message; result.FailureStage = stage; result.FailureError = exception.Message; }
            finally
            {
                result.RingInputHex = BitConverter.ToString(ringInput.ToArray());
                progress = "hanging_up";
                if (opened)
                {
                    try
                    {
                        if (duplex) Exchange("<DLE>^", new byte[] { 16, 94 }, false, false);
                        if (answerAttempted) Command("ATH", false, false);
                        if (changedMode && originalFormat.Length > 0) Command("AT+VSM=" + originalFormat, false, false);
                        if (originalFlow.Length > 0) Command("AT+IFC=" + originalFlow, false, false);
                        if (changedMode && originalClass.Length > 0) Command("AT+FCLASS=" + originalClass, false, false);
                        result.CleanupConfirmed = true;
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
                result.ReceivedSamples = decoder.Audio.Count;
                result.RawReceivedSamples = decoder.RawSampleCount;
                result.RemovedRepeatedSamples = decoder.RawSampleCount - decoder.Audio.Count;
                result.ReceiveFraming = decoder.ReceiveFraming;
                if (!decoder.FramingValid)
                {
                    result.Status = "failed"; result.ErrorCode = "voice_receive_framing_changed";
                    if (result.FailureStage.Length == 0) { result.FailureStage = "stream_audio"; result.FailureError = result.ErrorCode; }
                }
                result.Events = decoder.Events.Distinct().ToArray();
                if (decoder.Audio.Count > 0)
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
                progress = "finished";
            }
        }
    }
}
