<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Command;

use Bellcom\StarsTurnBundle\Domain\StartUniverseGenerator;
use Bellcom\StarsTurnBundle\Entity\Game;
use Bellcom\StarsTurnBundle\Entity\Player;
use Bellcom\StarsTurnBundle\Entity\PlayerTurn;
use Bellcom\StarsTurnBundle\Entity\Turn;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'stars:game:create', description: 'Opretter et spil, første runde og spillertokens.')]
final class CreateGameCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Spilnavn')
            ->addOption(
                'player',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Spiller som "Navn <email@example.net>". Gentag mindst to gange.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $playerDefinitions = $input->getOption('player');
        if (!is_array($playerDefinitions) || count($playerDefinitions) < 2) {
            $io->error('Angiv mindst to --player-værdier.');
            return Command::INVALID;
        }

        $game = new Game((string) $input->getArgument('name'));
        $this->entityManager->persist($game);

        $playersAndTokens = [];
        foreach ($playerDefinitions as $definition) {
            try {
                [$displayName, $email] = $this->parsePlayer((string) $definition);
            } catch (\InvalidArgumentException $exception) {
                $io->error($exception->getMessage());
                return Command::INVALID;
            }

            $token = bin2hex(random_bytes(32));
            $player = new Player($game, $displayName, $email, $token);
            $this->entityManager->persist($player);
            $playersAndTokens[] = [$player, $token];
        }

        // Assign database ids before the deterministic universe is generated. Fleet ownership
        // uses the real player ids, which are also the ids used by submitted turn orders.
        $this->entityManager->flush();

        $universePlayers = [];
        foreach ($playersAndTokens as [$player]) {
            $playerId = $player->getId() ?? throw new \LogicException('Spilleren mangler id efter flush.');
            $universePlayers[] = ['id' => $playerId, 'name' => $player->getDisplayName()];
        }

        $turnSeed = bin2hex(random_bytes(32));
        $initialState = (new StartUniverseGenerator())->generate($universePlayers, $turnSeed);
        $turn = new Turn(
            $game,
            1,
            $initialState,
            randomSeed: $turnSeed,
            rulesVersion: 'rekindled-0.5.1',
        );
        $this->entityManager->persist($turn);

        foreach ($playersAndTokens as [$player]) {
            $this->entityManager->persist(new PlayerTurn($turn, $player));
        }

        $this->entityManager->flush();

        $rows = [];
        foreach ($playersAndTokens as [$player, $token]) {
            $rows[] = [$player->getId(), $player->getDisplayName(), $player->getEmail(), $token];
        }

        $io->success(sprintf('Spillet "%s" blev oprettet med id %d.', $game->getName(), $game->getId()));
        $io->table(['Player ID', 'Navn', 'E-mail', 'Token (vises kun nu)'], $rows);
        $io->note(sprintf(
            'Startunivers: %d systemer og %d startflåder.',
            count($initialState['universe']['systems'] ?? []),
            count($initialState['universe']['fleets'] ?? []),
        ));
        $io->warning('Gem tokens sikkert. Databasen indeholder kun hashes.');

        return Command::SUCCESS;
    }

    /** @return array{string, string} */
    private function parsePlayer(string $definition): array
    {
        if (preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $definition, $matches)) {
            return [trim($matches[1]), trim($matches[2])];
        }

        $email = trim($definition);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [strstr($email, '@', true) ?: $email, $email];
        }

        throw new \InvalidArgumentException(sprintf('Ugyldig --player-værdi: %s', $definition));
    }
}
