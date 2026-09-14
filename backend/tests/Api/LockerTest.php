<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Locker;
use Symfony\Bundle\MercureBundle\DataCollector\MercureDataCollector;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class LockerTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private Client $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testGetCollection(): void
    {
        $response = $this->client->request('GET', '/api/lockers');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertJsonContains([
            '@context' => '/api/contexts/Locker',
            '@id' => '/api/lockers',
            '@type' => 'Collection',
            'totalItems' => 5,
        ]);
        self::assertMatchesResourceCollectionJsonSchema(Locker::class);

        $members = array_column($response->toArray()['member'], null, 'code');
        self::assertCount(5, $members);
        self::assertSame([
            '@id' => '/api/lockers/chips-1',
            '@type' => 'Locker',
            'state' => 'closed',
            'code' => 'chips-1',
            'name' => 'Casier chips 1',
            'transitions' => ['open'],
        ], $members['chips-1']);
    }

    public function testGetItem(): void
    {
        $response = $this->client->request('GET', '/api/lockers/chips-2');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertJsonContains([
            '@context' => '/api/contexts/Locker',
            '@id' => '/api/lockers/chips-2',
            '@type' => 'Locker',
            'code' => 'chips-2',
            'name' => 'Casier chips 2',
            'state' => 'closed',
            'transitions' => ['open'],
        ]);
        self::assertMatchesResourceItemJsonSchema(Locker::class);

        $data = $response->toArray();
        self::assertArrayNotHasKey('id', $data);
        self::assertArrayNotHasKey('createdAt', $data);
        self::assertArrayNotHasKey('updatedAt', $data);
    }

    public function testGetUnknownItem(): void
    {
        $this->client->request('GET', '/api/lockers/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOpenThenClose(): void
    {
        $this->requestProfiled('POST', '/api/lockers/chips-3/open');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertJsonContains([
            '@id' => '/api/lockers/chips-3',
            'code' => 'chips-3',
            'state' => 'open',
            'transitions' => ['close'],
        ]);

        $updates = $this->publishedUpdates();
        self::assertCount(1, $updates);
        // The topic is the path IRI (same as "@id"), independent of the request scheme/host.
        self::assertSame(['/api/lockers/chips-3'], $updates[0]->getTopics());
        self::assertFalse($updates[0]->isPrivate());
        $payload = json_decode($updates[0]->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('/api/lockers/chips-3', $payload['@id']);
        self::assertSame('chips-3', $payload['code']);
        self::assertSame('open', $payload['state']);
        self::assertSame(['close'], $payload['transitions']);

        $this->requestProfiled('POST', '/api/lockers/chips-3/close');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains([
            'code' => 'chips-3',
            'state' => 'closed',
            'transitions' => ['open'],
        ]);

        $updates = $this->publishedUpdates();
        self::assertCount(1, $updates);
        $payload = json_decode($updates[0]->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('closed', $payload['state']);

        $this->client->request('GET', '/api/lockers/chips-3');
        self::assertJsonContains(['state' => 'closed']);
    }

    public function testOpenAnOpenLockerConflicts(): void
    {
        $this->client->request('POST', '/api/lockers/chips-4/open');
        self::assertResponseStatusCodeSame(200);

        $this->requestProfiled('POST', '/api/lockers/chips-4/open');

        self::assertResponseStatusCodeSame(409);
        self::assertJsonContains([
            '@type' => 'Error',
            'status' => 409,
        ]);
        self::assertCount(0, $this->publishedUpdates(), 'A refused transition must not publish a Mercure update.');

        $this->client->request('GET', '/api/lockers/chips-4');
        self::assertJsonContains(['state' => 'open']);
    }

    public function testCloseAClosedLockerConflicts(): void
    {
        $this->requestProfiled('POST', '/api/lockers/couscous/close');

        self::assertResponseStatusCodeSame(409);
        self::assertJsonContains([
            '@type' => 'Error',
            'status' => 409,
        ]);
        self::assertCount(0, $this->publishedUpdates());
    }

    public function testTransitionOnUnknownLocker(): void
    {
        $this->client->request('POST', '/api/lockers/does-not-exist/open');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTransitionsAreDocumentedInOpenApi(): void
    {
        $openApi = $this->client->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']])->toArray();

        self::assertResponseIsSuccessful();
        $schemas = $openApi['components']['schemas'];

        $properties = $this->schemaProperties($schemas['Locker.jsonld-locker.read']);
        self::assertSame(['state', 'code', 'name', 'transitions'], array_keys($properties));
        self::assertSame(
            ['type' => 'array', 'items' => ['type' => 'string'], 'readOnly' => true],
            array_intersect_key($properties['transitions'], ['type' => 1, 'items' => 1, 'readOnly' => 1]),
        );

        // Output schemas of the open/close commands are documented the same way.
        self::assertArrayHasKey('transitions', $this->schemaProperties($schemas['OpenLocker.Locker.jsonld-locker.read']));
        self::assertArrayHasKey('transitions', $this->schemaProperties($schemas['CloseLocker.Locker.jsonld-locker.read']));
    }

    /**
     * Sends a request with the profiler enabled, so that {@see publishedUpdates()} can
     * read the Mercure updates it triggered. The kernel is rebooted between requests,
     * so each profile only contains the updates of its own request.
     */
    private function requestProfiled(string $method, string $url): ResponseInterface
    {
        $this->client->enableProfiler();

        return $this->client->request($method, $url);
    }

    /**
     * Mercure updates published during the last profiled request, as recorded by
     * MercureBundle's TraceableHub in the "mercure" data collector.
     *
     * @return list<Update>
     */
    private function publishedUpdates(): array
    {
        $profile = $this->client->getProfile();
        self::assertNotFalse($profile, 'Profiler is disabled: use requestProfiled().');

        $collector = $profile->getCollector('mercure');
        self::assertInstanceOf(MercureDataCollector::class, $collector);

        $updates = [];
        foreach ($collector->getHubs()['default']['messages'] ?? [] as $message) {
            self::assertInstanceOf(Update::class, $message['object']);
            $updates[] = $message['object'];
        }

        return $updates;
    }

    /**
     * JSON-LD schemas are "allOf: [HydraItemBaseSchema, {properties: ...}]".
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    private function schemaProperties(array $schema): array
    {
        foreach ($schema['allOf'] ?? [] as $part) {
            if (isset($part['properties'])) {
                return $part['properties'];
            }
        }

        return $schema['properties'] ?? [];
    }
}
