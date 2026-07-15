#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$ROOT/plugins/woocommerce"
DIST_DIR="$ROOT/dist/plugins"
PLUGIN_SLUG="payxcommerce-gateway"
VERSION="${1:-0.3.4}"
PACKAGE_BASE="payxcommerce-woocommerce-gateway"
PACKAGE="$DIST_DIR/${PACKAGE_BASE}-${VERSION}.zip"
LATEST="$DIST_DIR/${PACKAGE_BASE}.zip"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

if [[ ! -f "$SOURCE_DIR/payxcommerce-gateway.php" ]]; then
  echo "Missing WooCommerce plugin bootstrap at $SOURCE_DIR/payxcommerce-gateway.php" >&2
  exit 1
fi

mkdir -p "$DIST_DIR" "$STAGE/$PLUGIN_SLUG"
rsync -a \
  --exclude tests/ \
  --exclude '.git/' \
  --exclude '.DS_Store' \
  "$SOURCE_DIR/" "$STAGE/$PLUGIN_SLUG/"

rm -f "$PACKAGE" "$LATEST"
(
  cd "$STAGE"
  zip -qr "$PACKAGE" "$PLUGIN_SLUG"
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
