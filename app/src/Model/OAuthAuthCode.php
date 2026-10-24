<?php

namespace Archipro\SilverstripeOAuth2\Model;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use SilverStripe\ORM\DataObject;

/**
 * Authorisation code stored as a DataObject.
 *
 * Implements League's auth-code entity directly. Records must not be rendered
 * in templates.
 *
 * @property string|null $TokenIdentifier
 * @property string|null $ExpiresAt
 * @property string|null $RedirectTo
 * @property bool $Revoked
 * @property int $OAuthClientID
 * @property int $MemberID
 * @method OAuthClient OAuthClient()
 * @method \SilverStripe\Security\Member Member()
 */
class OAuthAuthCode extends DataObject implements AuthCodeEntityInterface
{
    use TokenIdentityTrait;
    use TokenSubjectTrait;

    private static string $table_name = 'OAuthAuthCode';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        'RedirectTo' => 'Text',
    ];

    /**
     * @var list<string>
     */
    private static array $extensions = [
        TokenIdentityExtension::class,
        TokenSubjectExtension::class,
    ];

    public function getRedirectUri(): ?string
    {
        $uri = $this->RedirectTo ?? '';

        return $uri === '' ? null : $uri;
    }

    /**
     * @param string $uri
     */
    public function setRedirectUri($uri): void
    {
        $this->RedirectTo = (string) $uri;
    }
}
