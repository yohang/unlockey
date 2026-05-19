<?php
declare(strict_types=1);

namespace App\Behavior\Impl;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;

trait TimestampImpl
{
    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private ?\DateTimeImmutable $createdAt = null;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function initialize(): void
    {
        $this->createdAt = new \DateTimeImmutable;
        $this->updatedAt = new \DateTimeImmutable;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable;
    }
}
