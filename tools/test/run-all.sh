#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

php "$ROOT_DIR/packages/php-sdk/tests/run.php"
find "$ROOT_DIR/packages/php-sdk/src" -name '*.php' -print -exec php -l {} \;
find "$ROOT_DIR/examples/raw-php" -name '*.php' -print -exec php -l {} \;
find "$ROOT_DIR/plugins" -name '*.php' -print -exec php -l {} \;

echo "All PayXCommerce integration checks passed."

