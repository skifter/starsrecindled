<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Controller;

use Bellcom\StarsTurnBundle\Application\TurnSubmissionService;
use Bellcom\StarsTurnBundle\Repository\PlayerTurnRepository;
use Bellcom\StarsTurnBundle\Repository\TurnRepository;
use Bellcom\StarsTurnBundle\Security\PlayerTokenAuthenticator;
use Bellcom\StarsTurnBundle\Service\PlayerVisibilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stars/api/games/{gameId}/turns/{turnNumber}', requirements: ['gameId' => '\\d+', 'turnNumber' => '\\d+'])]
final class TurnApiController extends AbstractController
{
    public function __construct(
        private readonly PlayerTokenAuthenticator $authenticator,
        private readonly TurnRepository $turnRepository,
        private readonly PlayerTurnRepository $playerTurnRepository,
        private readonly TurnSubmissionService $submissionService,
        private readonly PlayerVisibilityService $visibility,
    ) {
    }

    #[Route('', name: 'stars_turn_status', methods: ['GET'])]
    public function status(int $gameId, int $turnNumber, Request $request): JsonResponse
    {
        $player = $this->authenticator->authenticate($gameId, $request);
        $turn = $this->turnRepository->findForGameAndNumber($gameId, $turnNumber);
        if ($turn === null) {
            return $this->json(['error' => 'Runden findes ikke.'], Response::HTTP_NOT_FOUND);
        }

        $playerTurns = $this->playerTurnRepository->findForTurn($turn);
        $ownTurn = null;
        $players = [];

        foreach ($playerTurns as $playerTurn) {
            $entryPlayer = $playerTurn->getPlayer();
            $players[] = [
                'id' => $entryPlayer->getId(),
                'name' => $entryPlayer->getDisplayName(),
                'submitted' => $playerTurn->getSubmittedAt() !== null,
                'submitted_at' => $playerTurn->getSubmittedAt()?->format(DATE_ATOM),
            ];
            if ($entryPlayer === $player) {
                $ownTurn = $playerTurn;
            }
        }

        $playerId = $player->getId();
        if ($playerId === null) {
            throw new \LogicException('Den autentificerede spiller mangler id.');
        }
        $projection = $this->visibility->project($turn->getInitialState(), $playerId);

        $previousReport = null;
        if ($turnNumber > 1) {
            $previousTurn = $this->turnRepository->findForGameAndNumber($gameId, $turnNumber - 1);
            if ($previousTurn !== null) {
                $reports = $previousTurn->getPlayerReports();
                $playerReport = $reports[(string) $playerId] ?? null;
                $resultState = $previousTurn->getResultState();
                $previousReport = [
                    'turn_number' => $previousTurn->getNumber(),
                    'year' => is_array($resultState) && is_numeric($resultState['year'] ?? null) ? (int) $resultState['year'] : null,
                    'published_at' => $previousTurn->getPublishedAt()?->format(DATE_ATOM),
                    'data' => is_array($playerReport) ? $playerReport : null,
                ];
            }
        }

        return $this->json([
            'game' => [
                'id' => $turn->getGame()->getId(),
                'name' => $turn->getGame()->getName(),
                'current_turn' => $turn->getGame()->getCurrentTurnNumber(),
            ],
            'turn' => [
                'number' => $turn->getNumber(),
                'status' => $turn->getStatus()->value,
                'rules_version' => $turn->getRulesVersion(),
                'queued_at' => $turn->getQueuedAt()?->format(DATE_ATOM),
                'published_at' => $turn->getPublishedAt()?->format(DATE_ATOM),
            ],
            'state' => $projection['state'],
            'visibility' => [
                'sensor_system_ids' => $projection['sensorSystemIds'],
                'visible_enemy_fleets' => $projection['visibleEnemyFleetCount'],
                'colony_sensor_ranges' => $projection['colonySensorRanges'],
            ],
            'previous_report' => $previousReport,
            'players' => $players,
            'you' => [
                'id' => $playerId,
                'name' => $player->getDisplayName(),
                'orders' => $ownTurn?->getOrders() ?? [],
                'submitted' => $ownTurn?->getSubmittedAt() !== null,
            ],
        ]);
    }

    #[Route('/draft', name: 'stars_turn_draft', methods: ['PUT'])]
    public function draft(int $gameId, int $turnNumber, Request $request): JsonResponse
    {
        $player = $this->authenticator->authenticate($gameId, $request);
        $turn = $this->turnRepository->findForGameAndNumber($gameId, $turnNumber);
        if ($turn === null) {
            return $this->json(['error' => 'Runden findes ikke.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
            $orders = $payload['orders'] ?? null;
            if (!is_array($orders)) {
                return $this->json(['error' => 'Feltet orders skal være et JSON-objekt.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->submissionService->saveDraft($player, $turn, $orders);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\JsonException) {
            return $this->json(['error' => 'Ugyldig JSON.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['saved' => true]);
    }

    #[Route('/submit', name: 'stars_turn_submit', methods: ['POST'])]
    public function submit(int $gameId, int $turnNumber, Request $request): JsonResponse
    {
        $player = $this->authenticator->authenticate($gameId, $request);
        $turn = $this->turnRepository->findForGameAndNumber($gameId, $turnNumber);
        if ($turn === null) {
            return $this->json(['error' => 'Runden findes ikke.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
            $orders = $payload['orders'] ?? null;
            if (!is_array($orders)) {
                return $this->json(['error' => 'Feltet orders skal være et JSON-objekt.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $outcome = $this->submissionService->submit($player, $turn, $orders);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\JsonException) {
            return $this->json(['error' => 'Ugyldig JSON.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'submitted' => true,
            'all_players_submitted' => $outcome->allPlayersSubmitted,
            'submitted_players' => $outcome->submittedPlayers,
            'total_players' => $outcome->totalPlayers,
        ]);
    }

    #[Route('/reopen', name: 'stars_turn_reopen', methods: ['POST'])]
    public function reopen(int $gameId, int $turnNumber, Request $request): JsonResponse
    {
        $player = $this->authenticator->authenticate($gameId, $request);
        $turn = $this->turnRepository->findForGameAndNumber($gameId, $turnNumber);
        if ($turn === null) {
            return $this->json(['error' => 'Runden findes ikke.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->submissionService->reopen($player, $turn);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['reopened' => true]);
    }
}
