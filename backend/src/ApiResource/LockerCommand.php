<?php
declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Locker;

/**
 * Base class of the "apply a transition to a locker" API commands.
 *
 * Each concrete command is its own API resource (not backed by Doctrine): the
 * {@see \App\State\LockerCommandProvider} loads the target Locker from the URI
 * and the {@see \App\State\LockerCommandProcessor} applies the Finite transition,
 * returning the updated Locker as output.
 */
abstract class LockerCommand
{
    final public function __construct(
        public readonly Locker $locker,
    )
    {
    }

    /**
     * Name of the Finite transition this command applies.
     */
    abstract public static function transition(): string;
}
