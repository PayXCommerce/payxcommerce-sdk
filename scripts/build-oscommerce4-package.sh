#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="$repo_root/plugins/oscommerce4/upload"
output_dir="$repo_root/dist/plugins"
package="$output_dir/payxcommerce-oscommerce4.zip"
stage="$(mktemp -d)"
source_date_epoch="${SOURCE_DATE_EPOCH:-1767225600}"
trap 'rm -rf "$stage"' EXIT

php "$repo_root/plugins/oscommerce4/tests/standalone.php"
mkdir -p "$output_dir"
cp -a "$source_dir/lib" "$stage/lib"
find "$stage" -exec touch -h -d "@$source_date_epoch" {} +
(
    cd "$stage"
    zip -Xqr "$stage/package.zip" lib
)
for path in \
    lib/common/modules/orderPayment/payxcommerce.php \
    lib/common/modules/orderPayment/lib/PayxClient.php; do
    zipinfo -1 "$stage/package.zip" | grep -qx "$path"
done
mv "$stage/package.zip" "$package"
echo "$package"
sha256sum "$package"
