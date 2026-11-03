# Vídeo del Módulo 1 · El solar

The Module 1 introduction film, in the six languages of the platform. Nothing in it is generated
by an AI image or video model: the picture is drawn frame by frame from code (`composicion/`, in
Hani's palette and type), the narration is read by open text-to-speech models running on the
machine, and the music and sounds are synthesised (`herramientas/mezcla.py`).

What is said comes from `curso/1_contenido/modulos/01_…md` and, for the other languages, from the
course translations in `traducciones/`: no figure, law or claim that the module does not contain.

| Step | Command (from this folder) | Output, in `salida/<locale>/` |
|---|---|---|
| Voice models (once) | `herramientas/modelos.sh` | `modelos/` |
| Narration and timeline | `python herramientas/voz.py <locale>` | `voz/`, `voz.wav`, `linea-de-tiempo.json` |
| Sound mix | `python herramientas/mezcla.py <locale>` | `mezcla.wav` (−16 LUFS) |
| Film | `node herramientas/render.mjs <locale>` | `modulo-1-<locale>.mp4` (1080p, 30 fps) |
| Stills to check a frame | `node herramientas/render.mjs <locale> --still 12 48.5` | `fotos/` |

Python needs `kokoro-onnx`, `sherpa-onnx<2`, `soundfile` and `numpy`; Node uses the Playwright
installed with Chromium; `ffmpeg` encodes.

The picture follows the voice: `voz.py` lays the lines out in time and every animation takes its
beats from that timeline. A better voice (a recorded narrator, or a paid service) only needs its
clips in place of the generated ones, then `mezcla.py` and `render.mjs` again.

## Voices

| Locale | Model | Licence |
|---|---|---|
| es, en, fr | Kokoro 82M v1.0 (`ef_dora`, `af_heart`, `ff_siwis`) | Apache-2.0 |
| ru | Piper `ru_RU-dmitri-medium` | dataset CC0 |
| uk | Piper `uk_UA-ukrainian_tts-medium`, speaker `tetiana` | dataset CC0 |
| ar | Piper `ar_JO-kareem-medium` | **dataset licence not stated by its author**: check, or replace the voice, before a commercial launch |

The Ukrainian voice reads letters, with the stress marked in `guion/uk.json` (U+0301); the Arabic
script is fully vocalised. Spoken lines may differ from the on-screen words where a voice cannot
say an acronym (PGOU → «el plan general»).

## Putting it in the course

The film opens Module 1, so it belongs to its first lesson (Lección 1.1). Upload the six files to
Bunny Stream, then in **Admin → Cursos → contenido → Lección 1.1**:

- **ID del vídeo en Bunny**: the Spanish film's video id (`modulo-1-es.mp4`);
- **Vídeo en otros idiomas**: one id per language (`modulo-1-en.mp4`, `-fr`, `-ar`, `-ru`, `-uk`).

A student gets the film in their language; a language left empty plays the Spanish one, and the
player says so. Playback stays behind the access gate (`PlaybackController`), as for every lesson.
