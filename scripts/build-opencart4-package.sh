#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$ROOT/plugins/opencart4/upload/extension/payxcommerce"
DIST_DIR="$ROOT/dist/plugins"
PACKAGE="$DIST_DIR/payxcommerce.ocmod.zip"

if [[ ! -f "$SOURCE_DIR/install.json" ]]; then
  echo "Missing OpenCart 4 install.json at $SOURCE_DIR/install.json" >&2
  exit 1
fi

mkdir -p "$DIST_DIR"
rm -f "$PACKAGE"

(
  cd "$SOURCE_DIR"
  zip -qr "$PACKAGE" install.json admin catalog system
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
  echo "Package validation failed: extension/ must not be at ZIP root for OpenCart 4 installer." >&2
  exit 1
fi

echo "$PACKAGE"
