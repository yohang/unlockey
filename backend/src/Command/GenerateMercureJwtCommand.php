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

#[AsCommand(name: 'app:generate-mercure-jwt')]
final class GenerateMercureJwtCommand
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly IriConverterInterface $iriConverter,
    )
    {
    }

    /**
     * @param string[] $lockers
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument] array $lockers,
    ): int
    {
        $topics = array_map(
            fn(string $code) => $this->iriConverter->getIriFromResource(
                Locker::class,
                UrlGeneratorInterface::ABS_PATH,
                null,
                ['uri_variables' => ['code' => $code]],
            ),
            $lockers
        );

        $factory = $this->hub->getFactory();
        if (null === $factory) {
            $io->error('No token factory configured for the Mercure hub (check mercure.hubs.default.jwt).');

            return Command::FAILURE;
        }

        $jwt = $factory->create($topics, $topics);

        $io->title('Mercure JWT : ');
        $io->listing($topics);
        $io->writeln($jwt);

        return Command::SUCCESS;
    }
}
