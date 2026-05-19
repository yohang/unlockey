<?php

namespace App\Controller\App\Locker;

use App\Entity\Locker;
use App\Repository\LockerRepository;
use Finite\StateMachine;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/app/locker/{code}/apply/{transition}', name: 'locker_apply_transition', methods: ['POST'])]
final class ApplyTransition
{
    public function __construct(
        private readonly StateMachine $stateMachine,
        private readonly LockerRepository $lockerRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {

    }

    public function __invoke(
        #[MapEntity(mapping: ['code' => 'code'])] Locker $locker,
        string $transition,
    ): RedirectResponse
    {
        $this->stateMachine->apply($locker, $transition);
        $this->lockerRepository->update();

        return new RedirectResponse(
            $this->urlGenerator->generate('locker_show', ['code' => $locker->code]),
        );
    }
}
