<?php

use Archipro\SilverstripeOAuth2\Entity\Scope;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers the in-memory Scope value object used at the League boundary.
 */
class ScopeTest extends SapphireTest
{
    /**
     * Scope JSON-encodes to its identifier string.
     */
    public function testJsonEncodesToIdentifier(): void
    {
        // Arrange
        $scope = new Scope('profile');

        // Act
        $encoded = json_encode($scope);

        // Assert
        $this->assertSame('"profile"', $encoded);
        $this->assertSame('profile', $scope->getIdentifier());
    }
}
