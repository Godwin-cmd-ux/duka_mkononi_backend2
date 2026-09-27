import { Audio } from 'expo-av';

/**
 * The paper-fold sound played when a tab is flipped with a swipe.
 *
 * The clip is short (~0.3 s) and the players would otherwise rebuild it on
 * every turn, so the Sound object is created once, kept for the life of the
 * app and replayed from the start. Everything here is best-effort: a device
 * that cannot play it just stays silent, and a failed gesture must never
 * surface an error.
 */
const FOLD_SOUND = require('../assets/sounds/page-fold.wav');

let sound: Audio.Sound | null = null;
let preparing: Promise<Audio.Sound | null> | null = null;

async function prepare(): Promise<Audio.Sound | null> {
  if (sound) return sound;
  if (!preparing) {
    preparing = (async () => {
      try {
        // `playsInSilentModeIOS` keeps the (very quiet, decorative) cue audible
        // even when the iPhone mute switch is on.
        await Audio.setAudioModeAsync({
          playsInSilentModeIOS: true,
          staysActiveInBackground: false,
        });
        const created = await Audio.Sound.createAsync(FOLD_SOUND, {
          volume: 0.55,
          shouldPlay: false,
        });
        sound = created.sound;
        return sound;
      } catch (error) {
        console.log('page-fold sound unavailable:', error);
        return null;
      } finally {
        preparing = null;
      }
    })();
  }
  return preparing;
}

/** Loads the clip ahead of the first swipe. Safe to call more than once. */
export async function preloadPageFold(): Promise<void> {
  try {
    await prepare();
  } catch {
    // silent: the cue is optional
  }
}

/** Plays the paper-fold cue from the start. Never throws. */
export async function playPageFold(): Promise<void> {
  try {
    const instance = await prepare();
    if (!instance) return;
    const status = await instance.getStatusAsync();
    if (status.isLoaded && status.isPlaying) {
      await instance.stopAsync();
    }
    await instance.replayAsync();
  } catch {
    // silent: the cue is optional
  }
}
