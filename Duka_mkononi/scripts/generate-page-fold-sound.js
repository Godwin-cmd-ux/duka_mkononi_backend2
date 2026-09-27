#!/usr/bin/env node
/**
 * Writes assets/sounds/page-fold.wav - the short "paper squash" played when a
 * tab is flipped with a swipe.
 *
 * The asset is synthesised instead of downloaded so it stays tiny (~15 KB),
 * licence-free and reproducible:
 *
 *   node scripts/generate-page-fold-sound.js
 *
 * The old version was a smooth band-passed swish with a single crease snap,
 * which read as airy rather than papery. This is a crumple/squash instead:
 *
 *   1. a low "squash" body - heavily low-passed noise with a fast attack and a
 *      quick decay, the soft thud of a sheet being crushed,
 *   2. a crinkle bed - band-passed noise with a quick swell and a short decay,
 *      which keeps the clip sounding full rather than gappy at any volume,
 *   3. a dense crumple layer - a stream of tiny, randomly spaced impulses
 *      (a few thousand per second, thinning out) shaped through a band-pass,
 *      which is what actually gives the crackling-paper texture.
 *
 * It is mono 22.05 kHz 16-bit PCM, which ExoPlayer (Android) and AVFoundation
 * (iOS) both play natively.
 */
const fs = require('fs');
const path = require('path');

const SAMPLE_RATE = 22050;
const DURATION = 0.3; // seconds
const OUT = path.join(__dirname, '..', 'assets', 'sounds', 'page-fold.wav');

/** Deterministic PRNG so regenerating the file gives an identical asset. */
function mulberry32(seed) {
  let a = seed >>> 0;
  return function random() {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** One-pole low-pass coefficient for a cutoff in Hz. */
function onePole(cutoffHz) {
  return 1 - Math.exp((-2 * Math.PI * cutoffHz) / SAMPLE_RATE);
}

function synthesise() {
  const random = mulberry32(20260928);
  const total = Math.round(SAMPLE_RATE * DURATION);
  const samples = new Float32Array(total);

  // --- Layer 1: the low "squash" body -------------------------------------
  // Two cascaded one-poles give a steeper (more thump-like) roll-off.
  const bodyA = onePole(300);
  const bodyA2 = onePole(300);
  let body1 = 0;
  let body2 = 0;
  const bodyAttack = 0.004 * SAMPLE_RATE;
  const bodyDecay = 0.085; // seconds

  // --- Layer 2: the continuous crinkle bed --------------------------------
  const bedHighA = onePole(4000);
  const bedLowA = onePole(700);
  let bedHigh = 0;
  let bedLow = 0;
  const bedAttack = 0.01 * SAMPLE_RATE;
  const bedDecay = 0.15; // seconds

  // --- Layer 3: the crumple crackle ---------------------------------------
  // Sparse impulses, then band-passed (high shelf minus low shelf) to give
  // them a papery upper-mid voice instead of a dull click.
  const excite = new Float32Array(total);
  {
    let cursor = 0;
    while (cursor < total) {
      const progress = cursor / total;
      // Dense at the crush, thinning as the sheet settles.
      const perSecond = 2600 * (1 - progress) + 420 * progress;
      const gap = Math.max(1, Math.round(SAMPLE_RATE / perSecond));
      const at = cursor + Math.floor(random() * gap);
      if (at < total) {
        const amp = random() * 2 - 1;
        // Each micro-tear is ~1.4 ms, with its own little decay.
        const click = Math.round(0.0014 * SAMPLE_RATE);
        for (let k = 0; k < click && at + k < total; k += 1) {
          excite[at + k] += amp * Math.exp(-k / (click * 0.35));
        }
      }
      cursor += gap;
    }
  }
  const crackleHighA = onePole(6500);
  const crackleLowA = onePole(1400);
  let crackleHigh = 0;
  let crackleLow = 0;

  const sampleAt = (i) => {
    const t = i / SAMPLE_RATE;
    let value = 0;

    // Squash body.
    const noise = random() * 2 - 1;
    body1 += bodyA * (noise - body1);
    body2 += bodyA2 * (body1 - body2);
    const bodyAttackGain = i < bodyAttack ? i / bodyAttack : 1;
    value += body2 * bodyAttackGain * Math.exp(-t / bodyDecay) * 1.6;

    // Crinkle bed: fills the space between the crackles.
    const bedNoise = random() * 2 - 1;
    bedHigh += bedHighA * (bedNoise - bedHigh);
    bedLow += bedLowA * (bedNoise - bedLow);
    const bed = bedHigh - bedLow;
    const bedAttackGain = i < bedAttack ? i / bedAttack : 1;
    value += bed * bedAttackGain * Math.exp(-t / bedDecay) * 9;

    // Crumple crackle.
    const dry = excite[i];
    crackleHigh += crackleHighA * (dry - crackleHigh);
    crackleLow += crackleLowA * (dry - crackleLow);
    const crackle = crackleHigh - crackleLow;
    // Quick swell in, then the crunch dies away.
    const swell = Math.min(1, i / (0.012 * SAMPLE_RATE));
    value += crackle * swell * Math.exp(-t / 0.17) * 10;

    return value;
  };

  for (let i = 0; i < total; i += 1) samples[i] = sampleAt(i);

  // Normalise, then fade the tail so there is no click at the end.
  let peak = 0;
  for (let i = 0; i < total; i += 1) peak = Math.max(peak, Math.abs(samples[i]));
  const gain = peak > 0 ? 0.9 / peak : 0;
  const fade = 0.012 * SAMPLE_RATE;
  for (let i = 0; i < total; i += 1) {
    let v = samples[i] * gain;
    const remaining = total - i;
    if (remaining < fade) v *= remaining / fade;
    samples[i] = v;
  }
  return samples;
}

function encodeWav(samples) {
  const dataBytes = samples.length * 2;
  const buffer = Buffer.alloc(44 + dataBytes);

  buffer.write('RIFF', 0, 'ascii');
  buffer.writeUInt32LE(36 + dataBytes, 4);
  buffer.write('WAVE', 8, 'ascii');
  buffer.write('fmt ', 12, 'ascii');
  buffer.writeUInt32LE(16, 16); // PCM chunk size
  buffer.writeUInt16LE(1, 20); // PCM
  buffer.writeUInt16LE(1, 22); // mono
  buffer.writeUInt32LE(SAMPLE_RATE, 24);
  buffer.writeUInt32LE(SAMPLE_RATE * 2, 28); // byte rate
  buffer.writeUInt16LE(2, 32); // block align
  buffer.writeUInt16LE(16, 34); // bits per sample
  buffer.write('data', 36, 'ascii');
  buffer.writeUInt32LE(dataBytes, 40);

  for (let i = 0; i < samples.length; i += 1) {
    const clamped = Math.max(-1, Math.min(1, samples[i]));
    buffer.writeInt16LE(Math.round(clamped * 32767), 44 + i * 2);
  }
  return buffer;
}

const wav = encodeWav(synthesise());
fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, wav);
console.log(`Wrote ${path.relative(process.cwd(), OUT)} (${wav.length} bytes)`);
