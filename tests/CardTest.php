<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\CardData;
use Payriff\Internal\CardEncryptor;
use Payriff\Payriff;
use phpseclib3\Crypt\RSA;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CardTest extends TestCase
{
    private static RSA\PrivateKey $privateKey;

    public static function setUpBeforeClass(): void
    {
        self::$privateKey ??= RSA::createKey(2048);
    }

    public static function publicKeyBase64(): string
    {
        self::$privateKey ??= RSA::createKey(2048);
        $pem = self::$privateKey->getPublicKey()->toString('PKCS8');
        return (string) preg_replace('/-----[^-]+-----|\s/', '', $pem);
    }

    public static function decrypt(string $secretKey, string $encryptedMessage): string
    {
        $keyAndIv = self::$privateKey->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256')
            ->decrypt(base64_decode($secretKey));
        self::assertSame(44, strlen($keyAndIv));
        $data = base64_decode($encryptedMessage);
        $plain = openssl_decrypt(substr($data, 0, -16), 'aes-256-gcm', substr($keyAndIv, 0, 32), OPENSSL_RAW_DATA,
            substr($keyAndIv, 32), substr($data, -16));
        self::assertNotFalse($plain);
        return $plain;
    }

    private static function card(array $override = []): CardData
    {
        $args = $override + ['pan' => '4169 7413 3015 1979', 'cardHolder' => ' JOHN DOE ', 'expiryMonth' => '1', 'expiryYear' => '27', 'cvv' => '123'];
        return new CardData(...$args);
    }

    public function testEncryptedCardDecryptsWithPayriffScheme(): void
    {
        $encrypted = (new CardEncryptor(self::publicKeyBase64()))->encrypt(self::card());

        self::assertSame(
            '{"pan":"4169741330151979","cardHolder":"JOHN DOE","expiryYear":"2027","expiryMonth":"01","cvv":"123"}',
            self::decrypt($encrypted['secretKey'], $encrypted['encryptedMessage']),
        );
    }

    public function testEveryCallUsesFreshKeyMaterial(): void
    {
        $encryptor = new CardEncryptor(self::publicKeyBase64());

        $a = $encryptor->encrypt(self::card());
        $b = $encryptor->encrypt(self::card());

        self::assertNotSame($a['encryptedMessage'], $b['encryptedMessage']);
        self::assertNotSame($a['secretKey'], $b['secretKey']);
    }

    public function testParsesBase64AndPemKeys(): void
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(CardEncryptor::PRODUCTION_CARD_ENCRYPTION_KEY, 64, "\n") . "-----END PUBLIC KEY-----\n";

        self::assertSame(2048, CardEncryptor::parse(CardEncryptor::PRODUCTION_CARD_ENCRYPTION_KEY)->getLength());
        self::assertSame(2048, CardEncryptor::parse($pem)->getLength());
    }

    public function testRejectsInvalidEncryptionKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid RSA public key');

        new Payriff('k', cardEncryptionKey: 'not-a-key');
    }

    public function testNormalizesInput(): void
    {
        $card = new CardData(pan: '4169-7413-3015-1979', cardHolder: 'Rəşad', expiryMonth: 11, expiryYear: 2027, cvv: '1234');

        self::assertSame('{"pan":"4169741330151979","cardHolder":"Rəşad","expiryYear":"2027","expiryMonth":"11","cvv":"1234"}', $card->toJson());
    }

    public static function invalidCards(): array
    {
        return [
            [['pan' => '4169'], 'pan must contain 12-19 digits'],
            [['pan' => '4169741330151979000000'], 'pan must contain 12-19 digits'],
            [['pan' => '4169a41330151979'], 'pan must contain digits only'],
            [['expiryMonth' => '13'], 'expiryMonth must be 1-12'],
            [['expiryMonth' => '0'], 'expiryMonth must be 1-12'],
            [['expiryYear' => '202'], 'expiryYear must have 2 or 4 digits'],
            [['cvv' => '12'], 'cvv must contain 3-4 digits'],
            [['cvv' => ''], 'cvv must contain digits only'],
        ];
    }

    #[DataProvider('invalidCards')]
    public function testRejectsInvalidInput(array $override, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        self::card($override);
    }

    public function testDebugOutputMasksSensitiveData(): void
    {
        $card = self::card();

        $dumped = print_r($card, true);
        ob_start();
        var_dump($card);
        $dumped .= ob_get_clean();
        $dumped .= var_export($card, true) . (string) $card . print_r((array) $card, true) . json_encode($card);

        self::assertStringContainsString('416974******1979', $dumped);
        self::assertStringNotContainsString('4169741330151979', $dumped);
        self::assertStringNotContainsString('123', $dumped);
    }

    public function testCannotBeSerialized(): void
    {
        $this->expectException(\LogicException::class);

        serialize(self::card());
    }
}