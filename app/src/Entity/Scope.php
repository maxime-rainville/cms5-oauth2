<?php

namespace Archipro\SilverstripeOAuth2\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * In-memory OAuth2 scope identifier for League.
 *
 * Not a DataObject. Only used at the League boundary; do not render in templates.
 */
class Scope implements ScopeEntityInterface
{
    use ScopeTrait;

    private string $identifier;

    /**
     * Store the scope identifier League will put in the JWT or code.
     */
    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
