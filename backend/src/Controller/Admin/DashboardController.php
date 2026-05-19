<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Locker;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    #[\Override]
    public function index(): Response
    {
        return $this->redirect($this->adminUrlGenerator->setController(LockerController::class)->generateUrl());
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToCrud('Lockers', 'fa fa-box', Locker::class);
    }
}
