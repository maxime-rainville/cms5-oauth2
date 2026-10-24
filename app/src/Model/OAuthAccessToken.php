<?php

namespace Archipro\SilverstripeOAuth2\Model;

use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use SilverStripe\ORM\DataObject;

/**
 * Access token stored as a DataObject.
 *
 * Implements League's access-token entity directly. Records must not be
 * rendered in templates. Casting to string signs a JWT only when a private
 * key has been set on this in-flight instance.
 *
 * @property string|null $TokenIdentifier
 * @property string|null $ExpiresAt
 * @property bool $Revoked
 * @property int $OAuthClientID
 * @property int $MemberID
 * @method OAuthClient OAuthClient()
 * @method \SilverStripe\Security\Member Member()
 */
class OAuthAccessToken extends DataObject implements AccessTokenEntityInterface
{
    use TokenIdentityTrait;
    use TokenSubjectTrait;
    use AccessTokenTrait {
        __toString as private signAsJwt;
        setPrivateKey as private storePrivateKey;
    }

    private static string $table_name = 'OAuthAccessToken';

    /**
     * True after setPrivateKey(); not persisted.
     */
    private bool $canSignJwt = false;

    /**
     * @var list<string>
     */
    private static array $extensions = [
        TokenIdentityExtension::class,
        TokenSubjectExtension::class,
    ];

    /**
     * @var array<string, string>
     */
    private static array $summary_fields = [
        'TokenIdentifier' => 'Identifier',
        'ExpiresAt' => 'Expires',
        'Revoked' => 'Revoked',
    ];

    public function setPrivateKey(CryptKey $privateKey): void
    {
        $this->storePrivateKey($privateKey);
        $this->canSignJwt = true;
    }

    /**
     * Sign as a JWT when a private key is present; otherwise use ViewableData.
     */
    public function __toString(): string
    {
        if (!$this->canSignJwt) {
            return parent::__toString();
        }

        return $this->signAsJwt();
    }

    /**
     * Identifier for admin lists. Do not use in templates as a page title.
     */
    public function getTitle(): string
    {
        return $this->getIdentifier();
    }
}
