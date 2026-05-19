<?php
declare(strict_types=1);

namespace App\Entity;

use App\Behavior\HasTimestamp;
use App\Behavior\Impl\TimestampImpl;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Entity(repositoryClass: UserRepository::class)]
#[Table(name: 'app_user')]
#[HasLifecycleCallbacks]
final class User implements HasTimestamp, UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface, \Stringable
{
    use TimestampImpl;

    #[Id]
    #[GeneratedValue(strategy: 'NONE')]
    #[Column(type: UuidType::NAME)]
    private(set) Uuid $id;

    #[NotBlank]
    #[Column(type: Types::STRING, length: 255, nullable: false)]
    public ?string $email = null;

    #[NotBlank]
    #[Length(min: 5)]
    public ?string $plainPassword = null;

    #[Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $password = null;

    #[Column(type: Types::JSON)]
    private array $roles = ['ROLE_USER'];

    public function __construct()
    {
        $this->id = Uuid::v6();

        $this->initialize();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        if (null === $this->email || '' === $this->email) {
            throw new \LogicException('Trying to access user identifier on a user without email.');
        }

        return $this->email;
    }

    #[\Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $this->email === $user->email;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->email ?? 'A user with no email';
    }
}
