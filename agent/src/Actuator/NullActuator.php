<?php
declare(strict_types=1);

namespace App\Actuator;

use App\Entity\LockerLockState;
use Psr\Log\LoggerInterface;

/**
 * No-op actuator used when no GPIO hardware is available (e.g. local dev).
 */
final readonly class NullActuator implements Actuator
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function lock(LockerLockState $lockerLockState): void
    {
        $this->logger->info('[null-gpio] would lock ' . $lockerLockState->lockerCode);
    }

    public function unlock(LockerLockState $lockerLockState): void
    {
        $this->logger->info('[null-gpio] would unlock ' . $lockerLockState->lockerCode);
    }
}
