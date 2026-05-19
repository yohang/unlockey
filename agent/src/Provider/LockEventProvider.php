<?php
declare(strict_types=1);

namespace App\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class LockEventProvider
{
    public function __construct(
        private HubInterface    $hub,
        private LoggerInterface $logger,
        private int             $tickTime,
        private int             $unlockTime,
    )
    {
    }

    /**
     * @return \Generator<int, array{action: string, lockerCode: string}>
     * @throws JsonException
     * @throws TransportExceptionInterface
     */
    public function listen(): \Generator
    {
        $httpClient = HttpClient::create();
        $client = new EventSourceHttpClient($httpClient);

        /**
         * @var array<string, array{ticks: int, event: array{action: string, lockerCode: string}}> $timeTasks
         */
        $timeTasks = [];
        while (true) {
            $source = $this->connect($client);

            try {
                foreach ($client->stream($source, $this->tickTime) as $chunk) {
                    if ($chunk->isTimeout()) {
                        foreach ($timeTasks as $index => $task) {
                            $timeTasks[$index]['ticks']--;

                            if (0 === $timeTasks[$index]['ticks']) {
                                yield $task['event'];
                                unset($timeTasks[$index]);
                            }
                        }

                        continue;
                    }

                    if ($chunk->isLast()) {
                        break;
                    }

                    if ($chunk instanceof ServerSentEvent) {
                        $data = $chunk->getArrayData();
                        if ($data['action'] === 'open') {
                            $timeTasks[$data['code']] = [
                                'ticks' => $this->unlockTime / $this->tickTime,
                                'event' => [...$data, 'action' => 'close'],
                            ];
                        }

                        yield $data;
                    }
                }
            } catch (\LogicException|TransportExceptionInterface $e) {
                $this->logger->warning(
                    'Mercure stream dropped, reconnecting: {errorMessage}',
                    [
                        'exception' => $e,
                        'errorMessage' => $e->getMessage(),
                    ]
                );
            } finally {
                $source->cancel();
            }
        }
    }

    private function connect(EventSourceHttpClient $client): ResponseInterface
    {
        return $client->connect(
            $this->hub->getUrl() . '?topic=*',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->hub->getProvider()->getJwt(),
                ],
            ],
        );
    }
}
