<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

use Archipro\SilverstripeWellKnown\Contracts\JsonWebKey;
use Archipro\SilverstripeWellKnown\Providers\JsonWebKeySetProvider;

/**
 * JWKS provider that omits keys with no material.
 *
 * The well-known provider calls toArray() on every key it stores. An empty
 * path must not become a broken JWK, so unpublished keys are dropped first.
 */
final class PublishedJsonWebKeySetProvider extends JsonWebKeySetProvider
{
    /**
     * @param array<int, JsonWebKey> $keys
     */
    public function __construct(array $keys = [])
    {
        parent::__construct($this->onlyPublished($keys));
    }

    public function addKey(JsonWebKey $key): void
    {
        if (!$this->isPublishedKey($key)) {
            return;
        }

        parent::addKey($key);
    }

    /**
     * @param array<int, JsonWebKey> $keys
     */
    public function setKeys(array $keys): void
    {
        parent::setKeys($this->onlyPublished($keys));
    }

    /**
     * @param array<int, JsonWebKey> $keys
     * @return array<int, JsonWebKey>
     */
    private function onlyPublished(array $keys): array
    {
        $published = [];
        foreach ($keys as $key) {
            if ($this->isPublishedKey($key)) {
                $published[] = $key;
            }
        }

        return $published;
    }

    private function isPublishedKey(JsonWebKey $key): bool
    {
        return !($key instanceof PublishableJsonWebKey) || $key->isPublished();
    }
}
