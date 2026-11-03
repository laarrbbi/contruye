"""Speaks a script and lays it out in time.

Usage: python voz.py <locale>   (reads ../guion/<locale>.json, writes ../salida/<locale>/)

Each line of the script becomes its own clip, trimmed of the silence the model
leaves around it. The clips are then placed one after the other, scene by scene,
with fixed breaths between them; that placement is the timeline the picture is
animated on (salida/<locale>/linea-de-tiempo.json), so the picture always follows
the voice, whatever voice speaks it.
"""
import json, os, sys, numpy as np, soundfile as sf
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import tts

HERE = os.path.dirname(os.path.abspath(__file__))
RATE = 48000
FIRST_LEAD = 2.4   # the title has the screen to itself before the first word
LEAD = 1.0         # each scene draws itself in a little before it is spoken
GAP = 0.34         # breath between two lines
TAIL = 0.9         # the last line is left to settle
OVERLAP = 0.7      # the next scene comes in over the end of this one


def resample(x, sr):
    if sr == RATE:
        return x
    n = int(round(len(x) * RATE / sr))
    return np.interp(np.linspace(0, len(x) - 1, n), np.arange(len(x)), x).astype(np.float32)


def trim(x, threshold=0.012, pad=0.03):
    loud = np.where(np.abs(x) > threshold)[0]
    if len(loud) == 0:
        return x
    a = max(0, loud[0] - int(pad * RATE))
    b = min(len(x), loud[-1] + int(pad * RATE))
    return x[a:b]


def spoken(line):
    return line if isinstance(line, str) else line['say']


def main(locale):
    script = json.load(open(os.path.join(HERE, '..', 'guion', f'{locale}.json'), encoding='utf-8'))
    out = os.path.join(HERE, '..', 'salida', locale)
    os.makedirs(os.path.join(out, 'voz'), exist_ok=True)
    clips, timeline, t = [], [], 0.0
    for k, scene in enumerate(script['scenes']):
        start = t
        t += FIRST_LEAD if k == 0 else LEAD
        lines = []
        for i, line in enumerate(scene['vo']):
            path = os.path.join(out, 'voz', f'{k:02d}_{i:02d}.wav')
            samples, sr = tts.speak(locale, spoken(line))
            clip = trim(resample(samples, sr))
            sf.write(path, clip, RATE)
            duration = len(clip) / RATE
            lines.append({'start': round(t, 3), 'end': round(t + duration, 3)})
            clips.append((t, clip))
            t += duration + GAP
            print(f'{scene["id"]} {i} {duration:.2f}s', flush=True)
        end = lines[-1]['end'] + TAIL
        timeline.append({'id': scene['id'], 'start': round(start, 3), 'end': round(end, 3), 'lines': lines})
        t = end - OVERLAP
    total = timeline[-1]['end'] + 1.2
    voice = np.zeros(int(total * RATE) + 1, dtype=np.float32)
    for at, clip in clips:
        i = int(at * RATE)
        voice[i:i + len(clip)] += clip
    sf.write(os.path.join(out, 'voz.wav'), voice, RATE)
    json.dump({'locale': locale, 'duration': round(total, 3), 'scenes': timeline}, open(os.path.join(out, 'linea-de-tiempo.json'), 'w'), indent=1)
    print(f'{locale}: {total:.1f} s', flush=True)


if __name__ == '__main__':
    main(sys.argv[1])
