<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Locker;
use App\Service\LockerLockingService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class LockerController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly LockerLockingService $lockerLockingService,
    )
    {
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'Identifiant')->hideOnForm();
        yield TextField::new('code', 'Code');
        yield TextField::new('name', 'Nom');

        yield DateTimeField::new('createdAt', 'Créé le')->onlyOnDetail();
        yield DateTimeField::new('updatedAt', 'Mis à jour le')->onlyOnDetail();
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $open = Action::new('open', 'Ouvrir le locker')
            ->linkToCrudAction('openLocker');

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $open)
            ->add(Crud::PAGE_DETAIL, $open)
            ->add(Crud::PAGE_EDIT, $open);
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->showEntityActionsInlined()
            ->setDefaultSort(['code' => 'ASC']);
    }

    #[AdminRoute(path: '/open/{entityId}', name: 'open_locker')]
    public function openLocker(AdminContext $context): RedirectResponse
    {
        $locker = $context->getEntity()->getInstance();

        assert($locker instanceof Locker);
        $this->lockerLockingService->open($locker);

        $this->addFlash('success', 'Le locker a été ouvert avec succès !');

        return new RedirectResponse($this->adminUrlGenerator->unsetAll()->setController(LockerController::class)->generateUrl());
    }

    #[\Override]
    public static function getEntityFqcn(): string
    {
        return Locker::class;
    }
}
