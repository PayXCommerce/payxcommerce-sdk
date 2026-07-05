#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

php "$ROOT_DIR/packages/php-sdk/tests/run.php"
find "$ROOT_DIR/packages/php-sdk/src" -name '*.php' -print -exec php -l {} \;
find "$ROOT_DIR/examples/raw-php" "$ROOT_DIR/examples/sdk-php" "$ROOT_DIR/packages/php-sdk/examples" -name '*.php' -print -exec php -l {} \;
if command -v python3 >/dev/null 2>&1; then
  python3 "$ROOT_DIR/packages/python-sdk/tests/run.py"
  find "$ROOT_DIR/packages/python-sdk" "$ROOT_DIR/examples/raw-python" "$ROOT_DIR/examples/sdk-python" -name '*.py' -print -exec python3 -m py_compile {} \;
fi
if command -v node >/dev/null 2>&1; then
  node "$ROOT_DIR/packages/node-sdk/test/run.js"
  find "$ROOT_DIR/packages/node-sdk" "$ROOT_DIR/examples/raw-nodejs" "$ROOT_DIR/examples/sdk-nodejs" -name '*.js' -print -exec node --check {} \;
fi
find "$ROOT_DIR/plugins" -name '*.php' -print -exec php -l {} \;
if command -v xmllint >/dev/null 2>&1; then
  find "$ROOT_DIR/plugins" -name '*.xml' -print -exec xmllint --noout {} \;
fi

echo "All PayXCommerce integration checks passed."
