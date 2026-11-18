let context: AudioContext | undefined;

export async function primeNotificationSound(): Promise<boolean> {
    try {
        context ??= new AudioContext();
        if (context.state === 'suspended') await context.resume();
        return context.state === 'running';
    } catch {
        return false;
    }
}

export async function playNotificationSound(): Promise<boolean> {
    if (!context || context.state !== 'running') return false;
    try {
        const start = context.currentTime;
        for (const [offset, frequency] of [[0, 660], [0.18, 880]]) {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0, start + offset);
            gain.gain.linearRampToValueAtTime(0.12, start + offset + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.001, start + offset + 0.25);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start(start + offset);
            oscillator.stop(start + offset + 0.27);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
        }
        return true;
    } catch {
        return false;
    }
}
