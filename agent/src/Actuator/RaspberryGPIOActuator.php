<?php
declare(strict_types=1);

namespace App\Actuator;

use App\Entity\LockerLockState;
use App\Gpio\LibgpiodPinDriver;
use Psr\Log\LoggerInterface;

final readonly class RaspberryGPIOActuator implements Actuator
{
    public function __construct(
        private LibgpiodPinDriver $gpio,
        private LoggerInterface $logger,
        private ?array $gpioMap,
    )
    {
    }

    public function lock(LockerLockState $lockerLockState): void
    {
        $this->logger->info('Locking locker (via GPIO) ' . $lockerLockState->lockerCode);

        $gpio = $this->findGpio($lockerLockState->lockerCode);

        try {
            $this->gpio->setLineValue($gpio, false);
        } catch (\RuntimeException $e) {
            $this->logger->error(
                'Error while locking locker ' . $lockerLockState->lockerCode . ' : ' . $e->getMessage(),
                ['exception' => $e],
            );
        }
    }

    public function unlock(LockerLockState $lockerLockState): void
    {
        $this->logger->info('Unlocking locker (via GPIO) ' . $lockerLockState->lockerCode);

        $gpio = $this->findGpio($lockerLockState->lockerCode);

        try {
            $this->gpio->setLineValue($gpio, true);
        } catch (\RuntimeException $e) {
            $this->logger->error(
                'Error while unlocking locker ' . $lockerLockState->lockerCode . ' : ' . $e->getMessage(),
                ['exception' => $e],
            );
        }
    }

    public function supports(LockerLockState $lockerLockState): bool
    {
        return null !== $this->gpioMap;
    }

    private function findGpio(string $lockerCode): int
    {
        if (!isset($this->gpioMap[$lockerCode])) {
            throw new \InvalidArgumentException('Locker "' . $lockerCode . '" not found');
        }

        return $this->gpioMap[$lockerCode];
    }
}
