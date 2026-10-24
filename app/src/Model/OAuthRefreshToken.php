<?php

namespace Archipro\SilverstripeOAuth2\Model;

use InvalidArgumentException;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use SilverStripe\ORM\DataObject;

/**
 * Refresh token stored as a DataObject.
 *
 * Implements League's refresh-token entity directly. Client and Member are
 * reached only through the linked access token. Records must not be rendered
 * in templates.
 *
 * @property string|null $TokenIdentifier
 * @property string|null $ExpiresAt
 * @property bool $Revoked
 * @property int $OAuthAccessTokenID
 * @method OAuthAccessToken OAuthAccessToken()
 */
class OAuthRefreshToken extends DataObject implements RefreshTokenEntityInterface
{
    use TokenIdentityTrait;

    private static string $table_name = 'OAuthRefreshToken';

    /**
     * @var list<string>
     */
    private static array $extensions = [
        TokenIdentityExtension::class,
    ];

    /**
     * Relation name avoids League's getAccessToken() / setAccessToken().
     *
     * @var array<string, string>
     */
    private static array $has_one = [
        'OAuthAccessToken' => OAuthAccessToken::class,
    ];

    /**
     * @var array<string, string>
     */
    private static array $summary_fields = [
        'TokenIdentifier' => 'Identifier',
        'ExpiresAt' => 'Expires',
        'Revoked' => 'Revoked',
    ];

    public function getAccessToken(): AccessTokenEntityInterface
    {
        return $this->OAuthAccessToken();
    }

    public function setAccessToken(AccessTokenEntityInterface $accessToken): void
    {
        if (!$accessToken instanceof OAuthAccessToken) {
            throw new InvalidArgumentException('Refresh tokens require an OAuthAccessToken instance.');
        }

        $this->setComponent('OAuthAccessToken', $accessToken);
    }
}
