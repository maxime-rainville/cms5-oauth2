<?php

use Archipro\SilverstripeOAuth2\Entity\Scope;
use Archipro\SilverstripeOAuth2\Model\OAuthAccessToken;
use Archipro\SilverstripeOAuth2\Model\OAuthClient;
use Archipro\SilverstripeOAuth2\Tests\Double\FakeRsaKeyPair;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * Covers OAuthAccessToken persistence and guarded JWT signing.
 */
class OAuthAccessTokenTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * Field and link values round-trip through the database.
     */
    public function testPersistsFieldsAndLinks(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'access-token-client';
        $client->Title = 'Access Token Client';
        $client->write();

        $member = Member::create();
        $member->Email = 'token-owner@example.com';
        $member->write();

        $token = OAuthAccessToken::create();
        $token->setIdentifier('access-token-1');
        $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
        $token->setClient($client);
        $token->setUserIdentifier((string) $member->ID);
        $token->Revoked = false;

        // Act
        $token->write();
        $loaded = OAuthAccessToken::get()->byID($token->ID);

        // Assert
        $this->assertInstanceOf(OAuthAccessToken::class, $loaded);
        $this->assertSame('access-token-1', $loaded->getIdentifier());
        $this->assertSame((int) $client->ID, (int) $loaded->OAuthClientID);
        $this->assertTrue($loaded->OAuthClient()->exists());
        $this->assertSame((string) $member->ID, $loaded->getUserIdentifier());
        $this->assertFalse((bool) $loaded->Revoked);
        $this->assertSame('access-token-1', $loaded->getTitle());
    }

    /**
     * With a private key set, the string form verifies as an RS256 JWT.
     */
    public function testToStringSignsVerifiableJwtWhenPrivateKeySet(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'jwt-client';
        $client->Title = 'JWT Client';
        $client->write();

        $member = Member::create();
        $member->Email = 'jwt-owner@example.com';
        $member->write();

        $expiry = new DateTimeImmutable('+1 hour');
        $pair = new FakeRsaKeyPair();

        $token = OAuthAccessToken::create();
        $token->setIdentifier('jwt-token-1');
        $token->setExpiryDateTime($expiry);
        $token->setClient($client);
        $token->setUserIdentifier((string) $member->ID);
        $token->addScope(new Scope('profile'));
        $token->addScope(new Scope('email'));
        $token->setPrivateKey($pair->getPrivateKey());

        // Act
        $jwt = (string) $token;
        if ($jwt === '') {
            $this->fail('Expected a signed JWT string.');
        }

        $publicPem = FakeRsaKeyPair::getPublicPem();

        // Assert
        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($publicPem),
            InMemory::plainText($publicPem)
        );
        $parsed = $config->parser()->parse($jwt);
        $this->assertInstanceOf(UnencryptedToken::class, $parsed);
        $this->assertTrue(
            $config->validator()->validate($parsed, new SignedWith($config->signer(), $config->verificationKey()))
        );
        $this->assertSame('jwt-token-1', $parsed->claims()->get('jti'));
        $this->assertSame(['jwt-client'], $parsed->claims()->get('aud'));
        $this->assertSame((string) $member->ID, $parsed->claims()->get('sub'));
        $exp = $parsed->claims()->get('exp');
        $this->assertInstanceOf(DateTimeImmutable::class, $exp);
        $this->assertSame($expiry->getTimestamp(), $exp->getTimestamp());

        $scopeIds = [];
        $scopes = $parsed->claims()->get('scopes');
        $this->assertIsIterable($scopes);
        foreach ($scopes as $scope) {
            if (is_object($scope) && method_exists($scope, 'getIdentifier')) {
                $scopeIds[] = $scope->getIdentifier();
            } else {
                $scopeIds[] = (string) $scope;
            }
        }
        $this->assertSame(['profile', 'email'], $scopeIds);
    }

    /**
     * A fresh database row casts to the ViewableData string without signing.
     */
    public function testFreshRecordCastsWithoutSigning(): void
    {
        // Arrange
        $client = OAuthClient::create();
        $client->ClientIdentifier = 'fresh-client';
        $client->Title = 'Fresh Client';
        $client->write();

        $token = OAuthAccessToken::create();
        $token->setIdentifier('fresh-token-1');
        $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
        $token->setClient($client);
        $token->write();

        // Act
        $loaded = OAuthAccessToken::get()->byID($token->ID);
        $this->assertInstanceOf(OAuthAccessToken::class, $loaded);
        $asString = (string) $loaded;

        // Assert — not a JWT (three base64 segments)
        $this->assertStringNotContainsString('.', $asString);
        $this->assertStringContainsString('OAuthAccessToken', $asString);
    }
}
