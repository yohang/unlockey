<?php
declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Locker;
use App\State\LockerCommandProcessor;
use App\State\LockerCommandProvider;
use App\Workflow\State\LockerState;

#[ApiResource(
    uriTemplate: '/lockers/{code}/close',
    operations: [
        new Post(
            status: 200,
            openapi: new Operation(
                tags: ['Locker'],
                summary: 'Close the locker',
                description: 'Applies the "close" transition. No request body. Returns 409 when the locker is already closed.',
            ),
            normalizationContext: ['groups' => [Locker::GROUP_READ]],
            input: false,
            output: Locker::class,
            read: true,
            deserialize: false,
            validate: false,
            provider: LockerCommandProvider::class,
            processor: LockerCommandProcessor::class,
        ),
    ],
    uriVariables: [
        'code' => new Link(fromClass: Locker::class, identifiers: ['code']),
    ],
)]
final class CloseLocker extends LockerCommand
{
    #[\Override]
    public static function transition(): string
    {
        return LockerState::TRANSITION_CLOSE;
    }
}
