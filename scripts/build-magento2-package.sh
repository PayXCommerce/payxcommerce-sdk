#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$ROOT/plugins/magento2"
DIST_DIR="$ROOT/dist/plugins"
VERSION="$(php -r '$data=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $data["version"] ?? "";' "$SOURCE_DIR/composer.json")"
APP_CODE_PACKAGE="$DIST_DIR/payxcommerce-magento2-payment-app-code-${VERSION}.zip"
COMPOSER_PACKAGE="$DIST_DIR/payxcommerce-magento2-payment-composer-${VERSION}.zip"
STAGE="$(mktemp -d)"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1767225600}"
trap 'rm -rf "$STAGE"' EXIT

if [[ -z "$VERSION" ]]; then
  echo "Unable to detect Magento module version from composer.json" >&2
  exit 1
fi

mkdir -p "$DIST_DIR" "$STAGE/app-code/PayXCommerce/Payment" "$STAGE/composer"
rsync -a --exclude README.md "$SOURCE_DIR/" "$STAGE/app-code/PayXCommerce/Payment/"
rsync -a "$SOURCE_DIR/" "$STAGE/composer/"
find "$STAGE" -exec touch -h -d "@${SOURCE_DATE_EPOCH}" {} +
rm -f "$APP_CODE_PACKAGE" "$COMPOSER_PACKAGE"
(
  cd "$STAGE/app-code"
  zip -Xqr "$APP_CODE_PACKAGE" PayXCommerce
)
(
  cd "$STAGE/composer"
  zip -Xqr "$COMPOSER_PACKAGE" .
)
zipinfo -1 "$APP_CODE_PACKAGE" | grep -qx 'PayXCommerce/Payment/registration.php'
zipinfo -1 "$APP_CODE_PACKAGE" | grep -qx 'PayXCommerce/Payment/etc/module.xml'
zipinfo -1 "$COMPOSER_PACKAGE" | grep -qx 'registration.php'
zipinfo -1 "$COMPOSER_PACKAGE" | grep -qx 'etc/module.xml'
zipinfo -1 "$COMPOSER_PACKAGE" | grep -qx 'composer.json'
echo "$APP_CODE_PACKAGE"
echo "$COMPOSER_PACKAGE"
sha256sum "$APP_CODE_PACKAGE" "$COMPOSER_PACKAGE"
