#!/usr/bin/env sh
set -eu

ROOT="${1:-.}"

REQUIRED_DIRS="
$ROOT/system/library
$ROOT/admin/controller/extension/payment
$ROOT/admin/language/en-gb/extension/payment
$ROOT/admin/view/image/payment
$ROOT/admin/view/template/extension/payment
$ROOT/catalog/controller/extension/payment
$ROOT/catalog/language/en-gb/extension/payment
$ROOT/catalog/model/extension/payment
$ROOT/catalog/view/theme/default/template/extension/payment
$ROOT/system/storage/upload
"

FAILED=0
for DIR in $REQUIRED_DIRS; do
  if [ ! -d "$DIR" ]; then
    echo "MISSING: $DIR"
    FAILED=1
    continue
  fi

  if [ ! -w "$DIR" ]; then
    echo "NOT WRITABLE: $DIR"
    FAILED=1
  else
    echo "OK: $DIR"
  fi
done

if [ "$FAILED" -ne 0 ]; then
  printf '\n%s\n' "OpenCart installer cannot copy PayXCommerce files until the paths above are writable by the PHP/web-server user." >&2
  exit 1
fi

printf '\n%s\n' "OpenCart 3 permission preflight passed."
