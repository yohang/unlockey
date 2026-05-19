<?php
declare(strict_types=1);

namespace App\Controller\App;

use App\Entity\User;
use App\Form\SearchLockerType;
use App\Repository\LockerRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/app', name: 'dashboard')]
#[Template('app/dashboard.html.twig')]
final readonly class Dashboard
{
    public function __construct(
        private LockerRepository     $lockerRepository,
        private FormFactoryInterface $formFactory,
    )
    {
    }

    public function __invoke(): array
    {
        $searchForm = $this->formFactory->create(SearchLockerType::class);

        return [
            'lockers' => $this->lockerRepository->findAll(),
            'searchForm' => $searchForm->createView(),
        ];
    }
}
