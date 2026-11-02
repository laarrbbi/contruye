#!/usr/bin/env bash
# One still per clip of the construction loop (public/video/chantier.mp4), for
# the ring of the sales page (S5, Journal). The loop strings ten licensed clips
# (docs/credits-photos.md); each lasts 3.4 s and is seen alone from 0.7 s to
# 2.7 s after it starts, so the still is taken 1.75 s in: no crossfade in it.
# Run from web/: bash scripts/fotogramas-obra.sh
set -euo pipefail
mkdir -p public/photos
for k in 0 1 2 3 4 5 6 7 8 9; do
  t=$(awk "BEGIN { print $k * 2.7 + 1.75 }")
  n=$(printf '%02d' $((k + 1)))
  ffmpeg -v error -y -ss "$t" -i public/video/chantier.mp4 -frames:v 1 -vf scale=900:900 -c:v libwebp -quality 74 "public/photos/obra-$n.webp"
  echo "public/photos/obra-$n.webp ($t s)"
done
