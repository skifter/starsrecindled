<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Application;

use Bellcom\StarsTurnBundle\Entity\Player;
use Bellcom\StarsTurnBundle\Entity\Turn;
use Bellcom\StarsTurnBundle\Enum\NotificationEventType;
use Bellcom\StarsTurnBundle\Enum\PlayerTurnStatus;
use Bellcom\StarsTurnBundle\Enum\TurnStatus;
use Bellcom\StarsTurnBundle\Message\GenerateTurnMessage;
use Bellcom\StarsTurnBundle\Notification\TurnNotificationPlanner;
use Bellcom\StarsTurnBundle\Repository\PlayerTurnRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class TurnSubmissionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerTurnRepository $playerTurnRepository,
        private TurnNotificationPlanner $notificationPlanner,
        private MessageBusInterface $messageBus,
    ) {
    }

    /** @param array<string, mixed> $orders */
    public function saveDraft(Player $player, Turn $turn, array $orders): void
    {
        $this->assertSameGame($player, $turn);
        if ($turn->getStatus() !== TurnStatus::OPEN) {
            throw new \DomainException('Runden er ikke åben.');
        }

        $playerTurn = $this->playerTurnRepository->findForTurnAndPlayer($turn, $player)
            ?? throw new \DomainException('Spilleren er ikke med i denne runde.');

        $playerTurn->saveDraft($orders);
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $orders */
    public function submit(Player $player, Turn $turn, array $orders): SubmissionOutcome
    {
        $this->assertSameGame($player, $turn);

        /** @var array{outcome: SubmissionOutcome, turn: Turn} $result */
        $result = $this->entityManager->wrapInTransaction(function () use ($player, $turn, $orders): array {
            $this->entityManager->lock($turn, LockMode::PESSIMISTIC_WRITE);

            if ($turn->getStatus() !== TurnStatus::OPEN) {
                throw new \DomainException('Runden er ikke længere åben.');
            }

            $playerTurn = $this->playerTurnRepository->findForTurnAndPlayer($turn, $player)
                ?? throw new \DomainException('Spilleren er ikke med i denne runde.');
            $playerTurn->submit($orders);
            $this->entityManager->flush();

            $allPlayerTurns = $this->playerTurnRepository->findForTurn($turn);
            $total = count($allPlayerTurns);
            $submitted = count(array_filter(
                $allPlayerTurns,
                static fn ($entry): bool => $entry->getStatus() === PlayerTurnStatus::SUBMITTED,
            ));
            $allSubmitted = $total > 0 && $submitted === $total;

            if ($allSubmitted) {
                $turn->queue();
                $this->entityManager->flush();
            }

            return [
                'outcome' => new SubmissionOutcome($allSubmitted, $submitted, $total),
                'turn' => $turn,
            ];
        });

        if ($result['outcome']->allPlayersSubmitted) {
            $this->notificationPlanner->plan($result['turn'], NotificationEventType::ALL_PLAYERS_SUBMITTED);
            $turnId = $result['turn']->getId() ?? throw new \LogicException('Runden mangler id.');
            $this->messageBus->dispatch(new GenerateTurnMessage($turnId));
        }

        return $result['outcome'];
    }

    public function reopen(Player $player, Turn $turn): void
    {
        $this->assertSameGame($player, $turn);
        if ($turn->getStatus() !== TurnStatus::OPEN) {
            throw new \DomainException('Runden kan ikke genåbnes, efter at generering er kølagt.');
        }

        $playerTurn = $this->playerTurnRepository->findForTurnAndPlayer($turn, $player)
            ?? throw new \DomainException('Spilleren er ikke med i denne runde.');
        $playerTurn->reopen();
        $this->entityManager->flush();
    }

    private function assertSameGame(Player $player, Turn $turn): void
    {
        if ($player->getGame() !== $turn->getGame()) {
            throw new \DomainException('Spilleren tilhører ikke dette spil.');
        }
    }
}
