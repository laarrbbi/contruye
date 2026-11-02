#!/usr/bin/env bash
# The account screens' video: ten real clips, one per shot, in the order of a
# building site, cut, graded alike and chained into a 27 s loop
# (docs/credits-photos.md lists them with their authors and licences).
#
#   web/scripts/video-chantier.sh <work dir>
#
# The sources are not in the repository (about 400 MB); they are downloaded
# into <work dir>/src. Needs ffmpeg with libx264 and libvpx-vp9, and node with
# sharp (web/node_modules) for the poster. Writes web/public/video/.
set -euo pipefail

WORK=${1:?work directory}
HERE=$(cd "$(dirname "$0")/.." && pwd)
OUT="$HERE/public/video"
mkdir -p "$WORK/src" "$WORK/seg" "$OUT"
cd "$WORK"

L=3.4 # each shot, fades included: 2.7 s seen alone
F=0.7 # each cross-fade

# One look for every shot: blacks pulled to night blue, highlights to amber.
LOOK="curves=r='0/0.02 0.25/0.21 0.5/0.50 0.8/0.84 1/1':g='0/0.04 0.25/0.22 0.5/0.47 0.8/0.76 1/0.92':b='0/0.10 0.25/0.26 0.5/0.44 0.8/0.64 1/0.78',eq=contrast=1.04:saturation=0.9"

# id | source | start (s) | crop (square) | correction before the common look
SHOTS=(
  "5407510|https://videos.pexels.com/video-files/5407510/5407510-uhd_2560_1440_24fps.mp4|10.0|crop=1440:1440:800:0|eq=gamma=0.9:saturation=0.85,"
  "35647503|https://videos.pexels.com/video-files/35647503/15106480_1920_1080_50fps.mp4|2.6|crop=1080:1080:260:0|eq=gamma=0.85:saturation=0.8,"
  "41826|https://cdn.pixabay.com/video/2020/06/12/41826-431406547_large.mp4|1.0|crop=1440:1440:600:0|eq=brightness=-0.04:gamma=0.88:saturation=0.8,"
  "39924253|https://videos.pexels.com/video-files/39924253/17034480_2560_1440_30fps.mp4|0.6|crop=1440:1440:560:0|eq=gamma=0.95,"
  "14134278|https://videos.pexels.com/video-files/14134278/14134278-uhd_2560_1440_25fps.mp4|6.0|crop=1440:1440:560:0|eq=gamma=0.9:saturation=0.85,"
  "9343478|https://videos.pexels.com/video-files/9343478/9343478-uhd_2560_1440_30fps.mp4|4.0|crop=1440:1440:380:0|eq=brightness=-0.06:gamma=0.85:saturation=0.55,"
  "36396548|https://videos.pexels.com/video-files/36396548/15434475_2560_1440_60fps.mp4|2.0|crop=1440:1440:560:0|eq=gamma=0.9:saturation=0.8,"
  "28128921|https://videos.pexels.com/video-files/28128921/12307040_1920_1080_60fps.mp4|12.0|crop=1080:1080:420:0|eq=brightness=-0.03:gamma=0.85:saturation=0.75,"
  "7317305|https://videos.pexels.com/video-files/7317305/7317305-uhd_2560_1440_25fps.mp4|3.0|crop=1440:1440:1000:0|eq=brightness=0.03:gamma=1.2,"
  "18029861|https://videos.pexels.com/video-files/18029861/18029861-uhd_2560_1440_24fps.mp4|20.0|crop=1440:1440:700:0|eq=gamma=1.1,"
)

UA="Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/126 Safari/537.36"
cut() { # out, source file, start, crop, correction, length
  ffmpeg -nostdin -loglevel error -y -ss "$3" -t "$6" -i "$2" -an \
    -vf "$4,scale=1080:1080:flags=lanczos,fps=25,$5$LOOK,format=yuv420p" \
    -c:v libx264 -crf 14 -preset slow "$1"
}

inputs=()
n=0
for shot in "${SHOTS[@]}"; do
  IFS='|' read -r id url start crop fix <<<"$shot"
  [ -s "src/$id.mp4" ] || curl -sSL -A "$UA" -o "src/$id.mp4" "$url"
  cut "seg/$n.mp4" "src/$id.mp4" "$start" "$crop" "$fix" "$L"
  if [ $n -eq 0 ]; then
    # The 0.7 s just before the first shot: the loop's last fade lands on its first frame.
    cut seg/back.mp4 "src/$id.mp4" "$(echo "$start - $F" | bc)" "$crop" "$fix" "$F"
  fi
  inputs+=(-i "seg/$n.mp4")
  n=$((n + 1))
done
inputs+=(-i seg/back.mp4)

# [0][1]xfade…[x1]; [x1][2]xfade…[x2]; … ; [x9][back]xfade…[v]
graph=""
prev="[0]"
for ((i = 1; i <= n; i++)); do
  offset=$(echo "$i * ($L - $F)" | bc)
  label=$([ $i -eq $n ] && echo "v" || echo "x$i")
  graph+="$prev[$i]xfade=transition=fade:duration=$F:offset=$offset[$label];"
  prev="[$label]"
done
ffmpeg -nostdin -loglevel error -y "${inputs[@]}" -filter_complex "${graph%;}" -map "[v]" -c:v libx264 -crf 10 -preset slow -pix_fmt yuv420p master.mp4

ffmpeg -nostdin -loglevel error -y -i master.mp4 -an -c:v libx264 -profile:v high -preset veryslow -crf 28 \
  -maxrate 1.4M -bufsize 2.8M -pix_fmt yuv420p -movflags +faststart -g 50 "$OUT/chantier.mp4"
VP9="-an -c:v libvpx-vp9 -b:v 1.1M -maxrate 1.4M -crf 36 -row-mt 1 -deadline good -cpu-used 2"
ffmpeg -nostdin -loglevel error -y -i master.mp4 $VP9 -pass 1 -f null /dev/null
ffmpeg -nostdin -loglevel error -y -i master.mp4 $VP9 -pass 2 "$OUT/chantier.webm"
rm -f ffmpeg2pass-0.log

# The poster is the loop's first frame, so the video takes over without a jump.
ffmpeg -nostdin -loglevel error -y -i master.mp4 -frames:v 1 first.png
(cd "$HERE" && node -e "require('sharp')(process.argv[1]).resize(1080).webp({quality:62}).toFile(process.argv[2])" "$WORK/first.png" "$OUT/chantier-poster.webp")
ffprobe -v error -show_entries format=duration -of csv=p=0 master.mp4
ls -la "$OUT"
