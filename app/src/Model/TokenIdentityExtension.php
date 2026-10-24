<?php

namespace Archipro\SilverstripeOAuth2\Model;

use SilverStripe\ORM\DataExtension;

/**
 * Shared identifier, expiry, and revoked columns for OAuth token DataObjects.
 *
 * @extends DataExtension<\SilverStripe\ORM\DataObject>
 * @property string|null $TokenIdentifier
 * @property string|null $ExpiresAt
 * @property bool $Revoked
 */
class TokenIdentityExtension extends DataExtension
{
    /**
     * @var array<string, string>
     */
    private static array $db = [
        'TokenIdentifier' => 'Varchar(255)',
        'ExpiresAt' => 'DBDatetime',
        'Revoked' => 'Boolean',
    ];

    /**
     * @var array<string, array<string, string|list<string>>>
     */
    private static array $indexes = [
        'TokenIdentifier' => [
            'type' => 'unique',
            'columns' => ['TokenIdentifier'],
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    private static array $defaults = [
        'Revoked' => false,
    ];
}
