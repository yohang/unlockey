<?php

use App\DataFixtures\LockerFixtures;
use App\Kernel;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// The test database is an in-memory SQLite kept alive across kernel reboots by
// DAMA's static connection. Create the schema and load the locker fixtures once;
// each test then runs inside a transaction that is rolled back.
(static function (): void {
    // Must be enabled before the first connection so that the in-memory database
    // created here is the one reused by the tests (DAMA's extension enables it later).
    StaticDriver::setKeepStaticConnections(true);

    $kernel = new Kernel('test', (bool) $_SERVER['APP_DEBUG']);
    $kernel->boot();

    $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
    assert($entityManager instanceof EntityManagerInterface);

    $schemaTool = new SchemaTool($entityManager);
    $schemaTool->dropDatabase();
    $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

    (new ORMExecutor($entityManager))->execute([new LockerFixtures()], true);

    // StaticDriver opened a transaction when the connection was created: commit it so the
    // fixtures become the baseline and DAMA can start its own per-test transaction.
    StaticDriver::commit();

    $kernel->shutdown();
})();
