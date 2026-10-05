<?php

namespace App;

/**
 * Payload for a submitted CMS form, dispatched through EventService.
 */
class FormSubmittedEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $formName,
        public array $data
    ) {
    }
}
