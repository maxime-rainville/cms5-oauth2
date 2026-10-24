<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use Archipro\SilverstripeWellKnown\Contracts\JsonWebKey;
use SilverStripe\Control\Director;

/**
 * Publishes one RSA public key for the JWKS document.
 *
 * The default constructor reads a PEM file. A subclass can override
 * loadFromPath() and call loadFromPem() when the key is not a file.
 */
class RsaPublicJsonWebKey implements JsonWebKey
{
    private readonly string $keyId;

    private readonly string $modulus;

    private readonly string $exponent;

    public function __construct(?string $publicKeyPath = null)
    {
        // Bind once in the constructor; the fields are readonly after that.
        $material = $this->loadFromPath($publicKeyPath);
        $this->keyId = $material->keyId;
        $this->modulus = $material->modulus;
        $this->exponent = $material->exponent;
    }

    /**
     * Read the PEM file at this path, or fail when it is empty or unusable.
     *
     * A subclass overrides this when the key is not a file, then calls loadFromPem().
     */
    protected function loadFromPath(?string $publicKeyPath): RsaPublicKeyMaterial
    {
        // A host-registered file key must have a path. Do not resolve an empty one.
        if ($publicKeyPath === null || $publicKeyPath === '') {
            throw new OAuth2ConfigurationException(
                sprintf('OAuth2 public key at "%s" is missing or not readable.', $publicKeyPath ?? '')
            );
        }

        return $this->loadFromPem($this->readPemFile($publicKeyPath, Director::getAbsFile($publicKeyPath)));
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    public function getKeyType(): string
    {
        return 'RSA';
    }

    public function getUse(): string
    {
        return 'sig';
    }

    public function getAlgorithm(): string
    {
        return 'RS256';
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'kty' => $this->getKeyType(),
            'alg' => $this->getAlgorithm(),
            'use' => $this->getUse(),
            'kid' => $this->getKeyId(),
            'n' => $this->modulus,
            'e' => $this->exponent,
        ];
    }

    /**
     * Read PEM bytes, or fail when the path is set but the file is unusable.
     */
    private function readPemFile(string $configuredPath, string $resolvedPath): string
    {
        if (!is_readable($resolvedPath)) {
            throw new OAuth2ConfigurationException(
                sprintf('OAuth2 public key at "%s" is missing or not readable.', $configuredPath)
            );
        }

        $contents = file_get_contents($resolvedPath);
        if ($contents === false || trim($contents) === '') {
            throw new OAuth2ConfigurationException(
                sprintf('OAuth2 public key at "%s" is empty or unreadable.', $configuredPath)
            );
        }

        return $contents;
    }

    /**
     * Parse public JWK fields from PEM bytes. The key id is the start of the PEM hash.
     */
    protected function loadFromPem(string $publicKeyPem): RsaPublicKeyMaterial
    {
        $resource = openssl_pkey_get_public($publicKeyPem);
        if ($resource === false) {
            throw new OAuth2ConfigurationException(
                'Cannot publish JWKS: public key PEM is not usable.'
            );
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new OAuth2ConfigurationException(
                'Cannot publish JWKS: public key must be RSA.'
            );
        }

        return new RsaPublicKeyMaterial(
            substr(hash('sha256', $publicKeyPem), 0, 32),
            $this->base64UrlEncode($details['rsa']['n']),
            $this->base64UrlEncode($details['rsa']['e']),
        );
    }

    /**
     * Encode binary key material as base64url without padding.
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
