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
    public sealed class VoiceDecoder
    {
        private bool dle;
        public readonly List<byte> Audio = new List<byte>();
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
                    if (b == 16) Audio.Add(16);
                    else if (b == 3) { Events.Add("stream_end"); Ended = true; return; }
                    else if (b == (byte)'b') Events.Add("busy_tone");
                    else if (b == (byte)'h') Events.Add("local_on_hook");
                    else if (b == (byte)'s') Events.Add("silence");
                    else Events.Add("dle_" + b.ToString("X2"));
                }
                else if (b == 16) dle = true;
                else Audio.Add(b);
                if (Audio.Count > 240000) throw new InvalidDataException("Audio size limit exceeded");
            }
        }
    }
    public sealed class VoiceResult
    {
        public string Status = "not_started";
        public string ErrorCode = "";
        public bool RingDetected;
        public bool Answered;
        public bool DuplexStarted;
        public bool CleanupConfirmed;
        public bool Cancelled;
        public int TransmittedSamples;
        public int ReceivedSamples;
        public int ReceivedPeak;
        public double ReceivedRms;
        public int StreamMilliseconds;
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
        private string Reply(int timeout, bool cancellable)
        {
            var watch = Stopwatch.StartNew(); var text = new StringBuilder();
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
        private string Command(string command, bool connect, bool cancellable)
        {
            Raw(Encoding.ASCII.GetBytes(command + "\r"));
            string reply = Reply(2500, cancellable);
            string expected = connect ? "CONNECT" : "OK";
            if (!Regex.IsMatch(reply, @"(?:^|[\r\n])" + expected + @"\s*(?:$|[\r\n])")) throw new InvalidOperationException("command_rejected:" + command.Split('=')[0]);
            return reply;
        }
        private void Run()
        {
            VoiceDecoder decoder = new VoiceDecoder();
            try
            {
                progress = "preparing"; port.Open(); opened = true;
                Command("AT", false, true);
                result.Identity = Command("ATI", false, true);
                string classes = Command("AT+FCLASS?", false, true);
                Match original = Regex.Match(classes, @"(?:^|[\r\n])\s*(?:\+FCLASS:\s*)?([0-9]+(?:\.[0-9]+)?)\s*(?:$|[\r\n])");
                if (!original.Success) throw new InvalidOperationException("cannot_preserve_original_mode");
                originalClass = original.Groups[1].Value;
                changedMode = true; Command("AT+FCLASS=8", false, true);
                result.Formats = Command("AT+VSM=?", false, true);
                int codec = VoiceCodec.FindMuLaw(result.Formats);
                if (codec < 0) throw new InvalidOperationException("no_supported_8khz_mulaw");
                string format = Command("AT+VSM?", false, true);
                Match oldFormat = Regex.Match(format, @"(?:^|[\r\n])\s*(?:\+VSM:\s*)?([0-9]+\s*,\s*[0-9]+)\s*(?:,|$|[\r\n])");
                if (!oldFormat.Success) throw new InvalidOperationException("cannot_preserve_original_format");
                originalFormat = Regex.Replace(oldFormat.Groups[1].Value, @"\s+", "");
                string flow = Command("AT+IFC?", false, true);
                Match oldFlow = Regex.Match(flow, @"(?:^|[\r\n])\s*(?:\+IFC:\s*)?([0-9]+\s*,\s*[0-9]+)\s*(?:$|[\r\n])");
                if (!oldFlow.Success) throw new InvalidOperationException("cannot_preserve_flow_control");
                originalFlow = Regex.Replace(oldFlow.Groups[1].Value, @"\s+", "");
                Command("AT+IFC=0,0", false, true);
                Command("AT+VSM=" + codec.ToString(CultureInfo.InvariantCulture) + ",8000", false, true);
                progress = "waiting_ring";
                var ringWatch = Stopwatch.StartNew(); var text = new StringBuilder();
                while (ringWatch.ElapsedMilliseconds < ringTimeout)
                {
                    stop.Token.ThrowIfCancellationRequested();
                    if (port.Available > 0)
                    {
                        byte[] b = new byte[1]; port.Read(b, 0, 1); text.Append((char)b[0]);
                        if (Regex.IsMatch(text.ToString(), @"(?:^|[\r\n])RING\r?\n$")) { result.RingDetected = true; break; }
                        if (text.Length > 4096) text.Remove(0, 2048);
                    }
                    else Thread.Sleep(10);
                }
                if (!result.RingDetected) throw new TimeoutException("incoming_call_timeout");
                stop.Token.ThrowIfCancellationRequested();
                progress = "answering"; answerAttempted = true; Command("AT+VLS=1", false, true); result.Answered = true;
                Command("AT+VTR", true, true); duplex = true; result.DuplexStarted = true;
                progress = "streaming";
                var clock = Stopwatch.StartNew(); int frame = 0;
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
                }
                result.StreamMilliseconds = (int)clock.ElapsedMilliseconds;
                result.Status = "captured";
            }
            catch (OperationCanceledException) { result.Cancelled = true; result.Status = "cancelled"; result.ErrorCode = "cancelled_by_user"; }
            catch (Exception exception) { result.Status = "failed"; result.ErrorCode = exception.Message; }
            finally
            {
                progress = "hanging_up";
                if (opened)
                {
                    try
                    {
                        if (duplex) { Raw(new byte[] { 16, 94 }); string ended = Reply(2500, false); if (!Regex.IsMatch(ended, @"(?:^|[\r\n])OK\s*(?:$|[\r\n])")) throw new IOException("voice_stop_not_acknowledged"); }
                        if (answerAttempted) Command("ATH", false, false);
                        if (changedMode && originalFormat.Length > 0) Command("AT+VSM=" + originalFormat, false, false);
                        if (originalFlow.Length > 0) Command("AT+IFC=" + originalFlow, false, false);
                        if (changedMode && originalClass.Length > 0) Command("AT+FCLASS=" + originalClass, false, false);
                        result.CleanupConfirmed = true;
                    }
                    catch { try { port.ReleaseDtr(); } catch { } result.CleanupConfirmed = false; }
                }
                try { port.Dispose(); } catch { }
                result.ReceivedSamples = decoder.Audio.Count;
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
                    catch { result.Status = "failed"; result.ErrorCode = "recording_write_failed"; }
                }
                if (!result.CleanupConfirmed && opened) { result.Status = "failed"; result.ErrorCode = "hangup_or_restore_not_confirmed"; }
                progress = "finished";
            }
        }
    }
}
