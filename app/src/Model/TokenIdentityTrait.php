<?php

namespace Archipro\SilverstripeOAuth2\Model;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

/**
 * Identifier and expiry mapping shared by every OAuth token row.
 *
 * @property string|null $TokenIdentifier
 * @property string|null $ExpiresAt
 */
trait TokenIdentityTrait
{
    public function getIdentifier(): string
    {
        return $this->TokenIdentifier ?? '';
    }

    /**
     * @param mixed $identifier
     */
    public function setIdentifier($identifier): void
    {
        if (!is_string($identifier) && !is_int($identifier)) {
            throw new InvalidArgumentException('Token identifier must be a string or int.');
        }

        $this->TokenIdentifier = (string) $identifier;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        $raw = (string) ($this->ExpiresAt ?? '');
        if ($raw === '') {
            throw new LogicException('Token expiry has not been set.');
        }

        return new DateTimeImmutable($raw, new DateTimeZone(date_default_timezone_get()));
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $local = $dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $this->ExpiresAt = $local->format('Y-m-d H:i:s');
    }
}
