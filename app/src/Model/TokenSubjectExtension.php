<?php

namespace Archipro\SilverstripeOAuth2\Model;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;

/**
 * Shared client and Member relations for auth-code and access-token rows.
 *
 * @extends DataExtension<\SilverStripe\ORM\DataObject>
 * @property int $OAuthClientID
 * @property int $MemberID
 * @method OAuthClient OAuthClient()
 * @method Member Member()
 */
class TokenSubjectExtension extends DataExtension
{
    /**
     * @var array<string, string>
     */
    private static array $has_one = [
        'OAuthClient' => OAuthClient::class,
        'Member' => Member::class,
    ];
}
