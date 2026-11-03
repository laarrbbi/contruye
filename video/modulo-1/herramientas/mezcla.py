"""The film's sound: the voice, a quiet pad under it, and a few sounds on the beats.

Usage: python mezcla.py <locale>   (reads salida/<locale>/voz.wav and linea-de-tiempo.json,
writes salida/<locale>/mezcla.wav, loudness-normalised to -16 LUFS)

Everything is synthesised here, from fixed seeds: no sample library, nothing to license.
The pad ducks under the voice; the effects sit on the cuts the picture makes (same
formulas as composicion/motion.js).
"""
import json, os, subprocess, sys, numpy as np, soundfile as sf

HERE = os.path.dirname(os.path.abspath(__file__))
R = 48000
rng = np.random.default_rng(20261102)


def note(f):
    return 440.0 * 2 ** ((f - 69) / 12)


def onepole(x, cutoff):
    """A gentle low-pass, in the frequency domain (cutoff in Hz)."""
    X = np.fft.rfft(x)
    f = np.fft.rfftfreq(len(x), 1 / R)
    return np.fft.irfft(X / np.sqrt(1 + (f / cutoff) ** 2), len(x))


def reverb(x, seconds=3.2, mix=0.32):
    n = int(seconds * R)
    t = np.arange(n) / R
    ir = rng.standard_normal(n) * np.exp(-t * 6.9 / seconds)
    ir = onepole(ir, 4200)
    ir /= np.sqrt(np.sum(ir ** 2))
    size = 1 << int(np.ceil(np.log2(len(x) + n)))
    wet = np.fft.irfft(np.fft.rfft(x, size) * np.fft.rfft(ir, size), size)[: len(x)]
    return (1 - mix) * x + mix * wet


def pad(duration):
    """Dmaj9 – Bm9 – Gmaj9 – A6/9, eight seconds each, slow fades between them."""
    chords = [[50, 57, 61, 64, 66, 69], [47, 54, 57, 61, 62, 66], [43, 50, 54, 57, 59, 62], [45, 52, 57, 59, 61, 64]]
    n = int(duration * R)
    t = np.arange(n) / R
    out = np.zeros((2, n))
    span = 8.0
    for k in range(int(duration / span) + 2):
        chord = chords[k % 4]
        a, b = k * span - 2.5, (k + 1) * span + 2.5
        env = np.clip((t - a) / 2.5, 0, 1) * np.clip((b - t) / 2.5, 0, 1)
        env = np.sin(env * np.pi / 2) ** 2
        active = env > 0
        if not active.any():
            continue
        tt = t[active]
        for i, m in enumerate(chord):
            f = note(m)
            amp = (0.5 if i == 0 else 0.22) * (0.8 + 0.2 * np.sin(2 * np.pi * (0.05 + i * 0.013) * tt + i))
            for ch, cents in ((0, -4), (1, 4)):
                fd = f * 2 ** (cents / 1200)
                s = np.sin(2 * np.pi * fd * tt + i) + 0.12 * np.sin(4 * np.pi * fd * tt)
                out[ch, active] += amp * s * env[active]
        # The root, an octave down, very soft.
        out[:, active] += 0.18 * np.sin(2 * np.pi * note(chord[0] - 12) * tt) * env[active]
    for ch in range(2):
        out[ch] = reverb(onepole(out[ch], 1600))
    out /= np.max(np.abs(out)) + 1e-9
    return out


def whoosh(length=0.85):
    n = int(length * R)
    t = np.arange(n) / R
    x = rng.standard_normal(n)
    # A band that rises through the sound, then the tail closes.
    y = np.zeros(n)
    state = 0.0
    for i in range(n):
        p = t[i] / length
        c = 300 + 3600 * np.sin(p * np.pi) ** 1.5
        a = 1 - np.exp(-2 * np.pi * c / R)
        state += a * (x[i] - state)
        y[i] = state
    env = np.sin(np.clip(t / length, 0, 1) * np.pi) ** 2
    y = y * env
    return y / (np.max(np.abs(y)) + 1e-9)


def bell(f=880.0, length=0.9):
    t = np.arange(int(length * R)) / R
    y = np.sin(2 * np.pi * f * t) * np.exp(-t * 6) + 0.35 * np.sin(2 * np.pi * f * 2.76 * t) * np.exp(-t * 11)
    return y * np.minimum(1, t / 0.004)


def tick():
    t = np.arange(int(0.08 * R)) / R
    return np.sin(2 * np.pi * 1900 * t) * np.exp(-t * 70) * np.minimum(1, t / 0.001)


def thud():
    t = np.arange(int(0.7 * R)) / R
    body = np.sin(2 * np.pi * (75 - 25 * t) * t) * np.exp(-t * 7)
    hit = onepole(rng.standard_normal(len(t)), 900) * np.exp(-t * 30)
    return body + 0.5 * hit / (np.max(np.abs(hit)) + 1e-9)


def boom():
    t = np.arange(int(2.4 * R)) / R
    return np.sin(2 * np.pi * (52 - 8 * t) * t) * np.exp(-t * 2.2) * np.minimum(1, t / 0.02)


def place(bed, clip, at, gain_db):
    i = int(at * R)
    if i >= bed.shape[1]:
        return
    seg = clip[: bed.shape[1] - i] * 10 ** (gain_db / 20)
    bed[:, i:i + len(seg)] += seg


def main(locale):
    out = os.path.join(HERE, '..', 'salida', locale)
    tl = json.load(open(os.path.join(out, 'linea-de-tiempo.json')))
    voice, sr = sf.read(os.path.join(out, 'voz.wav'))
    assert sr == R
    n = len(voice)
    dur = n / R
    music = pad(dur)[:, :n]
    # Duck the pad under the voice: a slow envelope of the voice level.
    lvl = np.abs(voice)
    k = int(0.25 * R)
    # A centred moving average, by running sum (a direct convolution is O(n·k)).
    c = np.concatenate([[0.0], np.cumsum(lvl)])
    lo = np.clip(np.arange(n) - k // 2, 0, n)
    hi = np.clip(np.arange(n) + (k - k // 2), 0, n)
    env = (c[hi] - c[lo]) / k
    env = np.clip(env / (np.percentile(env[env > 1e-4], 70) + 1e-9), 0, 1)
    duck = 10 ** ((-7 * env) / 20)
    music *= duck
    # Fade the pad in and out with the film.
    t = np.arange(n) / R
    music *= np.clip(t / 3.0, 0, 1) * np.clip((dur - t) / 3.5, 0, 1)
    fx = np.zeros((2, n))
    sc = {s['id']: s for s in tl['scenes']}
    place(fx, boom(), 0.5, -14)
    for s in tl['scenes'][1:]:
        place(fx, whoosh(), s['start'] - 0.05, -27)
    r = sc['route']
    for i in range(4):
        place(fx, bell(note(74 + [0, 2, 4, 7][i])), r['lines'][i + 1]['start'] - 0.15, -30)
    f = sc['filter']
    l2 = f['lines'][2]
    place(fx, thud(), l2['start'] + (l2['end'] - l2['start']) * 0.55, -18)
    c = sc['close']
    s0 = c['lines'][0]['end'] - c['lines'][0]['start']
    for i in range(13):
        at = c['lines'][0]['start'] + 0.1 + i * min(0.26, s0 * 0.6 / 13) + 0.2
        place(fx, tick(), at, -31)
    place(fx, bell(note(62), 2.0), c['lines'][1]['start'] - 0.2, -26)
    mix = np.vstack([voice, voice]) + music * 10 ** (-27 / 20) + fx
    raw = os.path.join(out, 'mezcla-bruta.wav')
    sf.write(raw, mix.T.astype(np.float32), R, subtype='FLOAT')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', raw, '-af', 'loudnorm=I=-16:TP=-1.5:LRA=9', '-ar', str(R), '-c:a', 'pcm_s24le', os.path.join(out, 'mezcla.wav')], check=True)
    os.remove(raw)
    print(f'{locale}: mezcla.wav ({dur:.1f} s)')


if __name__ == '__main__':
    main(sys.argv[1])
