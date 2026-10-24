<?php

namespace Archipro\SilverstripeOAuth2\Model;

use JsonException;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Registered OAuth2 client stored as a DataObject.
 *
 * Implements League's client entity directly. Records must not be rendered in
 * templates.
 *
 * @property string $ClientIdentifier
 * @property string $Title
 * @property string $SecretHash
 * @property string $RedirectUriList
 * @property bool $Confidential
 * @property string $Status
 */
class OAuthClient extends DataObject implements ClientEntityInterface
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_DISABLED = 'Disabled';

    public const STATUS_DELETED = 'Deleted';

    private static string $table_name = 'OAuthClient';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        'ClientIdentifier' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'SecretHash' => 'Varchar(255)',
        'RedirectUriList' => 'Text',
        'Confidential' => 'Boolean',
        'Status' => "Enum('Active,Disabled,Deleted','Active')",
    ];

    /**
     * @var array<string, string>
     */
    private static array $summary_fields = [
        'ClientIdentifier' => 'Identifier',
        'Title' => 'Name',
        'Status' => 'Status',
    ];

    /**
     * @var array<string, array<string, string|list<string>>>
     */
    private static array $indexes = [
        'ClientIdentifier' => [
            'type' => 'unique',
            'columns' => ['ClientIdentifier'],
        ],
    ];

    /**
     * Soft-delete only: set Status to Deleted. Rows must stay for linked tokens.
     *
     * @param Member|mixed $member
     */
    public function canDelete($member = null): bool
    {
        return false;
    }

    public function getIdentifier(): string
    {
        return $this->ClientIdentifier;
    }

    public function getName(): string
    {
        return $this->Title;
    }

    /**
     * Registered redirect URIs for this client.
     *
     * @return list<string>
     */
    public function getRedirectUri(): array
    {
        $raw = $this->RedirectUriList;
        if ($raw === '') {
            return [];
        }

        // Decode strictly so corrupt rows fail instead of looking like no redirects
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException(
                'OAuthClient RedirectUriList must be a JSON array of strings.',
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new JsonException('OAuthClient RedirectUriList must be a JSON array of strings.');
        }

        // Keep only non-empty strings; anything else means the stored JSON is invalid
        $uris = [];
        foreach ($decoded as $uri) {
            if (!is_string($uri) || $uri === '') {
                throw new JsonException('OAuthClient RedirectUriList must be a JSON array of strings.');
            }
            $uris[] = $uri;
        }

        return $uris;
    }

    /**
     * Persist the redirect URI list as JSON.
     *
     * @param array<int, string> $uris
     */
    public function setRedirectUris(array $uris): void
    {
        $this->RedirectUriList = json_encode(array_values($uris), JSON_THROW_ON_ERROR);
    }

    public function isConfidential(): bool
    {
        return (bool) $this->Confidential;
    }

    /**
     * Hash and store a plain client secret. The plain value is never kept.
     */
    public function setSecret(string $plainSecret): void
    {
        $this->SecretHash = password_hash($plainSecret, PASSWORD_DEFAULT);
    }

    /**
     * Check a plain secret against the stored hash.
     */
    public function verifySecret(string $plainSecret): bool
    {
        $hash = $this->SecretHash;
        if ($hash === '') {
            return false;
        }

        return password_verify($plainSecret, $hash);
    }

    /**
     * Mark the client deleted without removing the row.
     */
    public function markDeleted(): void
    {
        $this->Status = self::STATUS_DELETED;
    }
}
