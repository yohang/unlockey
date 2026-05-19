<?php
declare(strict_types=1);

namespace App\Entity;

use App\Behavior\HasTimestamp;
use App\Behavior\Impl\TimestampImpl;
use App\Repository\LockerRepository;
use App\Workflow\State\LockerState;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Entity(repositoryClass: LockerRepository::class)]
#[Table]
#[HasLifecycleCallbacks]
class Locker implements HasTimestamp, \Stringable
{
    use TimestampImpl;

    #[Id]
    #[GeneratedValue(strategy: 'NONE')]
    #[Column(type: UuidType::NAME)]
    private(set) Uuid $id;

    #[Column(type: Types::STRING, enumType: LockerState::class)]
    public LockerState $state = LockerState::CLOSED;

    #[NotBlank]
    #[Column(type: Types::STRING)]
    public ?string $code = null;

    #[NotBlank]
    #[Column(type: Types::STRING)]
    public  ?string $name = null;

    public function __construct()
    {
        $this->id = Uuid::v6();

        $this->initialize();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name ?? 'A locker with no name';
    }
}
