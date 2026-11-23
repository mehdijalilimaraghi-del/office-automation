using System;
using System.IO;
using System.Text;
using System.Runtime.InteropServices;

public sealed class OfficePromptRecorder : IDisposable
{
    [DllImport("winmm.dll", CharSet = CharSet.Unicode)] static extern uint mciSendString(string command, StringBuilder reply, int length, IntPtr callback);
    [DllImport("winmm.dll", CharSet = CharSet.Unicode)] static extern bool mciGetErrorString(uint error, StringBuilder text, int length);
    readonly string alias = "office_prompt_" + Guid.NewGuid().ToString("N");
    bool opened;
    void Send(string command) {
        uint code = mciSendString(command, null, 0, IntPtr.Zero);
        if (code != 0) { var text = new StringBuilder(512); mciGetErrorString(code, text, text.Capacity); throw new InvalidOperationException("Audio error " + code + ": " + text); }
    }
    public void Start() {
        if (opened) throw new InvalidOperationException("Already recording");
        Send("open new type waveaudio alias " + alias); opened = true;
        try {
            Send("set " + alias + " time format milliseconds format tag pcm channels 1 samplespersec 8000 bitspersample 16 alignment 2 bytespersec 16000");
            Send("record " + alias + " to 30000");
        } catch { Dispose(); throw; }
    }
    public void Save(string path) {
        if (!opened) throw new InvalidOperationException("Not recording");
        path = Path.GetFullPath(path);
        if (path.Contains("\"") || File.Exists(path)) throw new InvalidOperationException("Choose a new WAV filename");
        Send("stop " + alias);
        Send("save " + alias + " \"" + path + "\"");
    }
    public void Dispose() {
        if (!opened) return;
        Send("close " + alias); opened = false;
    }
}
