<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use Exception;
use League\OAuth2\Server\CryptKey;

/**
 * Active RSA signing pair for this authorization server.
 *
 * The public half is what the JWKS publishes. The private key is loaded only
 * when something signs, so the JWKS route does not need a private key file.
 */
final class RsaKeyPair implements PublishableJsonWebKey
{
    private RsaPublicJsonWebKey $publicKey;

    private ?string $privateKeyPath;

    private ?string $passphrase;

    /**
     * Store paths only. Reading the private key here would make JWKS require it.
     */
    public function __construct(
        ?string $privateKeyPath = null,
        ?string $publicKeyPath = null,
        ?string $passphrase = null
    ) {
        $this->publicKey = new RsaPublicJsonWebKey($publicKeyPath);
        $this->privateKeyPath = $privateKeyPath;
        $this->passphrase = $passphrase;
    }

    public function isPublished(): bool
    {
        return $this->publicKey->isPublished();
    }

    public function getKeyId(): string
    {
        return $this->publicKey->getKeyId();
    }

    public function getKeyType(): string
    {
        return $this->publicKey->getKeyType();
    }

    public function getUse(): string
    {
        return $this->publicKey->getUse();
    }

    public function getAlgorithm(): string
    {
        return $this->publicKey->getAlgorithm();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->publicKey->toArray();
    }

    /**
     * League private key for signing. Fails if the path is empty, missing, or the passphrase is wrong.
     */
    public function getPrivateKey(): CryptKey
    {
        if ($this->privateKeyPath === null || $this->privateKeyPath === '') {
            throw new OAuth2ConfigurationException(
                'OAuth2 private key is not configured. Set OAUTH2_PRIVATE_KEY_PATH.'
            );
        }

        $passphrase = $this->passphrase === null || $this->passphrase === ''
            ? null
            : $this->passphrase;

        try {
            return new CryptKey(KeyFilePath::resolve($this->privateKeyPath), $passphrase, false);
        } catch (Exception $exception) {
            throw new OAuth2ConfigurationException(
                sprintf(
                    'OAuth2 private key at "%s" is missing or unusable: %s',
                    $this->privateKeyPath,
                    $exception->getMessage()
                ),
                0,
                $exception
            );
        }
    }
}
