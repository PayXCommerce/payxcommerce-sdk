# Testing

## SDK

```bash
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

Full ecommerce smoke tests require WordPress/WooCommerce, OpenCart 3, OpenCart 4, and Magento 2 test installations.

