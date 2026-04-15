#!/usr/bin/env bash
#
# build-zip.sh — packages StoreMCP into a distributable zip.
#
# Usage:
#   build-zip.sh            → full build (self-hosted, with updater)
#   build-zip.sh --wporg    → wordpress.org build (WITHOUT updater; wp.org
#                             forbids plugin-bundled update mechanisms).
#
# Outputs:
#   dist/store-mcp-<version>.zip         (full)
#   dist/store-mcp-<version>-wporg.zip   (wordpress.org)
#
# Both zips contain a single top-level `store-mcp/` folder.

set -euo pipefail

PLUGIN_SLUG="store-mcp"
ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DIST_DIR="${ROOT_DIR}/dist"
STAGE_DIR="${DIST_DIR}/${PLUGIN_SLUG}"

VARIANT="full"
SUFFIX=""
if [[ "${1:-}" == "--wporg" ]]; then
  VARIANT="wporg"
  SUFFIX="-wporg"
fi

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

echo "Building ${PLUGIN_SLUG} v${VERSION} (${VARIANT})"

# Always rebuild stage from scratch.
rm -rf "${STAGE_DIR}"
mkdir -p "${STAGE_DIR}"

RSYNC_EXCLUDES=(
  --exclude=".git"
  --exclude=".gitignore"
  --exclude=".gitattributes"
  --exclude=".github"
  --exclude=".DS_Store"
  --exclude="node_modules"
  --exclude="vendor"
  --exclude="tests"
  --exclude="*.log"
  --exclude="*.swp"
  --exclude="*.map"
  --exclude="ARCHITECTURE.md"
  --exclude="CHANGELOG.md"
  --exclude="CODE_OF_CONDUCT.md"
  --exclude="LICENSE"
  --exclude="README.md"
  --exclude="build-zip.sh"
  --exclude="build-screenshots.sh"
  --exclude="screenshots-src"
  --exclude="dist"
  --exclude="assets"
  --exclude="phpcs.xml"
  --exclude="phpcs.xml.dist"
  --exclude="composer.json"
  --exclude="composer.lock"
)

# wordpress.org forbids plugin-bundled updaters. Strip the updater from that
# build so the automated scanner doesn't flag hook references to
# pre_set_site_transient_update_plugins.
if [[ "${VARIANT}" == "wporg" ]]; then
  RSYNC_EXCLUDES+=( --exclude="class-mcp-updater.php" )
fi

rsync -a "${RSYNC_EXCLUDES[@]}" "${ROOT_DIR}/" "${STAGE_DIR}/"

# Double-check: no /assets/ inside the plugin zip. /assets/ is SVN-only.
rm -rf "${STAGE_DIR}/assets"

ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}${SUFFIX}.zip"
rm -f "${ZIP_PATH}"
( cd "${DIST_DIR}" && zip -rq "${ZIP_PATH}" "${PLUGIN_SLUG}" )

SIZE=$(du -h "${ZIP_PATH}" | awk '{print $1}')
echo "OK: ${ZIP_PATH} (${SIZE})"
