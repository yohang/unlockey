<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Locker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class LockerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Locker::class);
    }

    /**
     * @return array<int, Locker>
     */
    public function findAll(): array
    {
        return parent::findAll();
    }

    public function update(Locker $locker): void
    {
        $this->getEntityManager()->flush();
    }
}
