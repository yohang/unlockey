<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/', name: 'homepage')]
final readonly class Homepage
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function __invoke(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('dashboard'));
    }
}
