<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Locker;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LockerLockingService
{
    public function __construct(
        private HubInterface          $hub,
        private UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function open(Locker $locker): void
    {
        $id = $this->urlGenerator->generate(
            'locker_show',
            ['code' => $locker->code],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $update = new Update(
            $id . '/open',
            json_encode(['@id' => $id, 'code' => $locker->code, 'action' => 'open']),
            true,
        );

        $this->hub->publish($update);
    }

    public function close(Locker $locker): void
    {
        $id = $this->urlGenerator->generate(
            'locker_show',
            ['code' => $locker->code],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $update = new Update(
            $id . '/open',
            json_encode(['@id' => $id, 'code' => $locker->code, 'action' => 'close']),
            true,
        );

        $this->hub->publish($update);
    }
}
