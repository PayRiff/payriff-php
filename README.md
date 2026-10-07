# Payriff PHP SDK

PHP client for the Payriff merchant API: orders, direct (host-to-host) card payments, saved cards,
transactions, payouts and invoices.

- PHP 8.1 or newer, with the `curl`, `json` and `openssl` extensions
- One dependency: `phpseclib/phpseclib` 3.x (RSA-OAEP with SHA-256 for card encryption)
- Talks to `https://api.payriff.com`

## Installation

Add the GitHub repository to `composer.json`, then require the package:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/PayRiff/payriff-php" }
    ],
    "require": {
        "payriff/payriff-php": "^0.1"
    }
}
```

Then run `composer install`.

> Packagist publishing is coming. After that, the `repositories` entry is no longer needed and
> `composer require payriff/payriff-php` is enough.

## Quick start

```php
use Payriff\Payriff;

$payriff = new Payriff(appKey: getenv('PAYRIFF_APP_KEY'));

$order = $payriff->orders->create([
    'amount' => '10.00',
    'description' => 'Order #1001',
    'callbackUrl' => 'https://shop.example/payriff/callback',
    'requestRrn' => 'order-1001',
]);

// Send the customer to the hosted payment page
header('Location: ' . $order['paymentUrl']);
```

Requests take associative arrays and responses are returned as associative arrays with the field
names of the Payriff API. Create one `Payriff` instance and reuse it.

## Configuration

| Argument | Required | Default | Notes |
|---|---|---|---|
| `appKey` | yes | — | Application key from the Payriff dashboard. |
| `merchantId` | for payouts and invoices | — | Merchant ID (e.g. `ES1000000`). |
| `timeout` | no | `60.0` | Total request timeout in seconds. Bank operations can take tens of seconds. |
| `connectTimeout` | no | `10.0` | Connection timeout in seconds. |
| `cardEncryptionKey` | no | built-in Payriff key | Only if Payriff rotates the card-encryption key. Base64 or PEM. |

Keep the app key in an environment variable or secret store. Never commit it.

## Orders

```php
$created = $payriff->orders->create([
    'amount' => '25.00',
    'currency' => 'AZN',          // default AZN
    'operation' => 'PRE_AUTH',    // default PURCHASE
    'language' => 'AZ',
    'description' => 'Booking #77',
    'callbackUrl' => 'https://shop.example/payriff/callback',
    'metadata' => ['bookingId' => '77'],
]);

$info = $payriff->orders->get($created['orderId']);
$byRef = $payriff->orders->getByRequestRrn('order-1001');   // the requestRrn you sent on create

$payriff->orders->complete(['orderId' => $created['orderId'], 'amount' => '25.00']);
$payriff->orders->refund(['orderId' => $created['orderId'], 'amount' => '5.00', 'refundReason' => 'Partial return']);
$payriff->orders->expire($created['orderId']);              // cancel an unpaid order

$receiptPdf = $payriff->orders->downloadReceipt($created['orderId']);
```

`paymentStatus` is a string such as `APPROVED`, `DECLINED`, `PREAUTH_APPROVED` or `REFUNDED`.

Amounts can be passed as numeric strings (`'10.50'`), integers or floats.

## Direct payments (host-to-host)

You collect the card details yourself, and the SDK encrypts them before sending
(AES-256-GCM + RSA-OAEP). Raw card data never leaves your server in clear text, but your
systems still handle card data, so PCI DSS requirements apply to you.

```php
use Payriff\CardData;

$result = $payriff->payments->directPay([
    'amount' => '1.00',
    'description' => 'Order #1002',
    'callbackUrl' => 'https://shop.example/payriff/callback',
    'requestRrn' => 'order-1002',
    'card' => new CardData(
        pan: '4169 7413 3015 1979',
        cardHolder: 'JOHN DOE',
        expiryMonth: '11',
        expiryYear: '27',
        cvv: '123',
    ),
]);

if (!empty($result['redirect'])) {
    // 3-D Secure: send the customer's browser to $result['redirectUrl'].
    // The final status arrives via your callback or $payriff->orders->get($result['orderId']).
}
```

`CardData` strips spaces and dashes, pads the month (`1` → `01`), expands a 2-digit year (`27` → `2027`)
and rejects malformed values before any network call. It never exposes the card number or CVV
through `print_r`, `var_dump`, `var_export` or `json_encode`, and it cannot be serialized.

### Charging a saved card

```php
$charge = $payriff->payments->autoPay([
    'cardUuid' => $savedCardUuid,
    'amount' => '9.99',
    'description' => 'Monthly subscription',
    'requestRrn' => 'sub-2026-10',
]);
```

## Saved cards

```php
$session = $payriff->cards->save([
    'customerRef' => 'customer-42',
    'callbackUrl' => 'https://shop.example/payriff/card-saved',
    'idempotencyKey' => bin2hex(random_bytes(16)),
]);
// Redirect the customer to $session['paymentUrl'] to verify the card.

$details = $payriff->cards->getSave($session['cardSaveId']);
if ($details['status'] === 'VERIFIED') {
    $cardUuid = $details['cardUuid'];   // store it; use with autoPay
}

$cards = $payriff->cards->list('customer-42');
$payriff->cards->delete($cards[0]['cardUuid']);
```

## Transactions

```php
$page = $payriff->transactions->list([
    'status' => 'APPROVED',
    'from' => '2026-09-01',        // DateTimeInterface or YYYY-MM-DD
    'to' => new DateTimeImmutable('2026-09-30'),
    'page' => 0,
    'size' => 20,                  // server maximum is 20
]);

foreach ($page['content'] as $tx) {
    echo $tx['orderId'], ' ', $tx['amount'], PHP_EOL;
}
```

## Payouts

Payouts require `merchantId` on the client.

```php
$payriff = new Payriff(appKey: getenv('PAYRIFF_APP_KEY'), merchantId: getenv('PAYRIFF_MERCHANT_ID'));

$maskedName = $payriff->payouts->checkCardholder('4169741330151979');   // e.g. "J*** D**"

$payout = $payriff->payouts->create([
    'transferAmount' => '50.00',   // minimum 1
    'description' => 'Refund for order #1001',
    'fullName' => 'JOHN DOE',
    'finCode' => '1AB2C3D',
    'cardPan' => '4169741330151979',
    'requestRrn' => 'payout-1001',
    'idempotencyKey' => 'payout-1001',
]);

$status = $payriff->payouts->getByRequestRrn('payout-1001');
$history = $payriff->payouts->list(['status' => 'SUCCESS']);
$receipt = $payriff->payouts->downloadReceipt('payout-1001');
```

## Invoices

Invoices require `merchantId` on the client.

```php
$invoice = $payriff->invoices->create([
    'amount' => '15.00',
    'fullName' => 'JOHN DOE',
    'phoneNumber' => '+994501234567',
    'description' => 'Consultation',
    'expireDate' => new DateTimeImmutable('+7 days'),
    'sendSms' => true,
]);

$link = $invoice['paymentUrl'];   // share with the customer
$details = $payriff->invoices->get($invoice['invoiceUuid']);
```

## Callbacks

When an order changes state, Payriff POSTs JSON to the `callbackUrl` you set on the order.

```php
use Payriff\Webhook;

$notified = Webhook::parseOrderCallback(file_get_contents('php://input'));   // also accepts a decoded array

// Callbacks are not signed: confirm the state with Payriff before fulfilling.
$confirmed = $payriff->orders->get($notified['orderId']);
if ($confirmed['paymentStatus'] === 'APPROVED') {
    // fulfil the order (make this idempotent: the same callback can arrive more than once)
}
http_response_code(200);
```

## Errors

Every SDK exception extends `Payriff\Exception\PayriffException`, which provides `getHttpStatus()`,
`getResultCode()` (Payriff result code) and `getResponseId()` (quote it when contacting support).

| Exception | When |
|---|---|
| `AuthenticationException` | App key rejected (`14010`, `14013`, `14014`, `14015`) |
| `ValidationException` | Invalid request (`15400` or HTTP 400) |
| `RequestRejectedException` | Business refusal (`01000`), e.g. application under review |
| `InsufficientBalanceException` | Not enough wallet balance for a payout (`01200`) |
| `PayoutLimitException` | Payout limit reached (`01300`, `01400`, `01500`) |
| `ApiException` | Any other failure reported by Payriff |
| `PayriffConnectionException` | No response: network error or timeout |

Payriff can report a failure with HTTP 200. The SDK checks the result code in the body, so you
only need to catch exceptions. Invalid arguments throw `InvalidArgumentException` before any
request is sent.

```php
use Payriff\Exception\PayriffConnectionException;
use Payriff\Exception\PayriffException;
use Payriff\Exception\ValidationException;

try {
    $payriff->orders->refund(['orderId' => $orderId]);
} catch (ValidationException $e) {
    error_log("Refund rejected: {$e->getMessage()} ({$e->getResultCode()})");
} catch (PayriffConnectionException $e) {
    // Outcome unknown: check $payriff->orders->get($orderId) before retrying
} catch (PayriffException $e) {
    error_log("Payriff error {$e->getResultCode()} responseId={$e->getResponseId()}");
}
```

### Retries

The SDK never retries on its own, because payment calls are not safe to repeat blindly. After a
`PayriffConnectionException`, look the operation up first (`orders->getByRequestRrn(...)`,
`payouts->getByRequestRrn(...)`) and retry only if it does not exist. Set `requestRrn` /
`idempotencyKey` on every request so that this lookup is possible.

## Development

```bash
composer install
composer test
```

## License

MIT