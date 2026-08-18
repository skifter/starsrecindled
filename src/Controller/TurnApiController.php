<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Controller;

use Bellcom\StarsTurnBundle\Application\TurnSubmissionService;
use Bellcom\StarsTurnBundle\Domain\ResearchCatalog;
use Bellcom\StarsTurnBundle\Domain\TechnologyModelCatalog;
use Bellcom\StarsTurnBundle\Repository\PlayerTurnRepository;
use Bellcom\StarsTurnBundle\Repository\TurnRepository;
use Bellcom\StarsTurnBundle\Security\PlayerTokenAuthenticator;
use Bellcom\StarsTurnBundle\Service\PlayerVisibilityService;
use Doctrine\DBAL\Connection;
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
        private readonly Connection $connection,
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
                'controller_type' => $entryPlayer->getControllerType(),
                'ai_level' => $entryPlayer->getAiLevel(),
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

        $initialState = TechnologyModelCatalog::normalizeState($turn->getInitialState());
        $history = $this->visibilityHistory($gameId, $turnNumber);
        $projection = $this->visibility->project($initialState, $playerId, $history, $turnNumber);
        $previousReport = $this->previousPublishedReport($gameId, $turnNumber, $playerId);
        $research = ResearchCatalog::playerState($initialState, $playerId);
        $research['income'] = ResearchCatalog::estimateIncome($initialState, $playerId);

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
                'systems' => $projection['systemVisibility'],
                'known_system_ids' => $projection['knownSystemIds'],
                'unknown_system_count' => $projection['unknownSystemCount'],
            ],
            'research' => $research,
            'research_catalog' => ResearchCatalog::publicCatalog(),
            'model_catalog' => TechnologyModelCatalog::publicForPlayer($initialState, $playerId),
            'previous_report' => $previousReport,
            'players' => $players,
            'you' => [
                'id' => $playerId,
                'name' => $player->getDisplayName(),
                'orders' => $this->normalizeOrders($ownTurn?->getOrders() ?? []),
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
            $playerId = $player->getId() ?? throw new \LogicException('Den autentificerede spiller mangler id.');
            $orders = $this->validateOrders($orders, $turn->getInitialState(), $playerId);
            $this->submissionService->saveDraft($player, $turn, $orders);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
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
            $playerId = $player->getId() ?? throw new \LogicException('Den autentificerede spiller mangler id.');
            $orders = $this->validateOrders($orders, $turn->getInitialState(), $playerId);
            $outcome = $this->submissionService->submit($player, $turn, $orders);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
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

    /** @param array<string,mixed> $orders @return array<string,mixed> */
    private function normalizeOrders(array $orders): array
    {
        $orders['fleets'] = is_array($orders['fleets'] ?? null) ? array_values($orders['fleets']) : [];
        $orders['production'] = is_array($orders['production'] ?? null) ? array_values($orders['production']) : [];
        $orders['research'] = is_array($orders['research'] ?? null) ? array_values($orders['research']) : [];
        $orders['designs'] = is_array($orders['designs'] ?? null) ? array_values($orders['designs']) : [];

        return $orders;
    }

    /** @param array<string,mixed> $orders @param array<string,mixed> $state @return array<string,mixed> */
    private function validateOrders(array $orders, array $state, int $playerId): array
    {
        $state = TechnologyModelCatalog::normalizeState($state);
        $orders = $this->validateDesignOrders($orders, $state, $playerId);
        $orders = $this->validateResearchOrders($orders, $state, $playerId);
        return $this->validateProductionOrders($orders, $state, $playerId);
    }

    /** @param array<string,mixed> $orders @param array<string,mixed> $state @return array<string,mixed> */
    private function validateDesignOrders(array $orders, array $state, int $playerId): array
    {
        if (!array_key_exists('designs', $orders) || $orders['designs'] === null) {
            $orders['designs'] = [];
            return $orders;
        }
        if (!is_array($orders['designs'])) {
            throw new \InvalidArgumentException('Design orders must be an array.');
        }

        $normalized = [];
        $workingState = $state;
        foreach (array_values($orders['designs']) as $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException('Each design order must be a JSON object.');
            }
            $action = is_string($entry['action'] ?? null) ? trim($entry['action']) : 'create';
            $baseDesignId = is_string($entry['baseDesignId'] ?? null) ? trim($entry['baseDesignId']) : '';
            $name = is_string($entry['name'] ?? null) ? trim($entry['name']) : '';
            $componentModelIds = is_array($entry['componentModelIds'] ?? null) ? $entry['componentModelIds'] : [];
            if ($action !== 'create') {
                throw new \InvalidArgumentException(sprintf('Unknown design action %s.', $action));
            }
            if ($baseDesignId === '' || $name === '') {
                throw new \InvalidArgumentException('New ship generations require baseDesignId and name.');
            }

            $design = TechnologyModelCatalog::createDesign($workingState, $playerId, $baseDesignId, $name, $componentModelIds);
            $normalized[] = [
                'action' => 'create',
                'baseDesignId' => $baseDesignId,
                'name' => (string) $design['name'],
                'componentModelIds' => array_combine(
                    array_map(static fn (array $component): string => (string) $component['category'], $design['components']),
                    array_map(static fn (array $component): string => (string) $component['modelId'], $design['components']),
                ),
                'designId' => (string) $design['id'],
                'generation' => (int) $design['generation'],
            ];
            $workingState = TechnologyModelCatalog::appendDesign($workingState, $playerId, $design, 0);
        }

        $orders['designs'] = $normalized;
        return $orders;
    }

    /** @param array<string,mixed> $orders @param array<string,mixed> $state @return array<string,mixed> */
    private function validateProductionOrders(array $orders, array $state, int $playerId): array
    {
        if (!array_key_exists('production', $orders) || $orders['production'] === null) {
            $orders['production'] = [];
            return $orders;
        }
        if (!is_array($orders['production'])) {
            throw new \InvalidArgumentException('Production orders must be an array.');
        }

        $catalog = TechnologyModelCatalog::publicForPlayer($state, $playerId);
        $allowed = [];
        foreach ($catalog['designs'] as $design) {
            if (is_array($design) && ($design['unlocked'] ?? true) === true && is_string($design['id'] ?? null)) {
                $allowed[$design['id']] = true;
            }
        }
        foreach ($catalog['installations'] as $model) {
            if (is_array($model) && ($model['unlocked'] ?? false) === true && is_string($model['id'] ?? null)) {
                $allowed[$model['id']] = true;
            }
        }

        $systemsById = [];
        foreach (is_array($state['universe']['systems'] ?? null) ? $state['universe']['systems'] : [] as $system) {
            if (is_array($system) && is_string($system['id'] ?? null)) {
                $systemsById[$system['id']] = $system;
            }
        }

        $normalized = [];
        $upgradeFamilies = [];
        foreach (array_values($orders['production']) as $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException('Each production order must be a JSON object.');
            }
            $systemId = is_string($entry['systemId'] ?? null) ? trim($entry['systemId']) : '';
            $item = is_string($entry['item'] ?? null) ? trim($entry['item']) : '';
            $modelId = is_string($entry['modelId'] ?? null) ? trim($entry['modelId']) : '';
            $productionKind = is_string($entry['productionKind'] ?? null) ? trim($entry['productionKind']) : '';
            $sourceModelId = is_string($entry['sourceModelId'] ?? null) ? trim($entry['sourceModelId']) : '';
            if ($systemId === '' || $item === '') {
                throw new \InvalidArgumentException('Production orders require systemId and item.');
            }
            if ($productionKind !== '' && !in_array($productionKind, ['ship', 'installation', 'upgrade', 'legacy'], true)) {
                throw new \InvalidArgumentException(sprintf('Unknown production kind %s.', $productionKind));
            }
            if ($modelId !== '' && !isset($allowed[$modelId])) {
                throw new \InvalidArgumentException(sprintf('Production model %s is unknown or not unlocked.', $modelId));
            }

            $system = $systemsById[$systemId] ?? null;
            if (!is_array($system) || (int) ($system['ownerPlayerId'] ?? 0) !== $playerId) {
                throw new \InvalidArgumentException(sprintf('Production system %s is not one of your colonies.', $systemId));
            }
            $systemName = is_string($system['name'] ?? null) && trim((string) $system['name']) !== ''
                ? trim((string) $system['name'])
                : $systemId;

            if ($productionKind === 'upgrade') {
                if ($modelId === '' || $sourceModelId === '') {
                    throw new \InvalidArgumentException('Installation upgrades require sourceModelId and target modelId.');
                }
                $target = TechnologyModelCatalog::installationUpgradeDefinition($state, $playerId, $sourceModelId, $modelId);
                if ($target === null) {
                    throw new \InvalidArgumentException(sprintf('Upgrade %s -> %s is unknown, locked or not a sequential upgrade.', $sourceModelId, $modelId));
                }
                $family = (string) ($target['family'] ?? '');
                $installed = TechnologyModelCatalog::installationForFamily($system, $family);
                if ($installed === null || ($installed['modelId'] ?? null) !== $sourceModelId) {
                    throw new \InvalidArgumentException(sprintf('Upgrade source %s is not installed on %s.', $sourceModelId, $systemName));
                }
                if (TechnologyModelCatalog::pendingUpgradeForFamily($system, $family) !== null) {
                    throw new \InvalidArgumentException(sprintf('%s already has an active %s upgrade.', $systemName, (string) ($target['name'] ?? $family)));
                }
                $upgradeKey = $systemId.'|'.$family;
                if (isset($upgradeFamilies[$upgradeKey])) {
                    throw new \InvalidArgumentException(sprintf('%s already has a queued %s upgrade in this turn.', $systemName, (string) ($target['name'] ?? $family)));
                }
                $upgradeFamilies[$upgradeKey] = true;
                $source = TechnologyModelCatalog::model($sourceModelId);
                $entry = [
                    'systemId' => $systemId,
                    'item' => sprintf('Upgrade to %s', (string) $target['name']),
                    'quantity' => 1,
                    'productionKind' => 'upgrade',
                    'modelId' => $modelId,
                    'modelName' => (string) $target['name'],
                    'modelVersion' => (int) $target['version'],
                    'sourceModelId' => $sourceModelId,
                    'sourceModelVersion' => (int) ($source['version'] ?? $installed['version'] ?? 1),
                    'upgradeTurns' => max(1, (int) ($target['upgradeTurns'] ?? 1)),
                ];
                $normalized[] = $entry;
                continue;
            }

            if ($modelId !== '') {
                $model = TechnologyModelCatalog::model($modelId);
                if (is_array($model) && ($model['category'] ?? null) === 'installation') {
                    $family = (string) ($model['family'] ?? '');
                    $installed = TechnologyModelCatalog::installationForFamily($system, $family);
                    if ($installed !== null) {
                        $installedName = is_string($installed['name'] ?? null) && trim((string) $installed['name']) !== ''
                            ? trim((string) $installed['name'])
                            : $family;
                        throw new \InvalidArgumentException(sprintf('%s already has %s installed; use Upgrade instead.', $systemName, $installedName));
                    }
                }
            }

            $entry['systemId'] = $systemId;
            $entry['item'] = $item;
            $entry['quantity'] = max(1, min(10, (int) ($entry['quantity'] ?? 1)));
            if ($modelId !== '') {
                $entry['modelId'] = $modelId;
            }
            $normalized[] = $entry;
        }
        $orders['production'] = $normalized;
        return $orders;
    }

    /**
     * @param array<string,mixed> $orders
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function validateResearchOrders(array $orders, array $state, int $playerId): array
    {
        if (!array_key_exists('research', $orders) || $orders['research'] === null) {
            $orders['research'] = [];
            return $orders;
        }
        if (!is_array($orders['research'])) {
            throw new \InvalidArgumentException('Research orders must be an array.');
        }

        $entries = array_values($orders['research']);
        if (count($entries) > 1) {
            throw new \InvalidArgumentException('Only one active research technology can be selected per turn.');
        }
        if ($entries === []) {
            $orders['research'] = [];
            return $orders;
        }

        $selection = $entries[0];
        if (!is_array($selection)) {
            throw new \InvalidArgumentException('The research selection must be a JSON object.');
        }
        $technologyId = is_string($selection['technologyId'] ?? null)
            ? trim($selection['technologyId'])
            : (is_string($selection['field'] ?? null) ? trim($selection['field']) : '');
        if ($technologyId === '' || ResearchCatalog::technology($technologyId) === null) {
            throw new \InvalidArgumentException('Unknown research technology.');
        }

        $playerResearch = ResearchCatalog::playerState($state, $playerId);
        if (!ResearchCatalog::canResearch($playerResearch, $technologyId)) {
            throw new \InvalidArgumentException(sprintf('Research technology %s is completed or its prerequisites are not met.', $technologyId));
        }

        // Canonical format. Keep the legacy field/allocation values for older clients
        // while technologyId is the authoritative 0.7+ key.
        $orders['research'] = [[
            'technologyId' => $technologyId,
            'field' => $technologyId,
            'allocation' => 100,
        ]];

        return $orders;
    }

    /** @return list<array{turnNumber:int,state:array<string,mixed>}> */
    private function visibilityHistory(int $gameId, int $turnNumber): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT turn_number, initial_state, result_state
FROM stars_turn
WHERE game_id = :gameId
  AND turn_number < :turnNumber
  AND status = 'published'
ORDER BY turn_number ASC
SQL,
            ['gameId' => $gameId, 'turnNumber' => $turnNumber],
        );

        $history = [];
        foreach ($rows as $row) {
            $historicalTurn = max(1, (int) ($row['turn_number'] ?? 1));
            foreach (['initial_state', 'result_state'] as $column) {
                $state = $this->decodeJsonArray($row[$column] ?? null);
                if ($state !== []) {
                    $history[] = ['turnNumber' => $historicalTurn, 'state' => $state];
                }
            }
        }

        return $history;
    }

    /** @return array<string,mixed>|null */
    private function previousPublishedReport(int $gameId, int $turnNumber, int $playerId): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT turn_number, initial_state, result_state, player_reports, published_at
FROM stars_turn
WHERE game_id = :gameId
  AND turn_number < :turnNumber
  AND status = 'published'
ORDER BY turn_number DESC
LIMIT 1
SQL,
            ['gameId' => $gameId, 'turnNumber' => $turnNumber],
        );

        if ($row === false) {
            return null;
        }

        $reports = $this->decodeJsonArray($row['player_reports'] ?? null);
        $playerReport = $reports[(string) $playerId] ?? $reports[$playerId] ?? null;
        $data = is_array($playerReport) ? $playerReport : [];

        $initialState = $this->decodeJsonArray($row['initial_state'] ?? null);
        $resultState = $this->decodeJsonArray($row['result_state'] ?? null);
        if ($initialState !== [] && $resultState !== []) {
            $data['sightings'] = $this->visibility->contactEvents($initialState, $resultState, $playerId);
        } else {
            $data['sightings'] ??= [];
        }

        $year = is_numeric($resultState['year'] ?? null) ? (int) $resultState['year'] : null;

        return [
            'turn_number' => max(1, (int) ($row['turn_number'] ?? 1)),
            'year' => $year,
            'published_at' => $this->publishedAt($row['published_at'] ?? null),
            'data' => $data,
        ];
    }

    /** @return array<string,mixed> */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function publishedAt(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Exception) {
            return $value;
        }
    }
}
