<?php

use Archipro\SilverstripeOAuth2\Settings\OAuth2SettingsInterface;
use SilverStripe\Dev\FunctionalTest;

/**
 * Confirms installer basics, OAuth2 settings binding, and empty JWKS wiring.
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
     * Asserts Injector binds the OAuth2 settings interface.
     */
    public function testOAuth2SettingsBound(): void
    {
        $this->assertTrue(interface_exists(OAuth2SettingsInterface::class));
        $settings = singleton(OAuth2SettingsInterface::class);
        $this->assertInstanceOf(OAuth2SettingsInterface::class, $settings);
    }

    /**
     * Asserts JWKS answers HTTP 200 with {"keys":[]} when no public key is set.
     */
    public function testJwksReturnsEmptyKeys(): void
    {
        $response = $this->get('/.well-known/jwks.json');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertSame(['keys' => []], $body);
    }
}
