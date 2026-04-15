#!/usr/bin/env bash
#
# build-zip.sh — packages StoreMCP into a distributable zip.
#
# Outputs: dist/store-mcp-<version>.zip
# The zip contains a single top-level `store-mcp/` folder, which is required
# by both wordpress.org SVN and WordPress's "Upload plugin" installer.
#
# Excludes: VCS, OS junk, dev files, build artifacts, the dist folder itself.

set -euo pipefail

PLUGIN_SLUG="store-mcp"
ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DIST_DIR="${ROOT_DIR}/dist"
STAGE_DIR="${DIST_DIR}/${PLUGIN_SLUG}"

VERSION=$(grep -E "^[[:space:]]*\*[[:space:]]*Version:" "${ROOT_DIR}/${PLUGIN_SLUG}.php" | head -n1 | sed -E 's/.*Version:[[:space:]]*([^[:space:]]+).*/\1/')
if [[ -z "${VERSION}" ]]; then
  echo "ERROR: could not read Version from ${PLUGIN_SLUG}.php" >&2
  exit 1
fi

README_STABLE=$(grep -E "^Stable tag:" "${ROOT_DIR}/readme.txt" | head -n1 | sed -E 's/Stable tag:[[:space:]]*([^[:space:]]+).*/\1/')
if [[ "${README_STABLE}" != "${VERSION}" ]]; then
  echo "ERROR: Version mismatch — ${PLUGIN_SLUG}.php is ${VERSION} but readme.txt Stable tag is ${README_STABLE}" >&2
  exit 1
fi

echo "Building ${PLUGIN_SLUG} v${VERSION}"

rm -rf "${DIST_DIR}"
mkdir -p "${STAGE_DIR}"

# rsync excludes — keep in sync with .distignore if you add one.
rsync -a \
  --exclude=".git" \
  --exclude=".gitignore" \
  --exclude=".gitattributes" \
  --exclude=".github" \
  --exclude=".DS_Store" \
  --exclude="node_modules" \
  --exclude="vendor" \
  --exclude="tests" \
  --exclude="*.log" \
  --exclude="*.swp" \
  --exclude="*.map" \
  --exclude="ARCHITECTURE.md" \
  --exclude="CHANGELOG.md" \
  --exclude="build-zip.sh" \
  --exclude="build-screenshots.sh" \
  --exclude="screenshots-src" \
  --exclude="dist" \
  --exclude="assets" \
  "${ROOT_DIR}/" "${STAGE_DIR}/"

# The /assets/ folder is for wordpress.org SVN (banners, icons, screenshots) —
# it does NOT belong inside the plugin zip.
rm -rf "${STAGE_DIR}/assets"

ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
( cd "${DIST_DIR}" && zip -rq "${ZIP_PATH}" "${PLUGIN_SLUG}" )

SIZE=$(du -h "${ZIP_PATH}" | awk '{print $1}')
echo "OK: ${ZIP_PATH} (${SIZE})"
