#!/usr/bin/env bash
# Fetches the open voice models into ../modelos (or $VIDEO_MODELS). GitHub releases only.
set -euo pipefail
dir="${VIDEO_MODELS:-$(cd "$(dirname "$0")/.." && pwd)/modelos}"
mkdir -p "$dir" && cd "$dir"
base=https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0
[ -f kokoro-v1.0.onnx ] || curl -sSLO "$base/kokoro-v1.0.onnx"
[ -f voices-v1.0.bin ] || curl -sSLO "$base/voices-v1.0.bin"
for m in vits-piper-ru_RU-dmitri-medium vits-piper-uk_UA-ukrainian_tts-medium vits-piper-ar_JO-kareem-medium; do
  [ -d "$m" ] || { curl -sSL "https://github.com/k2-fsa/sherpa-onnx/releases/download/tts-models/$m.tar.bz2" | tar xj; }
done
echo "models in $dir"
