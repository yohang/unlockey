<?php

namespace App\Workflow\EventListener;

use App\Entity\Locker;
use App\Service\LockerLockingService;
use App\Workflow\State\LockerState;
use Finite\Event\PostTransitionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class LockerStateTransitionListener
{
    public function __construct(
        private LockerLockingService $lockerLockingService,
    )
    {
    }

    #[AsEventListener(event: PostTransitionEvent::class)]
    public function onOpen(PostTransitionEvent $event): void
    {
        $locker = $event->getObject();
        if (!$locker instanceof Locker) {
            return;
        }

        if (LockerState::TRANSITION_OPEN !== $event->getTransition()->getName()) {
            return;
        }

        $this->lockerLockingService->open($locker);
    }
}
