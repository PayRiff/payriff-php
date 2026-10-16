# Changelog

## 0.2.0 (unreleased)

- Added: Saudi Arabia support via `new Payriff(..., region: Region::SAUDI)`. It uses
  `https://api.gccpay.sa` and defaults the currency to `SAR`. Azerbaijan (`AZN`) stays the default.
- Removed `Payriff::PRODUCTION_URL`; use `Region::AZERBAIJAN->baseUrl()`.
- Removed: direct (host-to-host) card payments: `payments->directPay()`, `CardData` and the
  `cardEncryptionKey` option. Use the hosted payment page (`orders->create()`) instead.
- Removed the `phpseclib/phpseclib` dependency and the `openssl` extension requirement.
- `Payriff::VERSION` now matches the released version.

## 0.1.1 (2026-10-15)

- First Packagist release: install with `composer require payriff/payriff-php`.

## 0.1.0 (2026-10-07)

- Initial release: orders, direct and saved-card payments, saved cards, transactions, payouts,
  v2 invoices, order callback parsing.