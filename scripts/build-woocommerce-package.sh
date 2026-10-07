#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$ROOT/plugins/woocommerce"
DIST_DIR="$ROOT/dist/plugins"
PLUGIN_SLUG="payxcommerce-gateway"
PLUGIN_FILE="$SOURCE_DIR/payxcommerce-gateway.php"
DETECTED_VERSION="$(awk '/^[[:space:]]*[*][[:space:]]*Version:/ { print $3; exit }' "$PLUGIN_FILE")"
VERSION="${1:-$DETECTED_VERSION}"
PACKAGE_BASE="payxcommerce-woocommerce-gateway"
PACKAGE="$DIST_DIR/${PACKAGE_BASE}-${VERSION}.zip"
LATEST="$DIST_DIR/${PACKAGE_BASE}.zip"
STAGE="$(mktemp -d)"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1767225600}"
trap 'rm -rf "$STAGE"' EXIT

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Missing WooCommerce plugin bootstrap at $PLUGIN_FILE" >&2
  exit 1
fi

if [[ -z "$VERSION" ]]; then
  echo "Unable to detect WooCommerce plugin version from $PLUGIN_FILE" >&2
  exit 1
fi

mkdir -p "$DIST_DIR" "$STAGE/$PLUGIN_SLUG"
rsync -a \
  --exclude tests/ \
  --exclude '.git/' \
  --exclude '.DS_Store' \
  "$SOURCE_DIR/" "$STAGE/$PLUGIN_SLUG/"
find "$STAGE/$PLUGIN_SLUG" -exec touch -h -d "@${SOURCE_DATE_EPOCH}" {} +

rm -f "$PACKAGE" "$LATEST"
(
  cd "$STAGE"
  zip -Xqr "$PACKAGE" "$PLUGIN_SLUG"
)
cp "$PACKAGE" "$LATEST"

for zip_file in "$PACKAGE" "$LATEST"; do
  if ! zipinfo -1 "$zip_file" | grep -qx "$PLUGIN_SLUG/payxcommerce-gateway.php"; then
    echo "Package validation failed: $PLUGIN_SLUG/payxcommerce-gateway.php is missing in $zip_file." >&2
    exit 1
  fi

  if zipinfo -1 "$zip_file" | grep -q '^woocommerce/'; then
    echo "Package validation failed: woocommerce/ must never be the ZIP root for the WordPress plugin." >&2
    exit 1
  fi

  if zipinfo -1 "$zip_file" | grep -q '^tests/\|^payxcommerce-gateway/tests/'; then
    echo "Package validation failed: test files should not be shipped in $zip_file." >&2
    exit 1
  fi

done

echo "$PACKAGE"
echo "$LATEST"
sha256sum "$PACKAGE" "$LATEST"
