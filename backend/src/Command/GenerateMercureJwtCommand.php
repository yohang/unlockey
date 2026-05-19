<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(name: 'app:generate-mercure-jwt')]
final class GenerateMercureJwtCommand
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly UrlGeneratorInterface $urlGenerator,
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
            fn(string $code) => $this->urlGenerator->generate(
                    'locker_show',
                    ['code' => $code],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ) . '/*',
            $lockers
        );

        $jwt = $this->hub->getFactory()->create($topics, $topics);

        $io->title('Mercure JWT : ');
        $io->writeln($jwt);

        return Command::SUCCESS;
    }
}
