# Testing

## SDK

```bash
tools/test/run-all.sh
php packages/php-sdk/tests/run.php
find packages/php-sdk/src -name '*.php' -print -exec php -l {} \;
```

## Raw PHP Examples

```bash
find examples/raw-php -name '*.php' -print -exec php -l {} \;
```

## Plugins

```bash
find plugins -name '*.php' -print -exec php -l {} \;
```

Full ecommerce acceptance tests should be run in WordPress/WooCommerce, OpenCart 3, OpenCart 4, and Magento 2 staging stores before enabling live processing.
