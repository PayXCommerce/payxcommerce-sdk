#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$ROOT/plugins/magento2"
DIST_DIR="$ROOT/dist/plugins"
PACKAGE="$DIST_DIR/payxcommerce-magento2-payment-app-code-0.3.7.zip"
STAGE="$(mktemp -d)"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1767225600}"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$DIST_DIR" "$STAGE/PayXCommerce/Payment"
rsync -a --exclude README.md "$SOURCE_DIR/" "$STAGE/PayXCommerce/Payment/"
find "$STAGE" -exec touch -h -d "@${SOURCE_DATE_EPOCH}" {} +
rm -f "$PACKAGE"
(
  cd "$STAGE"
  zip -Xqr "$PACKAGE" PayXCommerce
)
zipinfo -1 "$PACKAGE" | grep -qx 'PayXCommerce/Payment/registration.php'
zipinfo -1 "$PACKAGE" | grep -qx 'PayXCommerce/Payment/etc/module.xml'
echo "$PACKAGE"
sha256sum "$PACKAGE"
