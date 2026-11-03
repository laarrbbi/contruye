"""The narrator's voice, one line at a time.

Usage: tts.py <locale> <out.wav> <text>, or JSON lines on stdin ({"locale", "out", "text"}).

Open models, run on this machine (models/README in this folder says where each
comes from and under which licence; fetch them with modelos.sh):
- es, en, fr: Kokoro 82M (Apache-2.0).
- ru: Piper "dmitri" (dataset CC0), through sherpa-onnx with espeak-ng phonemes.
- uk: Piper "ukrainian_tts", speaker "tetiana" (dataset CC0). It reads letters, not
  phonemes, so it is run directly; stress is written in the script (U+0301).
- ar: Piper "kareem" (dataset licence not stated by its author). The script is
  written fully vocalised: espeak-ng guesses the vowels otherwise.
"""
import sys, json, os, numpy as np, soundfile as sf
M = os.environ.get('VIDEO_MODELS') or os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'modelos')
VOICES = {
    'es': ('kokoro', 'ef_dora', 'es', 0.96),
    'en': ('kokoro', 'af_heart', 'en-us', 0.97),
    'fr': ('kokoro', 'ff_siwis', 'fr-fr', 0.96),
    'ru': ('piper', 'vits-piper-ru_RU-dmitri-medium/ru_RU-dmitri-medium', 0, 1.0),
    'uk': ('piper-text', 'vits-piper-uk_UA-ukrainian_tts-medium/uk_UA-ukrainian_tts-medium', 2, 1.0),
    'ar': ('piper', 'vits-piper-ar_JO-kareem-medium/ar_JO-kareem-medium', 0, 1.0),
}
_k = None
_p = {}
def kokoro():
    global _k
    if _k is None:
        from kokoro_onnx import Kokoro
        _k = Kokoro(os.path.join(M, 'kokoro-v1.0.onnx'), os.path.join(M, 'voices-v1.0.bin'))
    return _k
def piper(path):
    if path not in _p:
        import sherpa_onnx
        d = os.path.join(M, os.path.dirname(path))
        cfg = sherpa_onnx.OfflineTtsConfig(model=sherpa_onnx.OfflineTtsModelConfig(vits=sherpa_onnx.OfflineTtsVitsModelConfig(model=os.path.join(M, path + '.onnx'), tokens=os.path.join(d, 'tokens.txt'), data_dir=os.path.join(d, 'espeak-ng-data'), length_scale=1.0, noise_scale=0.6, noise_scale_w=0.75), num_threads=4), max_num_sentences=2)
        _p[path] = sherpa_onnx.OfflineTts(cfg)
    return _p[path]
_t = {}
def piper_text(path, text, sid, speed):
    """A Piper model that reads letters (phoneme_type "text"), run directly: the
    Ukrainian voice. Stress is a combining acute (U+0301) after the stressed vowel."""
    import onnxruntime as ort
    if path not in _t:
        cfg = json.load(open(os.path.join(M, path + '.onnx.json')))
        _t[path] = (ort.InferenceSession(os.path.join(M, path + '.onnx'), providers=['CPUExecutionProvider']), cfg)
    sess, cfg = _t[path]
    idmap = cfg['phoneme_id_map']
    ids = idmap['^'] + idmap['_']
    for ch in text.lower().replace('\u2019', "'").replace('ʼ', "'"):
        if ch in idmap:
            ids += idmap[ch] + idmap['_']
    ids += idmap['$']
    inf = cfg['inference']
    scales = np.array([inf.get('noise_scale', 0.667), inf.get('length_scale', 1.0) / speed, inf.get('noise_w', 0.8)], dtype=np.float32)
    feeds = {'input': np.array([ids], dtype=np.int64), 'input_lengths': np.array([len(ids)], dtype=np.int64), 'scales': scales}
    if cfg.get('num_speakers', 1) > 1:
        feeds['sid'] = np.array([sid], dtype=np.int64)
    audio = sess.run(None, feeds)[0].squeeze()
    return audio.astype(np.float32), cfg['audio']['sample_rate']
def speak(locale, text):
    engine, voice, arg, speed = VOICES[locale]
    if engine == 'piper-text':
        return piper_text(voice, text, arg, speed)
    if engine == 'kokoro':
        samples, sr = kokoro().create(text, voice=voice, speed=speed, lang=arg)
    else:
        a = piper(voice).generate(text, sid=arg, speed=speed)
        samples, sr = np.array(a.samples, dtype=np.float32), a.sample_rate
    return np.asarray(samples, dtype=np.float32), sr
if __name__ == '__main__':
    jobs = [{'locale': sys.argv[1], 'out': sys.argv[2], 'text': sys.argv[3]}] if len(sys.argv) > 3 else [json.loads(l) for l in sys.stdin if l.strip()]
    for j in jobs:
        s, sr = speak(j['locale'], j['text'])
        sf.write(j['out'], s, sr)
        print(j['out'], round(len(s) / sr, 2), sr, flush=True)
