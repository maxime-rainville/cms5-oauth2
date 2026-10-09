<?php

use Archipro\SilverstripeOAuth2\Discovery\PublishedJsonWebKeySetProvider;
use Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair;
use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use Archipro\SilverstripeOAuth2\Settings\EnvironmentOAuth2Settings;
use Archipro\SilverstripeOAuth2\Settings\OAuth2SettingsInterface;
use Archipro\SilverstripeWellKnown\Providers\JsonWebKeySetProvider;
use Defuse\Crypto\Key as DefuseKey;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers EnvironmentOAuth2Settings load and validation for happy and failure paths.
 */
class OAuth2SettingsTest extends SapphireTest
{
    /**
     * @var array<string, string>
     */
    private array $envBackup = [];

    /**
     * @var list<string>
     */
    private array $envCleared = [];

    private string $fixtureDir = '';

    private OpenSSLAsymmetricKey|false $privateKeyResource = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . '/oauth2-settings-' . uniqid('', true);
        mkdir($this->fixtureDir, 0700, true);

        $this->backupEnv([
            'OAUTH2_PRIVATE_KEY_PATH',
            'OAUTH2_PUBLIC_KEY_PATH',
            'OAUTH2_PRIVATE_KEY_PASSPHRASE',
            'OAUTH2_ENCRYPTION_KEY',
            'OAUTH2_ISSUER',
            'OAUTH2_PATH_PREFIX',
            'OAUTH2_ACCESS_TOKEN_TTL',
            'OAUTH2_REFRESH_TOKEN_TTL',
        ]);

        // Clear env so YAML defaults and unset paths are visible
        foreach ($this->envCleared as $name) {
            Environment::setEnv($name, '');
        }

        // The pair is a singleton. Drop it so this test's env is what gets loaded.
        $injector = Injector::inst();
        $injector->unregisterNamedObject(RsaKeyPair::class);
        $injector->unregisterNamedObject(EnvironmentOAuth2Settings::class);
        $injector->unregisterNamedObject(OAuth2SettingsInterface::class);
        $injector->unregisterNamedObject(JsonWebKeySetProvider::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            Environment::setEnv($name, $value);
        }
        foreach ($this->envCleared as $name) {
            if (!array_key_exists($name, $this->envBackup)) {
                Environment::setEnv($name, '');
            }
        }

        $this->removeDir($this->fixtureDir);

        parent::tearDown();
    }

    /**
     * Asserts auth-server settings validate without requiring a public key.
     */
    public function testAssertValidHappyPathWithoutPublicKey(): void
    {
        $this->writePrivateKey();
        $encryption = DefuseKey::createNewRandomKey()->saveToAsciiSafeString();

        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/private.key');
        Environment::setEnv('OAUTH2_ENCRYPTION_KEY', $encryption);
        Environment::setEnv('OAUTH2_ISSUER', 'https://cms5x-auth.ddev.site');

        $settings = EnvironmentOAuth2Settings::create();
        $settings->assertValid();

        $this->assertFalse($settings->getKeyPair()->isPublished());
        $this->assertSame('https://cms5x-auth.ddev.site', $settings->getIssuer());
        $this->assertSame('/oauth', $settings->getPathPrefix());
        $this->assertSame(1, $settings->getAccessTokenTTL()->h);
        $this->assertSame(1, $settings->getRefreshTokenTTL()->m);
    }

    /**
     * Asserts the pair publishes public JWK fields only, even when the private file is missing.
     */
    public function testKeyPairAndJwksOmitPrivateMaterial(): void
    {
        $this->writeKeyPair();
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', $this->fixtureDir . '/public.key');
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/missing-private.key');

        $settings = EnvironmentOAuth2Settings::create();
        $pair = $settings->getKeyPair();
        $this->assertTrue($pair->isPublished());
        $this->assertSame($pair, Injector::inst()->get(RsaKeyPair::class));

        $array = $pair->toArray();
        $this->assertSame(['kty', 'alg', 'use', 'kid', 'n', 'e'], array_keys($array));

        $provider = PublishedJsonWebKeySetProvider::create([$pair]);
        $body = json_decode($provider->getContent(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body['keys']);
        $this->assertSame($array['kid'], $body['keys'][0]['kid']);
        $this->assertArrayNotHasKey('d', $body['keys'][0]);

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or unusable');
        $pair->getPrivateKey();
    }

    /**
     * Asserts a missing private key fails with a clear configuration error.
     */
    public function testMissingPrivateKeyFailsClearly(): void
    {
        $settings = EnvironmentOAuth2Settings::create();

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('OAuth2 private key is not configured');
        $settings->getKeyPair()->getPrivateKey();
    }

    /**
     * Asserts an unreadable private key path fails without a raw OpenSSL dump.
     */
    public function testUnusablePrivateKeyFailsClearly(): void
    {
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/missing.key');
        $settings = EnvironmentOAuth2Settings::create();

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or unusable');
        $settings->getKeyPair()->getPrivateKey();
    }

    /**
     * Asserts a set but missing public key path fails clearly.
     */
    public function testMissingPublicKeyFileFailsClearly(): void
    {
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', $this->fixtureDir . '/missing-public.key');

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or not readable');
        EnvironmentOAuth2Settings::create();
    }

    /**
     * Asserts a wrong private-key passphrase fails as configuration, not a raw OpenSSL error.
     */
    public function testWrongPassphraseFailsClearly(): void
    {
        $this->writePrivateKey('correct-pass');
        $this->writePublicKeyFromPrivate();
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/private.key');
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', $this->fixtureDir . '/public.key');
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PASSPHRASE', 'wrong-pass');

        $settings = EnvironmentOAuth2Settings::create();

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or unusable');
        $settings->getKeyPair()->getPrivateKey();
    }

    /**
     * Asserts an invalid Defuse encryption key fails clearly.
     */
    public function testInvalidEncryptionKeyFailsClearly(): void
    {
        Environment::setEnv('OAUTH2_ENCRYPTION_KEY', 'not-a-defuse-key');
        $settings = EnvironmentOAuth2Settings::create();

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('Defuse ascii-safe string');
        $settings->getEncryptionKey();
    }

    /**
     * Asserts an empty issuer fails clearly.
     */
    public function testEmptyIssuerFailsClearly(): void
    {
        $settings = EnvironmentOAuth2Settings::create();

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('OAuth2 issuer is not configured');
        $settings->getIssuer();
    }

    /**
     * Asserts a path prefix without a leading slash fails clearly.
     */
    public function testPathPrefixWithoutSlashFailsClearly(): void
    {
        Environment::setEnv('OAUTH2_PATH_PREFIX', 'oauth');
        $settings = EnvironmentOAuth2Settings::create();

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('starting with "/"');
        $settings->getPathPrefix();
    }

    /**
     * Asserts a bad TTL string fails clearly.
     */
    public function testInvalidAccessTokenTtlFailsClearly(): void
    {
        Environment::setEnv('OAUTH2_ACCESS_TOKEN_TTL', 'one-hour');
        $settings = EnvironmentOAuth2Settings::create();

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('not a valid DateInterval string');
        $settings->getAccessTokenTTL();
    }

    /**
     * Write a temporary RSA private key into the fixture directory.
     */
    private function writePrivateKey(?string $passphrase = null): void
    {
        $private = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($private);

        openssl_pkey_export($private, $privatePem, $passphrase);
        file_put_contents($this->fixtureDir . '/private.key', $privatePem);
        $this->privateKeyResource = $private;
    }

    /**
     * Write the public half of the private key created by writePrivateKey().
     */
    private function writePublicKeyFromPrivate(): void
    {
        $this->assertNotFalse($this->privateKeyResource);
        $details = openssl_pkey_get_details($this->privateKeyResource);
        $this->assertIsArray($details);
        file_put_contents($this->fixtureDir . '/public.key', $details['key']);
    }

    /**
     * Write a temporary RSA key pair into the fixture directory.
     */
    private function writeKeyPair(): void
    {
        $private = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($private);

        openssl_pkey_export($private, $privatePem);
        $details = openssl_pkey_get_details($private);
        $this->assertIsArray($details);

        file_put_contents($this->fixtureDir . '/private.key', $privatePem);
        file_put_contents($this->fixtureDir . '/public.key', $details['key']);
    }

    /**
     * @param list<string> $names
     */
    private function backupEnv(array $names): void
    {
        $this->envBackup = [];
        $this->envCleared = [];

        foreach ($names as $name) {
            $value = Environment::getEnv($name);
            if (is_string($value) && $value !== '') {
                $this->envBackup[$name] = $value;
            } else {
                $this->envCleared[] = $name;
            }
        }
    }

    /**
     * Recursively remove a temporary fixture directory.
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
