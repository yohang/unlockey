<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\UrlGeneratorInterface;
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
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Entity(repositoryClass: LockerRepository::class)]
#[Table]
#[HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    mercure: [
        'topics' => ['@=iri(object, ' . UrlGeneratorInterface::ABS_PATH . ')'],
        'private' => true,
    ],
)]
final class Locker implements HasTimestamp, \Stringable
{
    use TimestampImpl;

    public const string GROUP_READ = 'locker:read';

    #[Id]
    #[GeneratedValue(strategy: 'NONE')]
    #[Column(type: UuidType::NAME)]
    #[ApiProperty(identifier: false)]
    private(set) Uuid $id;

    #[Column(type: Types::STRING, enumType: LockerState::class)]
    #[Groups([self::GROUP_READ])]
    public LockerState $state = LockerState::CLOSED;

    #[NotBlank]
    #[Column(type: Types::STRING, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_READ])]
    public ?string $code = null;

    #[NotBlank]
    #[Column(type: Types::STRING)]
    #[Groups([self::GROUP_READ])]
    public ?string $name = null;

    public function __construct()
    {
        $this->id = Uuid::v6();

        $this->initialize();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getState(): LockerState
    {
        return $this->state;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name ?? 'A locker with no name';
    }
}
