<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Locker;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SearchLockerType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {

    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'locker',
                EntityType::class,
                [
                    'class'        => Locker::class,
                    'choice_label' => fn (Locker $locker) => sprintf('%s (%s)', $locker->code ?? '', $locker->name ?? ''),
                    'choice_value' => fn (?Locker $locker) => $locker ? $this->urlGenerator->generate('locker_show', ['code' => $locker->code]) : null,
                    'placeholder'  => 'Code du casier',
                    'required'     => false,
                    'label'        => '',
                    'autocomplete' => true,
                    'attr'         => [ 'data-choose-locker-target' => 'lockerSelect' ]
                ]
            );
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(
                [
                    'data_class' => null,
                ]
            );
    }
}
