<?php
declare(strict_types=1);

namespace App\Actuator;

use App\Entity\LockerLockState;
use App\Gpio\LibgpiodPinDriver;
use Psr\Log\LoggerInterface;

final readonly class RaspberryGPIOActuator implements Actuator
{
    // Relay is active-low: energising the line (true) releases the latch.
    private const bool LINE_LOCKED = false;
    private const bool LINE_UNLOCKED = true;

    /**
     * @param array<string, int> $gpioMap
     */
    public function __construct(
        private LibgpiodPinDriver $gpio,
        private LoggerInterface $logger,
        private array $gpioMap,
    )
    {
        $this->gpio->setup(...array_values($this->gpioMap));
    }

    public function lock(LockerLockState $lockerLockState): void
    {
        $this->logger->info('Locking locker (via GPIO) ' . $lockerLockState->lockerCode);

        $this->gpio->setLineValue($this->findGpio($lockerLockState->lockerCode), self::LINE_LOCKED);
    }

    public function unlock(LockerLockState $lockerLockState): void
    {
        $this->logger->info('Unlocking locker (via GPIO) ' . $lockerLockState->lockerCode);

        $this->gpio->setLineValue($this->findGpio($lockerLockState->lockerCode), self::LINE_UNLOCKED);
    }

    private function findGpio(string $lockerCode): int
    {
        if (!isset($this->gpioMap[$lockerCode])) {
            throw new \InvalidArgumentException('Locker "' . $lockerCode . '" not found');
        }

        return $this->gpioMap[$lockerCode];
    }
}
