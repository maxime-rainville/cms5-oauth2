<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;

/**
 * Publishes one RSA public key from a PEM file for the JWKS document.
 *
 * Pass a filesystem path. An empty path means this key is left out of the set.
 */
final class RsaPublicJsonWebKey implements PublishableJsonWebKey
{
    private bool $published = false;

    private string $keyId = '';

    private string $modulus = '';

    private string $exponent = '';

    /**
     * @param string|null $publicKeyPath Filesystem path to a PEM public key. Empty means unpublished.
     */
    public function __construct(?string $publicKeyPath = null)
    {
        // Unset env and an empty path are the CI case: nothing to publish.
        if ($publicKeyPath === null || $publicKeyPath === '') {
            return;
        }

        $pem = $this->readPemFile($publicKeyPath, KeyFilePath::resolve($publicKeyPath));
        $this->loadRsaFields($pem);
        $this->published = true;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function getKeyId(): string
    {
        $this->assertPublished();

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
        $this->assertPublished();

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
     * Store the public JWK fields from PEM bytes. The key id is the start of the PEM hash.
     */
    private function loadRsaFields(string $publicKeyPem): void
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

        $this->modulus = $this->base64UrlEncode($details['rsa']['n']);
        $this->exponent = $this->base64UrlEncode($details['rsa']['e']);
        $this->keyId = substr(hash('sha256', $publicKeyPem), 0, 32);
    }

    private function assertPublished(): void
    {
        if (!$this->published) {
            throw new OAuth2ConfigurationException(
                'Cannot publish JWKS: public key path is empty.'
            );
        }
    }

    /**
     * Encode binary key material as base64url without padding.
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
