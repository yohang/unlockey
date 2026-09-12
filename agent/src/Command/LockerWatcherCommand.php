<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\LockerLockState;
use App\Provider\LockEventProvider;
use App\Repository\LockerLockStateRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:locker-watcher')]
final class LockerWatcherCommand extends Command implements SignalableCommandInterface
{
    private bool $exitRequested = false;

    public function __construct(
        private readonly LockEventProvider         $lockEventProvider,
        private readonly LockerLockStateRepository $lockerLockStateRepository,
        private readonly LoggerInterface           $logger,
        private readonly ManagerRegistry           $managerRegistry,
        private readonly array                     $watchedLockerCodes,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach ($this->watchedLockerCodes as $i => $lockerCode) {
            $this->lockerLockStateRepository->findOrCreate($lockerCode);
            $rows[$lockerCode] = $i;
        }

        $table = $io->createTable();
        $table->setHeaders(['Locker Code', 'Lock State']);
        foreach ($rows as $lockerCode => $i) {
            $table->addRow([$lockerCode, $this->lockChar($this->lockerLockStateRepository->findOrCreate($lockerCode))]);
        }

        $table->render();
        foreach ($this->lockEventProvider->listen() as $event) {
            if ($this->exitRequested) {
                break;
            }

            ['action' => $action, 'code' => $lockerCode] = $event;
            $lockerLockState = $this->lockerLockStateRepository->findOrCreate($lockerCode);

            try {
                $lockerLockState->locked = 'close' === $action;
                $this->lockerLockStateRepository->update($lockerLockState);
            } catch (\Throwable $e) {
                // Actuation runs inside the flush (preUpdate listener); a hardware
                // failure rolls back the transaction and closes the EntityManager.
                // Reset it so the long-running watcher survives to the next event.
                $this->logger->error(
                    'Actuation failed for locker {lockerCode}, state not persisted: {errorMessage}',
                    ['lockerCode' => $lockerCode, 'errorMessage' => $e->getMessage(), 'exception' => $e],
                );
                $this->managerRegistry->resetManager();

                continue;
            }

            if (isset($rows[$lockerCode])) {
                $table->setRow($rows[$lockerCode], [$lockerCode, $this->lockChar($lockerLockState)]);
            } else {
                $this->logger->warning(
                    'Received event for unregistered locker code {lockerCode} (known locker codes: {knownLockerCodes})',
                    ['lockerCode' => $lockerCode, 'knownLockerCodes' => join(', ', array_keys($rows))],
                );
            }

            $table->render();
        }

        return self::SUCCESS;
    }

    private function lockChar(LockerLockState $lockerLockState): string
    {
        return $lockerLockState->locked ? '🔴 Closed' : '🟢 Open';
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal, false|int $previousExitCode = 0): int|false
    {
        $this->exitRequested = true;

        return self::SUCCESS;
    }
}
