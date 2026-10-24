<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

/**
 * Parsed public RSA JWK fields bound once when a public key is loaded.
 */
final readonly class RsaPublicKeyMaterial
{
    public function __construct(
        public string $keyId,
        public string $modulus,
        public string $exponent,
    ) {
    }
}
