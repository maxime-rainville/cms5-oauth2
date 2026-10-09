<?php

use Archipro\SilverstripeOAuth2\Discovery\PublishedJsonWebKeySetProvider;
use Archipro\SilverstripeOAuth2\Discovery\RsaPublicJsonWebKey;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers dropping unpublished keys before the JWKS document is built.
 */
class PublishedJsonWebKeySetProviderTest extends SapphireTest
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . '/oauth2-jwks-' . uniqid('', true);
        mkdir($this->fixtureDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->fixtureDir);

        parent::tearDown();
    }

    /**
     * Asserts an empty public-key path yields an empty JWKS document.
     */
    public function testEmptyPathYieldsEmptyKeys(): void
    {
        $provider = PublishedJsonWebKeySetProvider::create([
            new RsaPublicJsonWebKey(null),
        ]);

        $body = json_decode($provider->getContent(), true);
        $this->assertSame(['keys' => []], $body);
    }

    /**
     * Asserts a PEM file yields one RSA key and an empty sibling is omitted.
     */
    public function testPemFileYieldsOneRsaKey(): void
    {
        $private = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertIsArray($details);
        $path = $this->fixtureDir . '/public.key';
        file_put_contents($path, $details['key']);

        $provider = PublishedJsonWebKeySetProvider::create([
            new RsaPublicJsonWebKey(''),
            new RsaPublicJsonWebKey($path),
        ]);

        $body = json_decode($provider->getContent(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body['keys']);
        $this->assertSame('RSA', $body['keys'][0]['kty']);
        $this->assertSame('RS256', $body['keys'][0]['alg']);
        $this->assertArrayHasKey('n', $body['keys'][0]);
        $this->assertArrayHasKey('e', $body['keys'][0]);
        $this->assertArrayNotHasKey('d', $body['keys'][0]);
    }

    /**
     * Asserts a later add or replace still omits an empty path.
     */
    public function testAddAndSetKeysOmitEmptyPath(): void
    {
        $private = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertIsArray($details);
        $path = $this->fixtureDir . '/public.key';
        file_put_contents($path, $details['key']);

        $provider = PublishedJsonWebKeySetProvider::create();
        $provider->addKey(new RsaPublicJsonWebKey(null));
        $provider->addKey(new RsaPublicJsonWebKey($path));
        $provider->setKeys([
            new RsaPublicJsonWebKey(''),
            new RsaPublicJsonWebKey($path),
        ]);

        $body = json_decode($provider->getContent(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body['keys']);
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
