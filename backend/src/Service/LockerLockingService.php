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
            $this->encodePayload(['@id' => $id, 'code' => $locker->code, 'action' => 'open']),
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
            $this->encodePayload(['@id' => $id, 'code' => $locker->code, 'action' => 'close']),
            true,
        );

        $this->hub->publish($update);
    }

    /**
     * @param array{ @id: string, code: string|null, action: 'open'|'close' } $payload
     */
    private function encodePayload(array $payload): string
    {
        $encoded = json_encode($payload);
        if (false === $encoded) {
            throw new \LogicException('Unable to encode locker update payload.');
        }

        return $encoded;
    }
}
