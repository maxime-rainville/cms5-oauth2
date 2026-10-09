<?php

namespace Archipro\SilverstripeOAuth2\Settings;

use Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair;
use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use DateInterval;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key as DefuseKey;
use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

/**
 * Default OAuth2 settings.
 *
 * The signing pair, encryption key, and issuer are injected from YAML.
 * Path prefix and token lifetimes keep their PHP defaults when the env var is unset.
 */
class EnvironmentOAuth2Settings implements OAuth2SettingsInterface
{
    use Configurable;
    use Injectable;

    private static string $path_prefix = '/oauth';

    private static string $access_token_ttl = 'PT1H';

    private static string $refresh_token_ttl = 'P1M';

    private RsaKeyPair $keyPair;

    private string $encryptionKey;

    private string $issuer;

    public function __construct(
        RsaKeyPair $keyPair,
        ?string $encryptionKey = null,
        ?string $issuer = null
    ) {
        $this->keyPair = $keyPair;
        $this->encryptionKey = $encryptionKey ?? '';
        $this->issuer = $issuer ?? '';
    }

    public function getKeyPair(): RsaKeyPair
    {
        return $this->keyPair;
    }

    public function getEncryptionKey(): string
    {
        if ($this->encryptionKey === '') {
            throw new OAuth2ConfigurationException(
                'OAuth2 encryption key is not configured. Set OAUTH2_ENCRYPTION_KEY.'
            );
        }

        try {
            DefuseKey::loadFromAsciiSafeString($this->encryptionKey);
        } catch (BadFormatException | EnvironmentIsBrokenException $exception) {
            throw new OAuth2ConfigurationException(
                'OAuth2 encryption key is not a valid Defuse ascii-safe string. '
                . 'Generate one with vendor/bin/generate-defuse-key.',
                0,
                $exception
            );
        }

        return $this->encryptionKey;
    }

    public function getIssuer(): string
    {
        if ($this->issuer === '') {
            throw new OAuth2ConfigurationException(
                'OAuth2 issuer is not configured. Set OAUTH2_ISSUER '
                . '(usually an absolute URL such as https://example.com).'
            );
        }

        return $this->issuer;
    }

    public function getPathPrefix(): string
    {
        $prefix = $this->resolveString('OAUTH2_PATH_PREFIX', 'path_prefix');
        if ($prefix === '' || !str_starts_with($prefix, '/')) {
            throw new OAuth2ConfigurationException(
                'OAuth2 path prefix must be a non-empty path starting with "/". '
                . 'Set OAUTH2_PATH_PREFIX or YAML path_prefix.'
            );
        }

        return $prefix;
    }

    public function getAccessTokenTTL(): DateInterval
    {
        return $this->parseTTL(
            $this->resolveString('OAUTH2_ACCESS_TOKEN_TTL', 'access_token_ttl'),
            'access token'
        );
    }

    public function getRefreshTokenTTL(): DateInterval
    {
        return $this->parseTTL(
            $this->resolveString('OAUTH2_REFRESH_TOKEN_TTL', 'refresh_token_ttl'),
            'refresh token'
        );
    }

    public function assertValid(): void
    {
        $this->keyPair->getPrivateKey();
        $this->getEncryptionKey();
        $this->getIssuer();
        $this->getPathPrefix();
        $this->getAccessTokenTTL();
        $this->getRefreshTokenTTL();
    }

    /**
     * Prefer a non-empty env value, otherwise the PHP/YAML default.
     *
     * Used only for path prefix and token lifetimes. A backtick would replace
     * those defaults with an empty string when the variable is unset.
     */
    private function resolveString(string $envName, string $configKey): string
    {
        $fromEnv = Environment::getEnv($envName);
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        $fromConfig = $this->config()->get($configKey);
        if (is_string($fromConfig)) {
            return $fromConfig;
        }

        return '';
    }

    /**
     * Parse an ISO-8601 duration into a DateInterval with a clear error.
     */
    private function parseTTL(string $value, string $label): DateInterval
    {
        if ($value === '') {
            throw new OAuth2ConfigurationException(
                sprintf('OAuth2 %s TTL is not configured.', $label)
            );
        }

        try {
            return new DateInterval($value);
        } catch (Exception $exception) {
            throw new OAuth2ConfigurationException(
                sprintf(
                    'OAuth2 %s TTL "%s" is not a valid DateInterval string (e.g. PT1H, P1M).',
                    $label,
                    $value
                ),
                0,
                $exception
            );
        }
    }
}
