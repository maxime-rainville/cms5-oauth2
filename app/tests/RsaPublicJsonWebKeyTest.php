<?php

use Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey;
use Archipro\SilverstripeOAuth2\Exception\OAuth2ConfigurationException;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers RSA public key → JWK conversion from a PEM file path.
 */
class RsaPublicJsonWebKeyTest extends SapphireTest
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . '/oauth2-public-jwk-' . uniqid('', true);
        mkdir($this->fixtureDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->fixtureDir);

        parent::tearDown();
    }

    /**
     * Asserts a valid RSA public key file publishes kty/n/e and RS256 metadata.
     */
    public function testToArrayFromValidPublicKey(): void
    {
        $pem = $this->writePublicKey('public.key');

        $jwk = new RsaPublicJsonWebKey($this->fixtureDir . '/public.key');
        $array = $jwk->toArray();

        $this->assertTrue($jwk->isPublished());
        $this->assertSame('RSA', $array['kty']);
        $this->assertSame('RS256', $array['alg']);
        $this->assertSame('sig', $array['use']);
        $this->assertSame(substr(hash('sha256', $pem), 0, 32), $array['kid']);
        $this->assertNotSame('', $array['n']);
        $this->assertNotSame('', $array['e']);
        $this->assertSame(['kty', 'alg', 'use', 'kid', 'n', 'e'], array_keys($array));
    }

    /**
     * Asserts an empty path is left unpublished instead of throwing.
     */
    public function testEmptyPathIsUnpublished(): void
    {
        $jwk = new RsaPublicJsonWebKey(null);

        $this->assertFalse($jwk->isPublished());
        $this->assertFalse((new RsaPublicJsonWebKey(''))->isPublished());
    }

    /**
     * Asserts a missing file fails with a configuration exception.
     */
    public function testMissingFileFailsClearly(): void
    {
        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('missing or not readable');
        new RsaPublicJsonWebKey($this->fixtureDir . '/missing.key');
    }

    /**
     * Asserts garbage PEM fails with a configuration exception.
     */
    public function testInvalidPemFailsClearly(): void
    {
        file_put_contents($this->fixtureDir . '/bad.key', 'not-a-key');

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('public key PEM is not usable');
        new RsaPublicJsonWebKey($this->fixtureDir . '/bad.key');
    }

    /**
     * Asserts a non-RSA public key fails with a configuration exception.
     */
    public function testNonRsaPublicKeyFailsClearly(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        file_put_contents($this->fixtureDir . '/ec.key', $details['key']);

        $this->expectException(OAuth2ConfigurationException::class);
        $this->expectExceptionMessage('public key must be RSA');
        new RsaPublicJsonWebKey($this->fixtureDir . '/ec.key');
    }

    /**
     * Write a temporary RSA public key and return its PEM bytes.
     */
    private function writePublicKey(string $filename): string
    {
        $private = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertIsArray($details);

        file_put_contents($this->fixtureDir . '/' . $filename, $details['key']);

        return $details['key'];
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
