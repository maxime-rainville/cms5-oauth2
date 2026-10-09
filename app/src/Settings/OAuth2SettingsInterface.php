<?php

namespace Archipro\SilverstripeOAuth2\Settings;

use Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair;
use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use DateInterval;

/**
 * Host-overridable OAuth2 crypto and authorization-server settings.
 *
 * Bind a custom implementation in Injector when these defaults are not enough.
 */
interface OAuth2SettingsInterface
{
    /**
     * Active signing pair. The same instance is published in the JWKS.
     *
     * @throws OAuth2ConfigurationException When the public key path is set but unusable
     */
    public function getKeyPair(): RsaKeyPair;

    /**
     * Defuse ascii-safe encryption key for auth codes and refresh tokens.
     *
     * @throws OAuth2ConfigurationException When the key is missing or invalid
     */
    public function getEncryptionKey(): string;

    /**
     * Authorization server issuer identifier (non-empty string, usually an absolute URL).
     *
     * @throws OAuth2ConfigurationException When empty
     */
    public function getIssuer(): string;

    /**
     * URL path prefix for OAuth endpoints (must start with `/`).
     *
     * @throws OAuth2ConfigurationException When empty or missing a leading slash
     */
    public function getPathPrefix(): string;

    /**
     * Access token lifetime.
     *
     * @throws OAuth2ConfigurationException When the TTL string is not a valid DateInterval
     */
    public function getAccessTokenTTL(): DateInterval;

    /**
     * Refresh token lifetime.
     *
     * @throws OAuth2ConfigurationException When the TTL string is not a valid DateInterval
     */
    public function getRefreshTokenTTL(): DateInterval;

    /**
     * Fail fast if settings required to sign and encrypt tokens are bad.
     *
     * An empty public key is allowed so the JWKS can stay empty. A private key
     * is required here because signing cannot start without it.
     *
     * @throws OAuth2ConfigurationException
     */
    public function assertValid(): void;
}
