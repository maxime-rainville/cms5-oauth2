<?php

use SilverStripe\Dev\SapphireTest;

/**
 * Confirms the Default PHPUnit suite has at least one test so CI stays green
 * when app/tests would otherwise be empty.
 */
class SmokeTest extends SapphireTest
{
    /**
     * Asserts the installer Page class is loadable.
     */
    public function testPageClassExists(): void
    {
        $this->assertTrue(class_exists(Page::class));
    }
}
