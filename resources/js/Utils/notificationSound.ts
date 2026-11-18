import notificationAudio from '../../audio/notification.wav';

let player: HTMLAudioElement | undefined;
export type NotificationSoundResult = 'playing' | 'blocked' | 'unavailable';

export async function playNotificationSound(): Promise<NotificationSoundResult> {
    try {
        player ??= new Audio(notificationAudio);
        player.preload = 'auto';
        player.volume = 0.85;
        player.muted = false;
        player.currentTime = 0;
        await player.play();
        return 'playing';
    } catch (error) {
        return error instanceof DOMException && error.name === 'NotAllowedError' ? 'blocked' : 'unavailable';
    }
}
