<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\MessageHandler;

use Bellcom\StarsTurnBundle\Domain\TurnEngineInterface;
use Bellcom\StarsTurnBundle\Entity\PlayerTurn;
use Bellcom\StarsTurnBundle\Entity\Turn;
use Bellcom\StarsTurnBundle\Enum\NotificationEventType;
use Bellcom\StarsTurnBundle\Enum\TurnStatus;
use Bellcom\StarsTurnBundle\Message\GenerateTurnMessage;
use Bellcom\StarsTurnBundle\Notification\TurnNotificationPlanner;
use Bellcom\StarsTurnBundle\Repository\PlayerTurnRepository;
use Bellcom\StarsTurnBundle\Repository\TurnRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateTurnMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TurnRepository $turnRepository,
        private PlayerTurnRepository $playerTurnRepository,
        private TurnEngineInterface $turnEngine,
        private TurnNotificationPlanner $notificationPlanner,
    ) {
    }

    public function __invoke(GenerateTurnMessage $message): void
    {
        /** @var Turn|null $publishedTurn */
        $publishedTurn = $this->entityManager->wrapInTransaction(function () use ($message): ?Turn {
            $turn = $this->turnRepository->find($message->turnId);
            if ($turn === null) {
                return null;
            }

            $this->entityManager->lock($turn, LockMode::PESSIMISTIC_WRITE);
            if ($turn->getStatus() === TurnStatus::PUBLISHED) {
                return null;
            }
            if ($turn->getStatus() !== TurnStatus::QUEUED) {
                throw new \DomainException(sprintf(
                    'Runde %d har status %s og kan ikke genereres.',
                    $turn->getNumber(),
                    $turn->getStatus()->value,
                ));
            }

            $playerTurns = $this->playerTurnRepository->findForTurn($turn);
            foreach ($playerTurns as $playerTurn) {
                if ($playerTurn->getStatus() !== \Bellcom\StarsTurnBundle\Enum\PlayerTurnStatus::SUBMITTED) {
                    throw new \DomainException('Runden kan ikke genereres, før alle spillere har afleveret.');
                }
            }

            $turn->beginGeneration();
            $orders = [];
            foreach ($playerTurns as $playerTurn) {
                $playerId = $playerTurn->getPlayer()->getId();
                if ($playerId === null) {
                    throw new \LogicException('En spiller mangler id.');
                }
                $orders[(string) $playerId] = $playerTurn->getOrders();
            }

            $result = $this->turnEngine->generate($turn, $orders);
            $turn->publish($result);

            $nextTurnNumber = $turn->getNumber() + 1;
            $nextTurn = new Turn(
                $turn->getGame(),
                $nextTurnNumber,
                $result->nextState,
                rulesVersion: $turn->getRulesVersion(),
            );
            $this->entityManager->persist($nextTurn);

            foreach ($turn->getGame()->getActivePlayers() as $player) {
                $this->entityManager->persist(new PlayerTurn($nextTurn, $player));
            }

            $turn->getGame()->advanceToTurn($nextTurnNumber);
            $this->entityManager->flush();

            return $turn;
        });

        if ($publishedTurn !== null && !$this->gameHasAiPlayers($publishedTurn)) {
            // STARS_AI_PLAYERS_DEV5: synthetic AI addresses never receive mail.
            $this->notificationPlanner->plan($publishedTurn, NotificationEventType::TURN_PUBLISHED);
        }
    }


    private function gameHasAiPlayers(Turn $turn): bool
    {
        foreach ($turn->getGame()->getActivePlayers() as $player) {
            if ($player->isAi()) {
                return true;
            }
        }

        return false;
    }

}
