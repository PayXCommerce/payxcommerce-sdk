#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$ROOT/plugins/opencart4/upload/extension/payxcommerce"
DIST_DIR="$ROOT/dist/plugins"
PACKAGE="$DIST_DIR/payxcommerce.ocmod.zip"
STAGE="$(mktemp -d)"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1767225600}"
trap 'rm -rf "$STAGE"' EXIT

if [[ ! -f "$SOURCE_DIR/install.json" ]]; then
  echo "Missing OpenCart 4 install.json at $SOURCE_DIR/install.json" >&2
  exit 1
fi

mkdir -p "$DIST_DIR"
rm -f "$PACKAGE" "$DIST_DIR"/payxcommerce-opencart4-gateway-*.ocmod.zip
rsync -a "$SOURCE_DIR/" "$STAGE/"
find "$STAGE" -exec touch -h -d "@${SOURCE_DATE_EPOCH}" {} +

(
  cd "$STAGE"
  zip -Xqr "$PACKAGE" install.json admin catalog system
)

if ! zipinfo -1 "$PACKAGE" | grep -qx 'install.json'; then
  echo "Package validation failed: install.json is not at ZIP root." >&2
  exit 1
fi

for required in admin catalog system; do
  if ! zipinfo -1 "$PACKAGE" | grep -q "^${required}/"; then
    echo "Package validation failed: ${required}/ is not at ZIP root." >&2
    exit 1
  fi
done

if zipinfo -1 "$PACKAGE" | grep -q '^extension/'; then
  echo "Package validation failed: extension/ must not be at ZIP root because OpenCart 4 prepends the extension code while extracting." >&2
  exit 1
fi

if ! zipinfo -1 "$PACKAGE" | grep -qx 'system/library/payxcommerce.php'; then
  echo "Package validation failed: system/library/payxcommerce.php is missing." >&2
  exit 1
fi

echo "$PACKAGE"
sha256sum "$PACKAGE"
