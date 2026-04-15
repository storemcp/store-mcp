#!/usr/bin/env bash
#
# build-screenshots.sh — renders HTML mockups to PNG screenshots for the
# wordpress.org /assets directory.
#
# Uses headless Chrome. Each screenshot is 1280x800 (matches the guideline
# minimum and produces crisp 2x on typical displays).

set -euo pipefail

ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SRC_DIR="${ROOT_DIR}/screenshots-src"
OUT_DIR="${ROOT_DIR}/assets"

CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
if [[ ! -x "${CHROME}" ]]; then
  if command -v chromium >/dev/null 2>&1; then
    CHROME="$(command -v chromium)"
  elif command -v google-chrome >/dev/null 2>&1; then
    CHROME="$(command -v google-chrome)"
  else
    echo "ERROR: Chrome/Chromium not found" >&2
    exit 1
  fi
fi

mkdir -p "${OUT_DIR}"

render() {
  local index="$1"
  local name="$2"
  local src="${SRC_DIR}/${index}-${name}.html"
  local out="${OUT_DIR}/screenshot-${index}.png"

  if [[ ! -f "${src}" ]]; then
    echo "SKIP: ${src} not found" >&2
    return
  fi

  echo "Rendering ${src} → ${out}"
  "${CHROME}" \
    --headless=new \
    --disable-gpu \
    --no-sandbox \
    --hide-scrollbars \
    --force-device-scale-factor=1 \
    --virtual-time-budget=2000 \
    --window-size=1280,800 \
    --screenshot="${out}" \
    --user-data-dir="/tmp/storemcp-chrome-$$" \
    "file://${src}" \
    >/dev/null 2>&1
  rm -rf "/tmp/storemcp-chrome-$$"
}

render 1 dashboard
render 2 settings
render 3 tools
render 4 activity-log
render 5 license

echo
echo "Done. Files written to ${OUT_DIR}:"
ls -la "${OUT_DIR}"/screenshot-*.png
