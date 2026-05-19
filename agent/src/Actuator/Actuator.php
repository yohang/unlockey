<?php
declare(strict_types=1);

namespace App\Actuator;

use App\Entity\LockerLockState;

interface Actuator
{
    public function lock(LockerLockState $lockerLockState): void;

    public function unlock(LockerLockState $lockerLockState): void;

    public function supports(LockerLockState $lockerLockState): bool;
}
