<?php

use Archipro\SilverstripeOAuth2\Crypto\SigningKey;
use Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair;
use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use Archipro\SilverstripeOAuth2\Settings\EnvironmentOAuth2Settings;
use Archipro\SilverstripeOAuth2\Settings\OAuth2SettingsInterface;
use Archipro\SilverstripeOAuth2\Tests\Double\FakeRsaKeyPair;
use Archipro\SilverstripeOAuth2\Tests\Double\StubSigningKey;
use Archipro\SilverstripeWellKnown\Providers\JsonWebKeySetProvider;
use Defuse\Crypto\Key as DefuseKey;
use League\OAuth2\Server\CryptKey;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers EnvironmentOAuth2Settings load and validation for happy and failure paths.
 *
 * Clears PHPUnit fixture OAUTH2_* env in setUp so empty-issuer and missing-path
 * cases see unset values, then restores them in tearDown.
 */
class OAuth2SettingsTest extends SapphireTest
{
    /**
     * @var array<string, string>
     */
    private array $envBackup = [];

    /**
     * OAUTH2_* names this class isolates from PHPUnit fixture env and other tests.
     *
     * @var list<string>
     */
    private array $envNames = [
        'OAUTH2_PRIVATE_KEY_PATH',
        'OAUTH2_PUBLIC_KEY_PATH',
        'OAUTH2_PRIVATE_KEY_PASSPHRASE',
        'OAUTH2_ENCRYPTION_KEY',
        'OAUTH2_ISSUER',
        'OAUTH2_PATH_PREFIX',
        'OAUTH2_ACCESS_TOKEN_TTL',
        'OAUTH2_REFRESH_TOKEN_TTL',
    ];

    private string $fixtureDir = '';

    private OpenSSLAsymmetricKey|false $privateKeyResource = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . '/oauth2-settings-' . uniqid('', true);
        mkdir($this->fixtureDir, 0700, true);

        $this->isolateEnv();

        // The pair is a singleton. Drop it so this test's env is what gets loaded.
        $injector = Injector::inst();
        $injector->unregisterNamedObject(RsaKeyPair::class);
        $injector->unregisterNamedObject(OAuth2SettingsInterface::class);
        $injector->unregisterNamedObject(JsonWebKeySetProvider::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->envNames as $name) {
            if (array_key_exists($name, $this->envBackup)) {
                Environment::setEnv($name, $this->envBackup[$name]);
            } else {
                Environment::setEnv($name, '');
            }
        }

        // Drop Injector singletons so later suites (SmokeTest) rebuild from fixture env.
        $injector = Injector::inst();
        $injector->unregisterNamedObject(RsaKeyPair::class);
        $injector->unregisterNamedObject(OAuth2SettingsInterface::class);
        $injector->unregisterNamedObject(JsonWebKeySetProvider::class);

        $this->removeDir($this->fixtureDir);

        parent::tearDown();
    }

    /**
     * Asserts a usable temporary pair passes validation and keeps the default lifetimes.
     */
    public function testAssertValidWithTemporaryPair(): void
    {
        $this->useTemporaryKeyPair();
        $encryption = DefuseKey::createNewRandomKey()->saveToAsciiSafeString();
        Environment::setEnv('OAUTH2_ENCRYPTION_KEY', $encryption);
        Environment::setEnv('OAUTH2_ISSUER', 'https://cms5x-auth.ddev.site');

        $settings = $this->makeSettings();
        $settings->assertValid();

        $this->assertSame('https://cms5x-auth.ddev.site', $settings->getIssuer());
        $this->assertSame('/oauth', $settings->getPathPrefix());
        $this->assertSame(1, $settings->getAccessTokenTTL()->h);
        $this->assertSame(1, $settings->getRefreshTokenTTL()->m);
    }

    /**
     * Asserts an empty public path fails when the pair is constructed.
     */
    public function testEmptyPublicPathFailsOnConstruct(): void
    {
        $this->useTemporaryKeyPair();
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', '');

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('OAuth2 public key is not configured');
        $this->makeSettings();
    }

    /**
     * Asserts a valid signing key publishes public JWK fields only, with no private exponent.
     */
    public function testKeyPairAndJwksOmitPrivateMaterial(): void
    {
        $this->useTemporaryKeyPair();
        $settings = $this->makeSettings();
        $pair = Injector::inst()->get(RsaKeyPair::class);
        $signingKey = $settings->getSigningKey();
        $this->assertSame($pair, $signingKey);

        $array = $pair->toArray();
        $this->assertSame(['kty', 'alg', 'use', 'kid', 'n', 'e'], array_keys($array));
        $this->assertArrayNotHasKey('d', $array);
        $this->assertSame($array['kid'], $signingKey->getKeyId());

        // Default Injector wiring lists the active pair in the JWKS document.
        $defaultBody = json_decode(Injector::inst()->get(JsonWebKeySetProvider::class)->getContent(), true);
        $this->assertIsArray($defaultBody);
        $this->assertCount(1, $defaultBody['keys']);
        $this->assertSame($signingKey->getKeyId(), $defaultBody['keys'][0]['kid']);
        $this->assertArrayNotHasKey('d', $defaultBody['keys'][0]);

        $privateKey = $signingKey->getPrivateKey();
        $this->assertInstanceOf(CryptKey::class, $privateKey);
        $this->assertSame($privateKey, $signingKey->getPrivateKey());
    }

    /**
     * Asserts settings accept a SigningKey that is not an RsaKeyPair.
     */
    public function testSettingsAcceptNonRsaKeyPairSigningKey(): void
    {
        $signingKey = new StubSigningKey();
        $settings = $this->makeSettings($signingKey);

        $this->assertSame($signingKey, $settings->getSigningKey());
        $this->assertNotInstanceOf(RsaKeyPair::class, $settings->getSigningKey());
    }

    /**
     * Asserts an empty private path fails when the private key is first loaded.
     */
    public function testEmptyPrivatePathFailsOnGetPrivateKey(): void
    {
        $this->useTemporaryKeyPair();
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', '');

        $settings = $this->makeSettings();
        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('OAuth2 private key is not configured');
        $settings->getSigningKey()->getPrivateKey();
    }

    /**
     * Asserts a missing private key file fails when the private key is first loaded.
     */
    public function testUnusablePrivateKeyFailsClearly(): void
    {
        $this->useTemporaryKeyPair();
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/missing.key');

        $settings = $this->makeSettings();
        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or unusable');
        $settings->getSigningKey()->getPrivateKey();
    }

    /**
     * Asserts a set but missing public key path fails clearly.
     */
    public function testMissingPublicKeyFileFailsClearly(): void
    {
        $this->useTemporaryKeyPair();
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', $this->fixtureDir . '/missing-public.key');

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or not readable');
        $this->makeSettings();
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

        $settings = $this->makeSettings();
        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or unusable');
        $settings->getSigningKey()->getPrivateKey();
    }

    /**
     * Asserts a public-only PEM on the private path fails when the private key is loaded.
     */
    public function testPublicOnlyPemOnPrivatePathFailsClearly(): void
    {
        $this->writeKeyPair();
        // Private path must be a private PEM; a public-only file must fail the OpenSSL check.
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/public.key');
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', $this->fixtureDir . '/public.key');

        $settings = $this->makeSettings();
        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or unusable');
        $settings->getSigningKey()->getPrivateKey();
    }

    /**
     * Asserts an encrypted private key with an empty passphrase fails when the private key is loaded.
     */
    public function testEncryptedPrivateKeyWithEmptyPassphraseFailsClearly(): void
    {
        $this->writePrivateKey('correct-pass');
        $this->writePublicKeyFromPrivate();
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/private.key');
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', $this->fixtureDir . '/public.key');
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PASSPHRASE', '');

        $settings = $this->makeSettings();
        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or unusable');
        $settings->getSigningKey()->getPrivateKey();
    }

    /**
     * Asserts the in-memory stand-in publishes the public half of its private key.
     */
    public function testInMemoryPairPublishesPublicMaterial(): void
    {
        $array = (new FakeRsaKeyPair())->toArray();

        $this->assertNotSame('', $array['n']);
        $this->assertNotSame('', $array['e']);
        $this->assertArrayNotHasKey('d', $array);
    }

    /**
     * Asserts an invalid Defuse encryption key fails clearly.
     */
    public function testInvalidEncryptionKeyFailsClearly(): void
    {
        Environment::setEnv('OAUTH2_ENCRYPTION_KEY', 'not-a-defuse-key');
        $settings = $this->makeSettings(new FakeRsaKeyPair());

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('Defuse ascii-safe string');
        $settings->getEncryptionKey();
    }

    /**
     * Asserts an empty issuer fails clearly.
     */
    public function testEmptyIssuerFailsClearly(): void
    {
        $settings = $this->makeSettings(new FakeRsaKeyPair());

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
        $settings = $this->makeSettings(new FakeRsaKeyPair());

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
        $settings = $this->makeSettings(new FakeRsaKeyPair());

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('not a valid DateInterval string');
        $settings->getAccessTokenTTL();
    }

    /**
     * Builds settings with the constructor arguments the interface YAML supplies.
     *
     * Pass a signing key when the test must not read PEM files.
     */
    private function makeSettings(?SigningKey $signingKey = null): EnvironmentOAuth2Settings
    {
        $encryptionKey = Environment::getEnv('OAUTH2_ENCRYPTION_KEY');
        $issuer = Environment::getEnv('OAUTH2_ISSUER');

        return new EnvironmentOAuth2Settings(
            $signingKey ?? Injector::inst()->get(RsaKeyPair::class),
            is_string($encryptionKey) && $encryptionKey !== '' ? $encryptionKey : null,
            is_string($issuer) && $issuer !== '' ? $issuer : null,
        );
    }

    /**
     * Point both env paths at a temporary PEM pair in the fixture directory.
     */
    private function useTemporaryKeyPair(): void
    {
        $this->writeKeyPair();
        Environment::setEnv('OAUTH2_PRIVATE_KEY_PATH', $this->fixtureDir . '/private.key');
        Environment::setEnv('OAUTH2_PUBLIC_KEY_PATH', $this->fixtureDir . '/public.key');
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
     * Backs up non-empty OAUTH2_* values, then clears every name in $envNames.
     *
     * PHPUnit sets happy-path OAUTH2_* via phpunit.xml.dist. Failure-path tests
     * in this class need those names empty until each test sets its own values.
     */
    private function isolateEnv(): void
    {
        $this->envBackup = [];

        foreach ($this->envNames as $name) {
            $value = Environment::getEnv($name);
            if (is_string($value) && $value !== '') {
                $this->envBackup[$name] = $value;
            }
            Environment::setEnv($name, '');
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
