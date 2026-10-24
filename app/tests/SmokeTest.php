<?php

use Archipro\SilverstripeOAuth2\Discovery\RsaKeyPair;
use Archipro\SilverstripeOAuth2\Settings\OAuth2SettingsInterface;
use SilverStripe\Dev\FunctionalTest;

/**
 * Confirms installer basics, fixture-backed OAuth2 settings, and JWKS wiring.
 *
 * phpunit.xml.dist supplies test key paths and issuer so settings and JWKS
 * construct. The default JWKS list includes RsaKeyPair, so
 * /.well-known/jwks.json publishes the Injector signing pair's public JWK.
 */
class SmokeTest extends FunctionalTest
{
    /**
     * Asserts the installer Page class is loadable.
     */
    public function testPageClassExists(): void
    {
        $this->assertTrue(class_exists(Page::class));
    }

    /**
     * Asserts Injector binds OAuth2 settings and the fixture env passes validation.
     */
    public function testOAuth2SettingsBound(): void
    {
        $this->assertTrue(interface_exists(OAuth2SettingsInterface::class));
        $settings = singleton(OAuth2SettingsInterface::class);
        $this->assertInstanceOf(OAuth2SettingsInterface::class, $settings);
        $settings->assertValid();
    }

    /**
     * Asserts JWKS answers HTTP 200 with the wired RsaKeyPair public JWK and no private exponent.
     */
    public function testJwksListsActiveKey(): void
    {
        $response = $this->get('/.well-known/jwks.json');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('keys', $body);
        $this->assertCount(1, $body['keys']);

        $expected = singleton(RsaKeyPair::class)->toArray();
        $this->assertSame($expected, $body['keys'][0]);
        $this->assertArrayNotHasKey('d', $body['keys'][0]);
    }
}
