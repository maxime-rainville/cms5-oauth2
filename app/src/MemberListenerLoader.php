<?php

namespace App;

use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\Silverstripe\EventDispatcher\Contract\ListenerLoaderInterface;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Listener\DataObjectEventListener;
use Closure;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\Member;

use function Amp\delay;

/**
 * Registers a listener that emails a member after they are created.
 */
class MemberListenerLoader implements ListenerLoaderInterface
{
    public function loadListeners(ListenerProvider $provider): void
    {
        DataObjectEventListener::create(
            Closure::fromCallable([$this, 'onMemberCreated']),
            [Member::class],
            [Operation::CREATE]
        )->selfRegister($provider);
    }

    /**
     * @param DataObjectEvent<Member> $event
     */
    public function onMemberCreated(DataObjectEvent $event): void
    {
        // Demo delay so the listener is visibly async
        delay(10);

        // Reload after the wait; the record may already be gone
        $member = $event->getObject();
        if ($member === null) {
            return;
        }

        Email::create()
            ->setTo($member->Email)
            ->setSubject('Welcome to our site')
            ->setFrom('no-reply@example.com')
            ->setBody('Welcome to our site, ' . $member->FirstName)
            ->send();
    }
}
