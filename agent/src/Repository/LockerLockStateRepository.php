<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\LockerLockState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

final class LockerLockStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LockerLockState::class);
    }

    public function findOrCreate(string $lockerCode): LockerLockState
    {
        try {
            return $this->createQueryBuilder('locker_lock_state')
                ->where('locker_lock_state.lockerCode = :lockerCode')
                ->setParameter('lockerCode', $lockerCode)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException) {
            $lockerLockState = new LockerLockState($lockerCode);
            $this->getEntityManager()->persist($lockerLockState);
            $this->getEntityManager()->flush();

            return $lockerLockState;
        }
    }

    /**
     * Persists pending changes. The flush() triggers the preUpdate actuation
     * listener (ActuateSubscriber), which drives the GPIO relay; a hardware
     * failure there aborts the flush so the DB never diverges from the latch.
     */
    public function update(LockerLockState $lockerLockState): void
    {
        $this->getEntityManager()->flush();
    }
}
