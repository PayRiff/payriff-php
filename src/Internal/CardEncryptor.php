<?php

declare(strict_types=1);

namespace Payriff\Internal;

use Payriff\CardData;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

final class CardEncryptor
{
    public const PRODUCTION_CARD_ENCRYPTION_KEY =
        'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxRq5a+44T6Dac60XmVRQ/7cpPyFsBnamXbJlRVJk8CnES5Re5tVMohyD0hZr'
        . '3zcQj+bxodYB4zZpQTPlrXvFBC3zz+rXnGlevxBQ6W2d3QC9q8vWH8p3ZOwTO3qVvDSHH9o+hMMRNbJ7kueq/KZlX/F+bjZ23CZw7iXE'
        . 'GQT3HYVYnnHsvpaguYDteWBag2sPPLLsVjeB3zhTfQ7OsWp5XTkDuRwLugHPvs6RHLcwGCnodukWyvwUaEUQR/kMGC+RbMsAIVkcLMP5'
        . 'csfR3Xo7Gi98+i44iLN00f7gE8QvEmvv8xDspyTAjDEL1a5gK7TijJ3yLG/Bwa1rr1uskYy7lwIDAQAB';

    private const AES_KEY_BYTES = 32;
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    private readonly RSA\PublicKey $publicKey;

    public function __construct(string $base64OrPem)
    {
        $this->publicKey = self::parse($base64OrPem)
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');
    }

    public static function parse(string $base64OrPem): RSA\PublicKey
    {
        $body = preg_replace('/\s/', '', str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'], '', $base64OrPem));
        $der = base64_decode((string) $body, true);
        try {
            $key = $der === false || $der === '' ? null : PublicKeyLoader::load($der);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Invalid RSA public key', 0, $e);
        }
        if (!$key instanceof RSA\PublicKey) {
            throw new \InvalidArgumentException('Invalid RSA public key');
        }
        return $key;
    }

    public function encrypt(CardData $card): array
    {
        $aesKey = random_bytes(self::AES_KEY_BYTES);
        $iv = random_bytes(self::IV_BYTES);
        $plaintext = $card->toJson();
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);
        if ($ciphertext === false) {
            throw new \RuntimeException('Card encryption failed');
        }
        $secret = $this->publicKey->encrypt($aesKey . $iv);
        $result = [
            'encryptedMessage' => base64_encode($ciphertext . $tag),
            'secretKey' => base64_encode($secret),
        ];
        if (function_exists('sodium_memzero')) {
            sodium_memzero($aesKey);
            sodium_memzero($iv);
            sodium_memzero($plaintext);
        }
        return $result;
    }
}