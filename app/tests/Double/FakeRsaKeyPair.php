<?php

namespace Archipro\SilverstripeOAuth2\Tests\Double;

use Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair;
use Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey;
use League\OAuth2\Server\CryptKey;
use RuntimeException;

/**
 * Signing pair for settings tests that never read key files.
 *
 * One in-memory RSA pair is reused. The public hook publishes that same key.
 */
class FakeRsaKeyPair extends RsaKeyPair
{
    private static ?string $privatePem = null;

    private static ?string $publicPem = null;

    /**
     * Call the parent constructor. The load hooks ignore the placeholder paths.
     */
    public function __construct()
    {
        self::ensurePair();
        parent::__construct('unused-private', 'unused-public');
    }

    /**
     * Public half of the in-memory pair.
     */
    protected function createPublicKey(?string $publicKeyPath): RsaPublicJsonWebKey
    {
        if (self::$publicPem === null) {
            throw new RuntimeException('In-memory public key is missing.');
        }

        return new FakeRsaPublicJsonWebKey(self::$publicPem);
    }

    /**
     * Private half of the same in-memory pair, as a League key.
     */
    protected function createPrivateKey(?string $privateKeyPath, ?string $passphrase): CryptKey
    {
        if (self::$privatePem === null) {
            throw new RuntimeException('In-memory private key is missing.');
        }

        return new CryptKey(self::$privatePem, null, false);
    }

    /**
     * Public PEM for the in-memory pair, for JWT verify in tests.
     *
     * @return non-empty-string
     */
    public static function getPublicPem(): string
    {
        self::ensurePair();
        if (self::$publicPem === null || self::$publicPem === '') {
            throw new RuntimeException('In-memory public key is missing.');
        }

        return self::$publicPem;
    }

    /**
     * Generate the shared pair once per process.
     */
    private static function ensurePair(): void
    {
        if (self::$privatePem !== null && self::$publicPem !== null) {
            return;
        }

        $private = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($private === false) {
            throw new RuntimeException('Cannot build an in-memory RSA key for tests.');
        }

        if (openssl_pkey_export($private, $privatePem) !== true) {
            throw new RuntimeException('Cannot export an in-memory RSA key for tests.');
        }

        $details = openssl_pkey_get_details($private);
        if ($details === false || !is_string($details['key'] ?? null)) {
            throw new RuntimeException('Cannot read the public half of the in-memory RSA key.');
        }

        self::$privatePem = $privatePem;
        self::$publicPem = $details['key'];
    }
}
