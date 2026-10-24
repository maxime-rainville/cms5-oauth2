<?php

use Archipro\SilverstripeOAuth2\Model\OAuthClient;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers OAuthClient persistence and League ClientEntityInterface mapping.
 */
class OAuthClientTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * Write and read back every stored field, including the redirect URI list.
     */
    public function testPersistsAllFieldsAndRedirectUriList(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'client-one';
        $client->Title = 'Example App';
        $client->Confidential = true;
        $client->Status = OAuthClient::STATUS_DISABLED;
        $client->setRedirectUris([
            'https://app.example/callback',
            'https://app.example/other',
        ]);
        $client->setSecret('plain-secret');

        // Act
        $client->write();
        $loaded = OAuthClient::get()->byID($client->ID);

        // Assert
        $this->assertInstanceOf(OAuthClient::class, $loaded);
        $this->assertSame('client-one', $loaded->ClientIdentifier);
        $this->assertSame('Example App', $loaded->Title);
        $this->assertTrue((bool) $loaded->Confidential);
        $this->assertSame(OAuthClient::STATUS_DISABLED, $loaded->Status);
        $this->assertSame(
            ['https://app.example/callback', 'https://app.example/other'],
            $loaded->getRedirectUri()
        );
        $this->assertNotSame('plain-secret', $loaded->SecretHash);
        $this->assertTrue($loaded->verifySecret('plain-secret'));
        $this->assertFalse($loaded->verifySecret('wrong-secret'));
    }

    /**
     * New clients default to Active and expose League getters from stored values.
     */
    public function testDefaultsToActiveAndMapsLeagueGetters(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'client-two';
        $client->Title = 'Second App';
        $client->Confidential = false;
        $client->setRedirectUris(['https://second.example/cb']);
        $client->write();

        // Act
        $loaded = OAuthClient::get()->byID($client->ID);

        // Assert
        $this->assertInstanceOf(OAuthClient::class, $loaded);
        $this->assertSame(OAuthClient::STATUS_ACTIVE, $loaded->Status);
        $this->assertSame('client-two', $loaded->getIdentifier());
        $this->assertSame('Second App', $loaded->getName());
        $this->assertSame(['https://second.example/cb'], $loaded->getRedirectUri());
        $this->assertFalse($loaded->isConfidential());
        $this->assertFalse($loaded->canDelete());
    }

    /**
     * Soft-delete sets Status to Deleted and leaves the row in place.
     */
    public function testMarkDeletedSetsStatusWithoutRemovingRow(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'client-three';
        $client->Title = 'Third App';
        $client->write();

        // Act
        $client->markDeleted();
        $client->write();
        $loaded = OAuthClient::get()->byID($client->ID);

        // Assert
        $this->assertInstanceOf(OAuthClient::class, $loaded);
        $this->assertSame(OAuthClient::STATUS_DELETED, $loaded->Status);
    }
}
