<?php

namespace Archipro\SilverstripeOAuth2\Tests\Double;

use Archipro\SilverstripeOAuth2\Crypto\SigningKey;
use League\OAuth2\Server\CryptKey;

/**
 * SigningKey stand-in that is not an RsaKeyPair.
 *
 * Use when a test must prove settings accept the interface contract.
 */
final class StubSigningKey implements SigningKey
{
    private CryptKey $privateKey;

    public function __construct()
    {
        $this->privateKey = (new FakeRsaKeyPair())->getPrivateKey();
    }

    public function getPrivateKey(): CryptKey
    {
        return $this->privateKey;
    }

    public function getKeyId(): string
    {
        return 'stub-signing-key';
    }

    public function getAlgorithm(): string
    {
        return 'RS256';
    }
}
