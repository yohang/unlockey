<?php
declare(strict_types=1);

namespace App\Controller\App\Locker;

use App\Entity\Locker;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/app/locker/{code}', name: 'locker_show', methods: ['GET'])]
#[Template('app/locker/show.html.twig')]
final readonly class Show
{
    public function __invoke(
        #[MapEntity(mapping: ['code' => 'code'])] Locker $locker,
    ): array
    {
        return [
            'locker' => $locker,
        ];
    }
}
