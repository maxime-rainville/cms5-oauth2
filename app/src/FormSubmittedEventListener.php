<?php

namespace App;

use function Amp\delay;

/**
 * Logs a FormSubmittedEvent after a short delay (demo listener).
 */
class FormSubmittedEventListener
{
    public static function handleEvent(FormSubmittedEvent $event): void
    {
        delay(10);
        error_log('Form submitted: ' . $event->formName . ' ' . json_encode($event->data));
    }
}
