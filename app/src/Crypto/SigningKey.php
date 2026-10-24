<?php

namespace Archipro\SilverstripeOAuth2\Crypto;

use League\OAuth2\Server\CryptKey;

/** The key the authorisation server signs access tokens with. */
interface SigningKey
{
    /** Private key in the form League's AuthorizationServer accepts. */
    public function getPrivateKey(): CryptKey;

    /** Key id for the JWT header, matching this key's JWKS entry. */
    public function getKeyId(): string;

    /** JWS algorithm used to sign, e.g. RS256. */
    public function getAlgorithm(): string;
}
