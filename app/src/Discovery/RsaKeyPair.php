<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

use Archipro\SilverstripeOAuth2\Crypto\SigningKey;
use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use Archipro\SilverstripeWellKnown\Contracts\JsonWebKey;
use Exception;
use League\OAuth2\Server\CryptKey;
use SilverStripe\Control\Director;

/**
 * RSA signing key for access tokens and the matching public JWK for the JWKS.
 *
 * The public half loads in the constructor so JWKS can publish without opening
 * the private file. The private half loads on the first getPrivateKey() call.
 * A subclass may replace createPublicKey() and createPrivateKey() and still
 * call this constructor. The default JWKS list includes this service; hosts
 * override that list to add older public keys or to publish a different set.
 */
class RsaKeyPair implements JsonWebKey, SigningKey
{
    private RsaPublicJsonWebKey $publicKey;

    private ?CryptKey $privateKey = null;

    private ?string $privateKeyPath;

    private ?string $passphrase;

    /**
     * Load the public half. Store private path and passphrase for later load.
     */
    public function __construct(
        ?string $privateKeyPath = null,
        ?string $publicKeyPath = null,
        ?string $passphrase = null
    ) {
        $this->publicKey = $this->createPublicKey($publicKeyPath);
        $this->privateKeyPath = $privateKeyPath;
        $this->passphrase = $passphrase;
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
     * League private key for signing. Loads once on first call.
     */
    public function getPrivateKey(): CryptKey
    {
        if ($this->privateKey === null) {
            $this->privateKey = $this->createPrivateKey($this->privateKeyPath, $this->passphrase);
        }

        return $this->privateKey;
    }

    /**
     * Read the public PEM, or fail when the path is empty or the file is bad.
     */
    protected function createPublicKey(?string $publicKeyPath): RsaPublicJsonWebKey
    {
        if ($publicKeyPath === null || $publicKeyPath === '') {
            throw new OAuth2ConfigurationException(
                'OAuth2 public key is not configured. Set OAUTH2_PUBLIC_KEY_PATH.'
            );
        }

        return new RsaPublicJsonWebKey($publicKeyPath);
    }

    /**
     * Read the private PEM into a League key, or fail when the path or passphrase is bad.
     *
     * League CryptKey accepts a public PEM on the private path
     * (`openssl_pkey_get_private ?: openssl_pkey_get_public`). This method
     * requires a true private key first, then hands the PEM bytes to CryptKey
     * so the file is not read twice.
     */
    protected function createPrivateKey(?string $privateKeyPath, ?string $passphrase): CryptKey
    {
        if ($privateKeyPath === null || $privateKeyPath === '') {
            throw new OAuth2ConfigurationException(
                'OAuth2 private key is not configured. Set OAUTH2_PRIVATE_KEY_PATH.'
            );
        }

        // CryptKey wants null when the PEM has no passphrase; OpenSSL wants ''.
        $cryptKeyPassphrase = $passphrase === null || $passphrase === '' ? null : $passphrase;
        $opensslPassphrase = $cryptKeyPassphrase ?? '';

        $absolutePath = Director::getAbsFile($privateKeyPath);
        $pem = $this->readPrivatePemFile($privateKeyPath, $absolutePath);
        $this->assertPrivatePemDecrypts($privateKeyPath, $pem, $opensslPassphrase);

        try {
            // Skip the mode check. Fixture keys and some host files are not mode 600.
            return new CryptKey($pem, $cryptKeyPassphrase, false);
        } catch (Exception $exception) {
            throw $this->unusablePrivateKey($privateKeyPath, $exception->getMessage(), $exception);
        }
    }

    /**
     * Read private PEM bytes, or fail when the path is set but the file is unusable.
     */
    private function readPrivatePemFile(string $configuredPath, string $resolvedPath): string
    {
        if (!is_readable($resolvedPath)) {
            throw $this->unusablePrivateKey($configuredPath, 'file is missing or not readable');
        }

        $contents = file_get_contents($resolvedPath);
        if ($contents === false || trim($contents) === '') {
            throw $this->unusablePrivateKey($configuredPath, 'file is empty or unreadable');
        }

        return $contents;
    }

    /**
     * Fail when OpenSSL cannot load the PEM as a private key with this passphrase.
     *
     * Rejects public-only PEMs that League CryptKey would otherwise accept.
     */
    private function assertPrivatePemDecrypts(
        string $configuredPath,
        string $pem,
        string $opensslPassphrase
    ): void {
        $this->drainOpensslErrors();

        if (openssl_pkey_get_private($pem, $opensslPassphrase) !== false) {
            return;
        }

        $opensslError = openssl_error_string();
        throw $this->unusablePrivateKey(
            $configuredPath,
            is_string($opensslError) && $opensslError !== ''
                ? $opensslError
                : 'could not load private key'
        );
    }

    /**
     * Clear the OpenSSL error queue so the next failure reports this call.
     */
    private function drainOpensslErrors(): void
    {
        while (openssl_error_string() !== false) {
        }
    }

    /**
     * Build the shared configuration error for an unusable private key path.
     */
    private function unusablePrivateKey(
        string $configuredPath,
        string $reason,
        ?Exception $previous = null
    ): OAuth2ConfigurationException {
        return new OAuth2ConfigurationException(
            sprintf(
                'OAuth2 private key at "%s" is missing or unusable: %s',
                $configuredPath,
                $reason
            ),
            0,
            $previous
        );
    }
}
