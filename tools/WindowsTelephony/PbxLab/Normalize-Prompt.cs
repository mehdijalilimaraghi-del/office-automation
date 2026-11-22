using System;
using System.IO;
using System.Text;

public static class OfficePromptWave
{
    public static void Convert(string input, string output)
    {
        var info = new FileInfo(input);
        if (info.Length < 44 || info.Length > 20971520) throw new InvalidDataException("WAV size is invalid");
        short channels = 0, bits = 0, format = 0;
        int rate = 0;
        byte[] samples = null;
        using (var reader = new BinaryReader(File.OpenRead(input)))
        {
            if (Encoding.ASCII.GetString(reader.ReadBytes(4)) != "RIFF") throw new InvalidDataException("Not RIFF");
            reader.ReadUInt32();
            if (Encoding.ASCII.GetString(reader.ReadBytes(4)) != "WAVE") throw new InvalidDataException("Not WAVE");
            while (reader.BaseStream.Position + 8 <= reader.BaseStream.Length)
            {
                string id = Encoding.ASCII.GetString(reader.ReadBytes(4));
                uint size = reader.ReadUInt32();
                long next = reader.BaseStream.Position + size + (size % 2);
                if (size > 20971520 || next > reader.BaseStream.Length) throw new InvalidDataException("Truncated WAV");
                if (id == "fmt ")
                {
                    if (size < 16) throw new InvalidDataException("Invalid format");
                    format = reader.ReadInt16(); channels = reader.ReadInt16(); rate = reader.ReadInt32();
                    reader.ReadInt32(); reader.ReadInt16(); bits = reader.ReadInt16();
                }
                if (id == "data") samples = reader.ReadBytes((int)size);
                reader.BaseStream.Position = next;
            }
        }
        if (format != 1 || (channels != 1 && channels != 2) || bits != 16 || rate < 8000 || rate > 48000 || samples == null || samples.Length == 0 || samples.Length % (channels * 2) != 0)
            throw new InvalidDataException("Use PCM 16-bit mono/stereo WAV at 8000-48000 Hz");
        int frames = samples.Length / (channels * 2);
        if (frames > rate * 600) throw new InvalidDataException("Greeting exceeds 10 minutes");
        int count = (int)((long)frames * 8000 / rate);
        using (var writer = new BinaryWriter(File.Create(output)))
        {
            writer.Write(Encoding.ASCII.GetBytes("RIFF")); writer.Write(36 + count * 2);
            writer.Write(Encoding.ASCII.GetBytes("WAVEfmt ")); writer.Write(16);
            writer.Write((short)1); writer.Write((short)1); writer.Write(8000); writer.Write(16000); writer.Write((short)2); writer.Write((short)16);
            writer.Write(Encoding.ASCII.GetBytes("data")); writer.Write(count * 2);
            // Area averaging reduces high-frequency aliasing while downsampling to telephone bandwidth.
            for (int i = 0; i < count; i++)
            {
                int first = (int)((long)i * rate / 8000), last = Math.Min(frames, (int)((long)(i + 1) * rate / 8000));
                long sum = 0; int n = 0;
                for (int f = first; f < Math.Max(first + 1, last); f++) for (int c = 0; c < channels; c++) { sum += BitConverter.ToInt16(samples, (f * channels + c) * 2); n++; }
                writer.Write((short)(sum / n));
            }
        }
    }
}
