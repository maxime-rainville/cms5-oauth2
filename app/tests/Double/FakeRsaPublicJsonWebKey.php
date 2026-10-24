<?php

namespace Archipro\SilverstripeOAuth2\Tests\Double;

use Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey;
use Archipro\SilverstripeOAuth2\Discovery\RsaPublicKeyMaterial;

/**
 * Public half of an in-memory signing pair. Does not read a PEM file.
 */
class FakeRsaPublicJsonWebKey extends RsaPublicJsonWebKey
{
    private string $pem;

    /**
     * Publish this PEM without reading a file.
     */
    public function __construct(string $pem)
    {
        $this->pem = $pem;
        // Null is not a path. loadFromPath() publishes $this->pem instead.
        parent::__construct(null);
    }

    /**
     * Publish the in-memory PEM instead of reading a path.
     */
    protected function loadFromPath(?string $publicKeyPath): RsaPublicKeyMaterial
    {
        return $this->loadFromPem($this->pem);
    }
}
