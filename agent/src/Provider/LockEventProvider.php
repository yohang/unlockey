<?php
declare(strict_types=1);

namespace App\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class LockEventProvider
{
    private const string STATE_OPEN = 'open';

    public function __construct(
        private LoggerInterface $logger,
        private array           $watchedLockerCodes,
        private string          $mercureUrl,
        private string          $mercureJwt,
        private int             $tickTime,
        private int             $unlockTime,
    )
    {
    }

    /**
     * @return \Generator<int, array{action: string, code: string}>
     * @throws JsonException
     * @throws TransportExceptionInterface
     */
    public function listen(): \Generator
    {
        $httpClient = HttpClient::create();
        $client = new EventSourceHttpClient($httpClient);

        /**
         * @var array<string, array{ticks: int, event: array{action: string, code: string}}> $timeTasks
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
                        $event = $this->toLockEvent($chunk->getArrayData());
                        if (null === $event) {
                            continue;
                        }

                        if (self::STATE_OPEN === $event['action']) {
                            $timeTasks[$event['code']] = [
                                'ticks' => $this->unlockTime / $this->tickTime,
                                'event' => [...$event, 'action' => 'close'],
                            ];
                        }

                        yield $event;
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
     * Maps a serialized Locker (API Platform Mercure payload) to an open/close event.
     *
     * @param array<string, mixed> $data
     *
     * @return array{action: string, code: string}|null
     */
    private function toLockEvent(array $data): ?array
    {
        $code = $data['code'] ?? null;
        $state = $data['state'] ?? null;

        if (!is_string($code) || !is_string($state)) {
            $this->logger->warning('Ignoring Mercure update without "code" or "state": {payload}', ['payload' => json_encode($data)]);

            return null;
        }

        if (!in_array($code, $this->watchedLockerCodes, true)) {
            $this->logger->debug('Ignoring Mercure update for unwatched locker {code}', ['code' => $code]);

            return null;
        }

        return [
            'action' => self::STATE_OPEN === $state ? 'open' : 'close',
            'code' => $code,
        ];
    }

    /**
     * @param array<string, array{ticks: int, event: array{action: string, code: string}}> $timeTasks
     *
     * @return \Generator<int, array{action: string, code: string}>
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
            $this->mercureUrl . '?match=*',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->mercureJwt,
                ],
            ],
        );
    }
}
