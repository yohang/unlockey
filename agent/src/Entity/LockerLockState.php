<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table]
class LockerLockState
{
    #[Id]
    #[GeneratedValue(strategy: 'NONE')]
    #[Column(type: Types::STRING, nullable: false)]
    private(set) string $lockerCode;

    #[Column(type: Types::BOOLEAN, nullable: false)]
    public bool $locked {
        get => $this->locked;
        set {
            $this->locked = $value;
            $this->lastChange = new \DateTimeImmutable;
        }
    }

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private(set) \DateTimeImmutable $lastChange;

    public function __construct(string $lockerCode, bool $locked = true)
    {
        $this->lockerCode = $lockerCode;
        $this->locked = $locked;
        $this->lastChange = new \DateTimeImmutable;
    }

    public function getLockedCharacter(): string
    {
        return $this->locked ? '🔴 Closed' : '🟢 Open';
    }
}
