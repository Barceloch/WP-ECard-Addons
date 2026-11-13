#!/usr/bin/env bash
#
# Build an installable WordPress ZIP for WP ECard Addons.
#
# Usage: scripts/build-release.sh
#
# The package contains production code and the production Composer
# dependencies (vendor/). Development files such as tests, fixtures, phase
# records, browser artefacts, upstream IDE metadata and Git data are never
# copied into the staging directory.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="wp-ecard-addons"
MAIN="${SLUG}.php"
DIST="${ROOT}/dist"
ZIP="${DIST}/${SLUG}.zip"
STAGE="$(mktemp -d)"

cleanup() {
	rm -rf "${STAGE}"
}
trap cleanup EXIT

fail() {
	printf 'build-release: %s\n' "$1" >&2
	exit 1
}

printf '==> Staging in %s\n' "${STAGE}"

PLUGIN_DIR="${STAGE}/${SLUG}"
mkdir -p "${PLUGIN_DIR}"

for entry in "${MAIN}" uninstall.php readme.txt composer.json composer.lock \
	includes templates themes assets docs languages; do
	if [ ! -e "${ROOT}/${entry}" ]; then
		fail "missing required entry: ${entry}"
	fi

	cp -R "${ROOT}/${entry}" "${PLUGIN_DIR}/${entry}"
done

if [ ! -f "${ROOT}/vendor/autoload.php" ]; then
	fail 'vendor/autoload.php is missing. Run "composer install --no-dev" before packaging.'
fi

cp -R "${ROOT}/vendor" "${PLUGIN_DIR}/vendor"

printf '==> Validating the plugin main file\n'

grep -q 'Plugin Name:       WP ECard Addons' "${PLUGIN_DIR}/${MAIN}" \
	|| fail 'plugin header not found'
grep -q 'Requires PHP:      7.4' "${PLUGIN_DIR}/${MAIN}" \
	|| fail 'Requires PHP header not found'
grep -q 'Text Domain:       wp-ecard-addons' "${PLUGIN_DIR}/${MAIN}" \
	|| fail 'text domain header not found'

php -l "${PLUGIN_DIR}/${MAIN}" >/dev/null \
	|| fail 'plugin main file has a syntax error'

printf '==> Checking version consistency\n'

HEADER_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "${PLUGIN_DIR}/${MAIN}" | head -1 | tr -d '[:space:]')"
CONST_VERSION="$(sed -n "s/^define( 'MY_ECARD_VERSION', '\([^']*\)' );/\1/p" "${PLUGIN_DIR}/${MAIN}" | head -1)"
README_VERSION="$(sed -n 's/^Stable tag:[[:space:]]*//p' "${PLUGIN_DIR}/readme.txt" | head -1 | tr -d '[:space:]')"

[ -n "${HEADER_VERSION}" ] || fail 'cannot read the Version header'
[ -n "${CONST_VERSION}" ] || fail 'cannot read MY_ECARD_VERSION'
[ -n "${README_VERSION}" ] || fail 'cannot read the readme Stable tag'

[ "${HEADER_VERSION}" = "${CONST_VERSION}" ] \
	|| fail "Version header (${HEADER_VERSION}) and MY_ECARD_VERSION (${CONST_VERSION}) differ. Asset cache busting depends on a single version source."
[ "${HEADER_VERSION}" = "${README_VERSION}" ] \
	|| fail "Version header (${HEADER_VERSION}) and readme Stable tag (${README_VERSION}) differ"

printf '    version %s\n' "${HEADER_VERSION}"

printf '==> Verifying the Composer autoloader\n'

php -r '
require $argv[1];
if ( ! class_exists( "chillerlan\\QRCode\\QRCode" ) ) {
	fwrite( STDERR, "QRCode class not autoloadable\n" );
	exit( 1 );
}
if ( ! class_exists( "chillerlan\\QRCode\\QROptions" ) ) {
	fwrite( STDERR, "QROptions class not autoloadable\n" );
	exit( 1 );
}
' "${PLUGIN_DIR}/vendor/autoload.php" || fail 'Composer autoloader cannot load the QR library'

printf '==> Removing files that must not ship\n'

# Directories that only exist for development or for the source repository.
find "${PLUGIN_DIR}" -type d \
	\( -name '.git' -o -name 'tests' -o -name 'node_modules' -o -name '.idea' \
	-o -name '.vscode' -o -name '.github' -o -name '.circleci' -o -name 'coverage' \) \
	-prune -exec rm -rf {} +

# Files that are development-only, editor metadata, CI configuration or noise.
find "${PLUGIN_DIR}" -type f \
	\( -name '*.log' -o -name '.DS_Store' -o -name 'Thumbs.db' -o -name '*.zip' \
	-o -name '*.neon' -o -name '*.dist' -o -name '.gitignore' -o -name '.gitattributes' \
	-o -name '.editorconfig' -o -name 'phpunit.xml*' -o -name 'phpstan*' \
	-o -name 'psalm*' -o -name '.php-cs-fixer*' -o -name '.travis.yml' \
	-o -name '*.swp' -o -name '*.bak' \) -delete

# Production Composer binaries are never needed at runtime.
if [ -d "${PLUGIN_DIR}/vendor/bin" ]; then
	rm -rf "${PLUGIN_DIR}/vendor/bin"
fi

find "${PLUGIN_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null \
	|| fail 'shipped PHP file has a syntax error'

printf '==> Verifying that no development artefact survived\n'

if find "${PLUGIN_DIR}" -type d \( -name '.idea' -o -name '.vscode' -o -name 'tests' \) | grep -q .; then
	fail 'the staging directory still contains a development directory'
fi

if find "${PLUGIN_DIR}" -type f \( -name '*.neon' -o -name '*.log' -o -name '*.zip' \) | grep -q .; then
	fail 'the staging directory still contains a development file'
fi

mkdir -p "${DIST}"
rm -f "${ZIP}"

printf '==> Normalizing timestamps and file order\n'

# Deterministic archive: fixed mtimes, stable order and no extra fields, so
# two builds of the same sources produce byte-identical ZIP files.
#
# Note: assets are versioned with MY_ECARD_VERSION, never with filemtime(),
# so this normalization cannot freeze the `ver` query argument.
find "${PLUGIN_DIR}" -exec touch -h -d '1980-01-01T00:00:00Z' {} +

printf '==> Creating %s\n' "${ZIP}"

(
	cd "${STAGE}"
	find "${SLUG}" -print | LC_ALL=C sort | zip -X -q "${ZIP}" -@
)

printf '==> Verifying the ZIP structure\n'

LISTING="$(unzip -Z1 "${ZIP}")"

for entry in \
	"${SLUG}/${MAIN}" \
	"${SLUG}/uninstall.php" \
	"${SLUG}/readme.txt" \
	"${SLUG}/composer.json" \
	"${SLUG}/composer.lock" \
	"${SLUG}/vendor/autoload.php" \
	"${SLUG}/vendor/chillerlan/php-qrcode/LICENSE" \
	"${SLUG}/vendor/chillerlan/php-settings-container/LICENSE" \
	"${SLUG}/includes/class-qr-code-generator.php" \
	"${SLUG}/templates/shell.php" \
	"${SLUG}/themes/reference/theme.json" \
	"${SLUG}/assets/js/media-viewer.js" \
	"${SLUG}/docs/theme-development.md" \
	"${SLUG}/languages/README.md"
do
	grep -Fxq "${entry}" <<<"${LISTING}" || fail "missing from ZIP: ${entry}"
done

if grep -Eq "^${SLUG}/(tests|odd|dist|\.git|\.idea)/|\.playwright-mcp|\.log$|\.neon$|\.zip$" <<<"${LISTING}"; then
	fail 'the ZIP contains development files'
fi

printf '==> Done\n'
printf 'ZIP: %s\n' "${ZIP}"
printf 'Size: %s\n' "$(du -h "${ZIP}" | cut -f1)"
printf 'Files: %s\n' "$(unzip -Z1 "${ZIP}" | wc -l | tr -d ' ')"
printf 'SHA-256: %s\n' "$(sha256sum "${ZIP}" | cut -d' ' -f1)"
