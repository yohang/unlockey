<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Locker;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class LockerFixtures extends Fixture
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $locker = new Locker;
        $locker->code = 'chips-1';
        $locker->name = 'Casier chips 1';
        $manager->persist($locker);

        $locker = new Locker;
        $locker->code = 'chips-2';
        $locker->name = 'Casier chips 2';
        $manager->persist($locker);

        $locker = new Locker;
        $locker->code = 'chips-3';
        $locker->name = 'Casier chips 3';
        $manager->persist($locker);

        $locker = new Locker;
        $locker->code = 'chips-4';
        $locker->name = 'Casier chips 4';
        $manager->persist($locker);

        $locker = new Locker;
        $locker->code = 'couscous';
        $locker->name = 'Test 1 (Couscous)';
        $manager->persist($locker);

        $manager->flush();
    }
}
