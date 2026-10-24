<?php

namespace Archipro\SilverstripeOAuth2\Model;

use InvalidArgumentException;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Client, Member, and in-memory scopes for auth-code and access-token rows.
 *
 * @property int $OAuthClientID
 * @property int $MemberID
 * @method OAuthClient OAuthClient()
 * @method \SilverStripe\Security\Member Member()
 * @method void setComponent(string $componentName, mixed $item)
 */
trait TokenSubjectTrait
{
    /**
     * Scopes for the in-flight League token. Not stored on the row.
     *
     * @var array<string, ScopeEntityInterface>
     */
    private array $leagueScopes = [];

    /**
     * @param string|int|null $identifier
     */
    public function setUserIdentifier($identifier): void
    {
        if ($identifier === null || $identifier === '') {
            $this->MemberID = 0;

            return;
        }

        if (!is_numeric($identifier)) {
            throw new InvalidArgumentException('User identifier must be numeric, null, or empty.');
        }

        $this->MemberID = (int) $identifier;
    }

    public function getUserIdentifier(): ?string
    {
        $memberId = (int) $this->MemberID;
        if ($memberId === 0) {
            return null;
        }

        return (string) $memberId;
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->OAuthClient();
    }

    public function setClient(ClientEntityInterface $client): void
    {
        if (!$client instanceof OAuthClient) {
            throw new InvalidArgumentException('Token records require an OAuthClient instance.');
        }

        $this->setComponent('OAuthClient', $client);
    }

    public function addScope(ScopeEntityInterface $scope): void
    {
        $this->leagueScopes[$scope->getIdentifier()] = $scope;
    }

    /**
     * @return list<ScopeEntityInterface>
     */
    public function getScopes(): array
    {
        return array_values($this->leagueScopes);
    }
}
