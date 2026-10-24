<?php

namespace Archipro\SilverstripeOAuth2\Settings;

use Archipro\SilverstripeOAuth2\Crypto\SigningKey;
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
     * Key that signs access tokens (private key, key id, and JWS algorithm).
     *
     * With the default `RsaKeyPair`, an empty or unusable public path fails at
     * construction; a bad private path fails on first `getPrivateKey()`.
     * The default JWKS list publishes that same pair.
     */
    public function getSigningKey(): SigningKey;

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
     * A bad injected public key fails when that key is constructed. This checks
     * the encryption key, the issuer, the path prefix, and both lifetimes.
     *
     * @throws OAuth2ConfigurationException
     */
    public function assertValid(): void;
}
