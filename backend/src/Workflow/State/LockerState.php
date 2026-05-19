<?php

namespace App\Workflow\State;

use Finite\State;
use Finite\Transition\Transition;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum LockerState: string implements State, TranslatableInterface
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public const string TRANSITION_OPEN = 'open';
    public const string TRANSITION_CLOSE = 'close';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::OPEN => 'Ouvert',
            default => 'Fermé',
        };
    }

    #[\Override]
    public static function getTransitions(): array
    {
        return [
            new Transition(self::TRANSITION_OPEN, [self::CLOSED], self::OPEN, ['label' => 'Ouvrir']),
            new Transition(self::TRANSITION_CLOSE, [self::OPEN], self::CLOSED, ['label' => 'Fermer']),
        ];
    }
}
