<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Actuator\Actuator;
use App\Entity\LockerLockState;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class ActuateSubscriber
{
    public function __construct(private Actuator $actuator)
    {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof LockerLockState) {
            return;
        }

        if (!$args->hasChangedField('locked')) {
            return;
        }

        $locked = $args->getNewValue('locked');
        if ($locked) {
            $this->actuator->lock($entity);
        } else {
            $this->actuator->unlock($entity);
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate => 'preUpdate',
        ];
    }
}
