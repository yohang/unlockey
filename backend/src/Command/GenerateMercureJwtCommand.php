<?php

namespace App\Command;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use App\Entity\Locker;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;

#[AsCommand(
    name: 'app:generate-mercure-jwt',
    description: 'Generates a long-lived Mercure JWT granting publish/subscribe on lockers matching URL Patterns',
)]
final class GenerateMercureJwtCommand
{
    /**
     * Stands for the locker code while generating the IRI: the router would percent-encode
     * the URL Pattern characters (":", "(", "\\"...), so the pattern is substituted afterwards.
     */
    private const string CODE_PLACEHOLDER = '__code__';

    public function __construct(
        private readonly HubInterface $hub,
        private readonly IriConverterInterface $iriConverter,
    )
    {
    }

    /**
     * @param string[] $codePatterns
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'WHATWG URL Patterns of the locker codes, e.g. "chips-:number(\\d+)" or a plain code like "couscous"')]
        array $codePatterns,
    ): int
    {
        $iriTemplate = $this->iriConverter->getIriFromResource(
            Locker::class,
            UrlGeneratorInterface::ABS_PATH,
            null,
            ['uri_variables' => ['code' => self::CODE_PLACEHOLDER]],
        );

        // Mercure 1.0 "urlpattern" matcher: one pattern covers every matching locker topic.
        $topics = array_map(
            fn (string $pattern) => str_replace(self::CODE_PLACEHOLDER, $pattern, $iriTemplate),
            $codePatterns,
        );

        $factory = $this->hub->getFactory();
        if (null === $factory) {
            $io->error('No token factory configured for the Mercure hub (check mercure.hubs.default.jwt).');

            return Command::FAILURE;
        }

        // Long-lived token for the agent. The hub's factory adds the protocol 1.0 claims
        // (typ at+jwt, iss, aud, sub, client_id, iat, jti), only the expiration is set here.
        $jwt = $factory->create(
            [new Grant([Grant::ACTION_PUBLISH, Grant::ACTION_SUBSCRIBE], ['urlpattern' => $topics])],
            ['exp' => new \DateTimeImmutable('+10 years')],
        );

        $io->title('Mercure JWT : ');
        $io->listing($topics);
        $io->writeln($jwt);

        return Command::SUCCESS;
    }
}
