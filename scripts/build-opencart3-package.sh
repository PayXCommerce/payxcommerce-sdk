#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$ROOT/plugins/opencart3/upload"
DIST_DIR="$ROOT/dist/plugins"
VERSION="$(tr -d '[:space:]' < "$ROOT/plugins/opencart3/VERSION")"
PACKAGE="$DIST_DIR/payxcommerce-opencart3-gateway-${VERSION}.zip"
STAGE="$(mktemp -d)"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1767225600}"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$DIST_DIR"
rsync -a "$SOURCE_DIR/" "$STAGE/upload/"
find "$STAGE" -exec touch -h -d "@${SOURCE_DATE_EPOCH}" {} +
rm -f "$PACKAGE"
(
  cd "$STAGE"
  zip -Xqr "$PACKAGE" upload
)
zipinfo -1 "$PACKAGE" | grep -qx 'upload/system/library/payxcommerce.php'
zipinfo -1 "$PACKAGE" | grep -qx 'upload/catalog/controller/extension/payment/payxcommerce.php'
echo "$PACKAGE"
sha256sum "$PACKAGE"
