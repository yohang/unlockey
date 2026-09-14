<?php
declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\LockerCommand;
use App\Entity\Locker;
use App\Repository\LockerRepository;
use Finite\StateMachine;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<LockerCommand, Locker>
 */
final readonly class LockerCommandProcessor implements ProcessorInterface
{
    public function __construct(
        private StateMachine $stateMachine,
        private LockerRepository $lockerRepository,
    )
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Locker
    {
        if (!$data instanceof LockerCommand) {
            throw new \LogicException(sprintf('Expected an instance of "%s", got "%s".', LockerCommand::class, get_debug_type($data)));
        }

        $locker = $data->locker;
        $transition = $data::transition();

        if (!$this->stateMachine->can($locker, $transition)) {
            throw new ConflictHttpException(sprintf(
                'Transition "%s" is not available from state "%s".',
                $transition,
                $locker->state->value,
            ));
        }

        $this->stateMachine->apply($locker, $transition);
        $this->lockerRepository->update();

        return $locker;
    }
}
