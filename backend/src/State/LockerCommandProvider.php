<?php
declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\LockerCommand;
use App\Repository\LockerRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<LockerCommand>
 */
final readonly class LockerCommandProvider implements ProviderInterface
{
    public function __construct(
        private LockerRepository $lockerRepository,
    )
    {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LockerCommand
    {
        $commandClass = $operation->getClass();
        if (null === $commandClass || !is_a($commandClass, LockerCommand::class, true)) {
            throw new \LogicException(sprintf('Operation "%s" must target a "%s" resource.', (string) $operation->getName(), LockerCommand::class));
        }

        $code = $uriVariables['code'] ?? null;
        if (!is_string($code)) {
            throw new NotFoundHttpException('Missing locker code.');
        }

        $locker = $this->lockerRepository->findOneBy(['code' => $code]);
        if (null === $locker) {
            throw new NotFoundHttpException(sprintf('Locker "%s" not found.', $code));
        }

        return new $commandClass($locker);
    }
}
