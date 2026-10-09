<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

use Archipro\SilverstripeWellKnown\Contracts\JsonWebKey;

/**
 * JSON Web Key that can be left out of the JWKS when it has no material.
 */
interface PublishableJsonWebKey extends JsonWebKey
{
    /**
     * Whether this key should appear in the published JWKS.
     */
    public function isPublished(): bool;
}
