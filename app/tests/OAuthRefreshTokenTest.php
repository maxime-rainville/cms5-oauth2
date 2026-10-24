<?php

use Archipro\SilverstripeOAuth2\Model\OAuthAccessToken;
use Archipro\SilverstripeOAuth2\Model\OAuthClient;
use Archipro\SilverstripeOAuth2\Model\OAuthRefreshToken;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * Covers OAuthRefreshToken persistence and access-token linkage.
 */
class OAuthRefreshTokenTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * Write and read back the refresh token, including the access-token link.
     */
    public function testPersistsAndResolvesAccessTokenClientAndMember(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'refresh-client';
        $client->Title = 'Refresh Client';
        $client->write();

        $member = Member::create();
        $member->Email = 'refresh-owner@example.com';
        $member->write();

        $accessToken = OAuthAccessToken::create();
        $accessToken->setIdentifier('access-for-refresh');
        $accessToken->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
        $accessToken->setClient($client);
        $accessToken->setUserIdentifier((string) $member->ID);
        $accessToken->write();

        $refresh = OAuthRefreshToken::create();
        $refresh->setIdentifier('refresh-token-1');
        $refresh->setExpiryDateTime(new DateTimeImmutable('+30 days'));
        $refresh->setAccessToken($accessToken);
        $refresh->Revoked = true;

        // Act
        $refresh->write();
        $loaded = OAuthRefreshToken::get()->byID($refresh->ID);

        // Assert
        $this->assertInstanceOf(OAuthRefreshToken::class, $loaded);
        $this->assertSame('refresh-token-1', $loaded->getIdentifier());
        $this->assertTrue((bool) $loaded->Revoked);
        $this->assertSame((int) $accessToken->ID, (int) $loaded->OAuthAccessTokenID);
        $this->assertTrue($loaded->OAuthAccessToken()->exists());
        $linked = $loaded->getAccessToken();
        $this->assertInstanceOf(OAuthAccessToken::class, $linked);
        $this->assertTrue($linked->OAuthClient()->exists());
        $this->assertSame('refresh-client', $linked->OAuthClient()->getIdentifier());
        $this->assertSame((string) $member->ID, $linked->getUserIdentifier());
    }

    /**
     * Unsaved refresh tokens expose an empty identifier string.
     */
    public function testUnsavedIdentifierIsEmptyString(): void
    {
        // Arrange / Act
        $refresh = OAuthRefreshToken::create();

        // Assert
        $this->assertSame('', $refresh->getIdentifier());
    }
}
