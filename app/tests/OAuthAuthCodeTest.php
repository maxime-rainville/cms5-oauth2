<?php

use Archipro\SilverstripeOAuth2\Entity\Scope;
use Archipro\SilverstripeOAuth2\Model\OAuthAuthCode;
use Archipro\SilverstripeOAuth2\Model\OAuthClient;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * Covers OAuthAuthCode persistence and League AuthCodeEntityInterface mapping.
 */
class OAuthAuthCodeTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * Expiry round-trips when the input uses a non-default time zone.
     */
    public function testExpiryRoundTripsFromNonDefaultTimezone(): void
    {
        // Arrange
        $expiry = new DateTimeImmutable('2030-06-15 12:30:00', new DateTimeZone('Pacific/Auckland'));
        $code = OAuthAuthCode::create();
        $code->setIdentifier('auth-code-expiry');
        $code->setExpiryDateTime($expiry);

        // Act
        $code->write();
        $loaded = OAuthAuthCode::get()->byID($code->ID);

        // Assert
        $this->assertInstanceOf(OAuthAuthCode::class, $loaded);
        $this->assertSame(
            $expiry->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'),
            $loaded->getExpiryDateTime()->format('Y-m-d H:i:s')
        );
    }

    /**
     * Client, Member, redirect URI, and revoked flag persist across a fresh read.
     */
    public function testPersistsClientMemberRedirectAndRevoked(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'auth-code-client';
        $client->Title = 'Auth Code Client';
        $client->write();

        $member = Member::create();
        $member->Email = 'owner@example.com';
        $member->write();

        $code = OAuthAuthCode::create();
        $code->setIdentifier('auth-code-links');
        $code->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
        $code->setClient($client);
        $code->setUserIdentifier((string) $member->ID);
        $code->setRedirectUri('https://app.example/callback');
        $code->Revoked = true;

        // Act
        $code->write();
        $loaded = OAuthAuthCode::get()->byID($code->ID);

        // Assert
        $this->assertInstanceOf(OAuthAuthCode::class, $loaded);
        $this->assertSame((int) $client->ID, (int) $loaded->OAuthClientID);
        $this->assertTrue($loaded->OAuthClient()->exists());
        $this->assertSame((string) $member->ID, $loaded->getUserIdentifier());
        $this->assertSame('https://app.example/callback', $loaded->getRedirectUri());
        $this->assertTrue((bool) $loaded->Revoked);
    }

    /**
     * Scopes stay on the in-memory instance and are absent after a fresh read.
     */
    public function testScopesAreInMemoryOnly(): void
    {
        // Arrange
        $code = OAuthAuthCode::create();
        $code->setIdentifier('auth-code-scopes');
        $code->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
        $code->addScope(new Scope('profile'));

        // Act
        $scopeIds = [];
        foreach ($code->getScopes() as $scope) {
            $scopeIds[] = $scope->getIdentifier();
        }
        $code->write();
        $loaded = OAuthAuthCode::get()->byID($code->ID);

        // Assert
        $this->assertSame(['profile'], $scopeIds);
        $this->assertInstanceOf(OAuthAuthCode::class, $loaded);
        $this->assertSame([], $loaded->getScopes());
    }

    /**
     * A deleted Member leaves the MemberID intact and does not fatal.
     */
    public function testDeletedMemberDoesNotFatalOnRead(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'auth-code-client-2';
        $client->Title = 'Auth Code Client 2';
        $client->write();

        $member = Member::create();
        $member->Email = 'gone@example.com';
        $member->write();
        $memberId = (int) $member->ID;

        $code = OAuthAuthCode::create();
        $code->setIdentifier('auth-code-2');
        $code->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
        $code->setClient($client);
        $code->setUserIdentifier((string) $memberId);
        $code->write();

        // Act
        $member->delete();
        $loaded = OAuthAuthCode::get()->byID($code->ID);

        // Assert
        $this->assertInstanceOf(OAuthAuthCode::class, $loaded);
        $this->assertSame((string) $memberId, $loaded->getUserIdentifier());
        $this->assertSame($memberId, (int) $loaded->MemberID);
        $this->assertFalse($loaded->Member()->exists());
    }

    /**
     * Unsaved records expose an empty identifier string, never null.
     */
    public function testUnsavedIdentifierIsEmptyString(): void
    {
        // Arrange / Act
        $code = OAuthAuthCode::create();

        // Assert
        $this->assertSame('', $code->getIdentifier());
    }

    /**
     * Reading expiry before it is set fails instead of inventing a sentinel.
     */
    public function testMissingExpiryThrows(): void
    {
        // Arrange
        $code = OAuthAuthCode::create();

        // Assert
        $this->expectException(LogicException::class);
        $code->getExpiryDateTime();
    }
}
