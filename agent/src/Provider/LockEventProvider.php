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
        $source = $this->connect($client);

        while (true) {
            try {
                foreach ($client->stream($source, $this->tickTime) as $chunk) {
                    if ($chunk->isTimeout()) {
                        yield from $this->tickPendingTasks($timeTasks);

                        $this->logger->debug(
                            'Mercure stream tick, {taskCount} pending tasks. (EventSource timeout)',
                            ['taskCount' => count($timeTasks)],
                        );

                        continue;
                    }

                    if ($chunk->isLast()) {
                        $this->logger->debug('Mercure stream ended, reconnecting');
                        $source->cancel();
                        $source = $this->reconnect($client);

                        continue 2;
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
                $source->cancel();
                $source = $this->reconnect($client);
            }
        }
    }

    /**
     * @param array<string, array{ticks: int, event: array{action: string, lockerCode: string}}> $timeTasks
     *
     * @return \Generator<int, array{action: string, lockerCode: string}>
     */
    private function tickPendingTasks(array &$timeTasks): \Generator
    {
        foreach ($timeTasks as $index => $task) {
            $timeTasks[$index]['ticks']--;

            if (0 === $timeTasks[$index]['ticks']) {
                yield $task['event'];

                unset($timeTasks[$index]);
            }
        }
    }

    private function reconnect(EventSourceHttpClient $client): ResponseInterface
    {
        $this->logger->debug('Reconnecting to Mercure stream');

        return $this->connect($client);
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
