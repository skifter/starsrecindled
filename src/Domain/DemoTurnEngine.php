<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

use Bellcom\StarsTurnBundle\Entity\Turn;

final class DemoTurnEngine implements TurnEngineInterface
{
    public function generate(Turn $turn, array $submittedOrders): TurnGenerationResult
    {
        $state = $turn->getInitialState();
        $currentYear = (int) ($state['year'] ?? 2400);

        ksort($submittedOrders, SORT_STRING);

        $nextState = $state;
        $nextState['year'] = $currentYear + 1;
        $nextState['last_turn'] = $turn->getNumber();
        $nextState['rules_version'] = $turn->getRulesVersion();
        $nextState['seed'] = $turn->getRandomSeed();
        $nextState['submitted_orders'] = $submittedOrders;

        $systems = is_array($nextState['universe']['systems'] ?? null)
            ? $nextState['universe']['systems']
            : [];
        $routes = is_array($nextState['universe']['routes'] ?? null)
            ? $nextState['universe']['routes']
            : [];
        $fleets = is_array($nextState['universe']['fleets'] ?? null)
            ? $nextState['universe']['fleets']
            : [];
        $researchByPlayer = is_array($nextState['research'] ?? null)
            ? $nextState['research']
            : [];

        $systemIds = [];
        $startingOwners = [];
        foreach ($systems as $index => $system) {
            if (is_array($system) && is_string($system['id'] ?? null)) {
                $systemId = (string) $system['id'];
                $systemIds[$systemId] = $index;
                $startingOwners[$systemId] = isset($system['ownerPlayerId']) && is_numeric($system['ownerPlayerId'])
                    ? (int) $system['ownerPlayerId']
                    : null;
            }
        }

        // Colonies receive their resource income once per generated turn. Industry
        // research increases the credited amount without permanently mutating the
        // base income value shown on the colony.
        foreach ($systems as $index => $system) {
            if (!is_array($system) || ($system['ownerPlayerId'] ?? null) === null) {
                continue;
            }

            $ownerPlayerId = (int) $system['ownerPlayerId'];
            $researchState = ResearchCatalog::playerState(['research' => $researchByPlayer], $ownerPlayerId);
            $industryBonusPercent = (int) ($researchState['modifiers']['industryIncomePercent'] ?? 0);
            $resources = is_array($system['resources'] ?? null) ? $system['resources'] : [];
            foreach ($resources as $resourceIndex => $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $income = max(0, (int) ($resource['income'] ?? 0));
                if (($resource['id'] ?? null) === 'industry' && $industryBonusPercent > 0) {
                    $income += (int) floor($income * $industryBonusPercent / 100);
                }
                $resources[$resourceIndex]['value'] = max(0, (int) ($resource['value'] ?? 0)) + $income;
            }
            $systems[$index]['resources'] = array_values($resources);
        }

        $reports = [];
        foreach ($submittedOrders as $playerId => $orders) {
            $movements = [];
            $colonizations = [];
            $productions = [];
            $researchCompleted = [];
            $researchProgress = null;
            $warnings = [];
            $movedFleetIds = [];
            $playerIdInt = (int) $playerId;
            $playerResearch = ResearchCatalog::playerState(['research' => $researchByPlayer], $playerIdInt);
            $researchIncome = ResearchCatalog::estimateIncome($state, $playerIdInt);
            $movementRange = max(1, (int) ($playerResearch['modifiers']['fleetMovementRange'] ?? 1));
            $fleetOrders = is_array($orders['fleets'] ?? null) ? $orders['fleets'] : [];

            // Movement is resolved before colonization. A fleet that moved this turn
            // cannot colonize until the following turn.
            foreach ($fleetOrders as $fleetOrder) {
                if (!is_array($fleetOrder) || ($fleetOrder['action'] ?? null) !== 'move') {
                    continue;
                }

                $fleetId = is_string($fleetOrder['fleetId'] ?? null) ? $fleetOrder['fleetId'] : '';
                $targetSystemId = is_string($fleetOrder['targetSystemId'] ?? null) ? $fleetOrder['targetSystemId'] : '';
                if ($fleetId === '' || $targetSystemId === '') {
                    $warnings[] = 'En fleet move-ordre manglede fleetId eller targetSystemId.';
                    continue;
                }

                $fleetIndex = $this->findFleetIndex($fleets, $fleetId);
                if ($fleetIndex === null) {
                    $warnings[] = sprintf('Flåden %s findes ikke.', $fleetId);
                    continue;
                }

                $fleet = $fleets[$fleetIndex];
                if ((string) ($fleet['ownerPlayerId'] ?? '') !== (string) $playerId) {
                    $warnings[] = sprintf('Flåden %s tilhører ikke spiller %s.', $fleetId, $playerId);
                    continue;
                }
                if (!isset($systemIds[$targetSystemId])) {
                    $warnings[] = sprintf('Destinationssystemet %s findes ikke.', $targetSystemId);
                    continue;
                }

                $fromSystemId = is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : '';
                if ($fromSystemId === $targetSystemId) {
                    continue;
                }
                $routeDistance = $this->systemDistance($routes, $fromSystemId, $targetSystemId, $movementRange);
                if ($routeDistance === null || $routeDistance > $movementRange) {
                    $warnings[] = sprintf(
                        'Flåden %s kan højst flytte %d hop denne runde; %s er uden for rækkevidde.',
                        $fleetId,
                        $movementRange,
                        $targetSystemId,
                    );
                    continue;
                }

                $fleets[$fleetIndex]['systemId'] = $targetSystemId;
                unset($fleets[$fleetIndex]['destinationSystemId']);
                $movedFleetIds[$fleetId] = true;
                $movements[] = [
                    'fleetId' => $fleetId,
                    'fromSystemId' => $fromSystemId,
                    'toSystemId' => $targetSystemId,
                ];
            }

            foreach ($fleetOrders as $fleetOrder) {
                if (!is_array($fleetOrder) || ($fleetOrder['action'] ?? null) !== 'colonize') {
                    continue;
                }

                $fleetId = is_string($fleetOrder['fleetId'] ?? null) ? $fleetOrder['fleetId'] : '';
                $targetSystemId = is_string($fleetOrder['targetSystemId'] ?? null) ? $fleetOrder['targetSystemId'] : '';
                if ($fleetId === '' || $targetSystemId === '') {
                    $warnings[] = 'En koloniseringsordre manglede fleetId eller targetSystemId.';
                    continue;
                }
                if (isset($movedFleetIds[$fleetId])) {
                    $warnings[] = sprintf('Flåden %s kan ikke både flytte og kolonisere i samme runde.', $fleetId);
                    continue;
                }

                $fleetIndex = $this->findFleetIndex($fleets, $fleetId);
                if ($fleetIndex === null) {
                    $warnings[] = sprintf('Flåden %s findes ikke.', $fleetId);
                    continue;
                }

                $fleet = $fleets[$fleetIndex];
                if ((string) ($fleet['ownerPlayerId'] ?? '') !== (string) $playerId) {
                    $warnings[] = sprintf('Flåden %s tilhører ikke spiller %s.', $fleetId, $playerId);
                    continue;
                }
                if (($fleet['systemId'] ?? null) !== $targetSystemId) {
                    $warnings[] = sprintf('Flåden %s befinder sig ikke i %s.', $fleetId, $targetSystemId);
                    continue;
                }

                $systemIndex = $systemIds[$targetSystemId] ?? null;
                if (!is_int($systemIndex) || !isset($systems[$systemIndex]) || !is_array($systems[$systemIndex])) {
                    $warnings[] = sprintf('Koloniseringssystemet %s findes ikke.', $targetSystemId);
                    continue;
                }
                if (($systems[$systemIndex]['ownerPlayerId'] ?? null) !== null) {
                    $warnings[] = sprintf('Systemet %s er allerede koloniseret.', $targetSystemId);
                    continue;
                }

                $capacity = $this->colonyCapacity($fleet);
                if ($capacity < 1) {
                    $warnings[] = sprintf('Flåden %s har intet ubrugt colony module.', $fleetId);
                    continue;
                }

                $systems[$systemIndex]['ownerPlayerId'] = (int) $playerId;
                $systems[$systemIndex]['population'] = max(0.25, (float) ($systems[$systemIndex]['population'] ?? 0.0));
                $systems[$systemIndex]['happiness'] = 70;
                $systems[$systemIndex]['security'] = 15;
                $systems[$systemIndex]['development'] = 8;
                $systems[$systemIndex]['defenses'] = max(0, (int) ($systems[$systemIndex]['defenses'] ?? 0));
                $systems[$systemIndex]['description'] = sprintf(
                    'New colony established in year %d by player %s.',
                    (int) $nextState['year'],
                    $playerId,
                );
                $systems[$systemIndex]['isCapital'] = false;
                $systems[$systemIndex]['sensorRange'] = 1;

                $fleets[$fleetIndex]['colonizationCapacity'] = $capacity - 1;
                $colonizations[] = [
                    'fleetId' => $fleetId,
                    'systemId' => $targetSystemId,
                    'population' => $systems[$systemIndex]['population'],
                ];
            }

            $productionOrders = is_array($orders['production'] ?? null) ? $orders['production'] : [];
            $buildSequence = 0;
            foreach ($productionOrders as $productionOrder) {
                if (!is_array($productionOrder)) {
                    continue;
                }

                $systemId = is_string($productionOrder['systemId'] ?? null) ? $productionOrder['systemId'] : '';
                $item = is_string($productionOrder['item'] ?? null) ? trim($productionOrder['item']) : '';
                $quantity = max(1, min(10, (int) ($productionOrder['quantity'] ?? 1)));
                $definition = $this->productionDefinition($item);

                if ($systemId === '' || $item === '' || $definition === null) {
                    $warnings[] = sprintf('Ukendt eller ugyldig produktionsordre: %s.', $item !== '' ? $item : 'tom ordre');
                    continue;
                }
                if (($startingOwners[$systemId] ?? null) !== (int) $playerId) {
                    $warnings[] = sprintf('Produktion i %s kræver, at systemet var din koloni ved rundens start.', $systemId);
                    continue;
                }

                $systemIndex = $systemIds[$systemId] ?? null;
                if (!is_int($systemIndex) || !isset($systems[$systemIndex]) || !is_array($systems[$systemIndex])) {
                    $warnings[] = sprintf('Produktionssystemet %s findes ikke.', $systemId);
                    continue;
                }

                for ($unit = 0; $unit < $quantity; ++$unit) {
                    $industryIndex = $this->findResourceIndex($systems[$systemIndex], 'industry');
                    if ($industryIndex === null) {
                        $warnings[] = sprintf('%s har ingen industry-ressource.', $systemId);
                        break;
                    }

                    $available = (int) ($systems[$systemIndex]['resources'][$industryIndex]['value'] ?? 0);
                    if ($available < $definition['cost']) {
                        $warnings[] = sprintf(
                            '%s mangler industry til %s: %d tilgængelig, %d kræves.',
                            (string) ($systems[$systemIndex]['name'] ?? $systemId),
                            $item,
                            $available,
                            $definition['cost'],
                        );
                        break;
                    }

                    $systems[$systemIndex]['resources'][$industryIndex]['value'] = $available - $definition['cost'];
                    ++$buildSequence;

                    if ($item === 'Scout Wing') {
                        $existingScoutCount = $this->countScoutFleets($fleets, (int) $playerId, $systemId);
                        $scoutNumber = $existingScoutCount + 1;
                        $fleets[] = [
                            'id' => sprintf('fleet-%s-built-%d-%d', $playerId, $turn->getNumber() + 1, $buildSequence),
                            'ownerPlayerId' => (int) $playerId,
                            'systemId' => $systemId,
                            'name' => sprintf('%s Scout Wing %d', (string) ($systems[$systemIndex]['name'] ?? 'Colony'), $scoutNumber),
                            'ships' => 40,
                            'role' => 'Scout fleet',
                            'colonizationCapacity' => 0,
                        ];
                    } elseif ($item === 'Defense Grid') {
                        $defenseGridAmount = max(250, (int) ($playerResearch['modifiers']['defenseGridAmount'] ?? 250));
                        $systems[$systemIndex]['defenses'] = max(0, (int) ($systems[$systemIndex]['defenses'] ?? 0)) + $defenseGridAmount;
                    } elseif ($item === 'Orbital Factory') {
                        $systems[$systemIndex]['development'] = min(100, max(0, (int) ($systems[$systemIndex]['development'] ?? 0)) + 10);
                        $systems[$systemIndex]['resources'][$industryIndex]['income'] = max(
                            0,
                            (int) ($systems[$systemIndex]['resources'][$industryIndex]['income'] ?? 0),
                        ) + 8;
                    } elseif ($item === 'Deep Space Array') {
                        $currentRange = max(1, (int) ($systems[$systemIndex]['sensorRange'] ?? 1));
                        if ($currentRange >= 3) {
                            // Refund if a duplicate/technical order attempts to exceed the cap.
                            $systems[$systemIndex]['resources'][$industryIndex]['value'] += $definition['cost'];
                            $warnings[] = sprintf('%s har allerede maksimal sensor-rækkevidde.', (string) ($systems[$systemIndex]['name'] ?? $systemId));
                            break;
                        }
                        $systems[$systemIndex]['sensorRange'] = min(3, $currentRange + 1);
                    }

                    $productions[] = [
                        'systemId' => $systemId,
                        'item' => $item,
                        'industryCost' => $definition['cost'],
                    ];
                }
            }

            // Research is persistent game state. Points generated by the player's
            // colonies are added every turn and spent on one active technology.
            // A new valid order changes the active technology; otherwise the previous
            // selection continues automatically.
            $playerResearch['income'] = $researchIncome;
            $playerResearch['stockpile'] = max(0, (int) ($playerResearch['stockpile'] ?? 0)) + $researchIncome;

            $researchOrders = is_array($orders['research'] ?? null) ? array_values($orders['research']) : [];
            if ($researchOrders !== []) {
                $selection = is_array($researchOrders[0]) ? $researchOrders[0] : [];
                $technologyId = is_string($selection['technologyId'] ?? null)
                    ? trim($selection['technologyId'])
                    : (is_string($selection['field'] ?? null) ? trim($selection['field']) : '');

                if ($technologyId === '' || !ResearchCatalog::canResearch($playerResearch, $technologyId)) {
                    $warnings[] = sprintf('Forskningsvalget %s er ugyldigt eller låst.', $technologyId !== '' ? $technologyId : 'tomt valg');
                } else {
                    $playerResearch['activeTechnologyId'] = $technologyId;
                }
            }

            $activeTechnologyId = is_string($playerResearch['activeTechnologyId'] ?? null)
                ? $playerResearch['activeTechnologyId']
                : null;
            if ($activeTechnologyId !== null && ResearchCatalog::canResearch($playerResearch, $activeTechnologyId)) {
                $definition = ResearchCatalog::technology($activeTechnologyId);
                if ($definition !== null) {
                    $cost = max(1, (int) $definition['cost']);
                    $progressBefore = max(0, (int) ($playerResearch['progress'][$activeTechnologyId] ?? 0));
                    $needed = max(0, $cost - $progressBefore);
                    $spent = min(max(0, (int) $playerResearch['stockpile']), $needed);
                    $progressAfter = min($cost, $progressBefore + $spent);
                    $playerResearch['stockpile'] -= $spent;
                    $playerResearch['progress'][$activeTechnologyId] = $progressAfter;

                    if ($progressAfter >= $cost) {
                        $completed = is_array($playerResearch['completed'] ?? null) ? $playerResearch['completed'] : [];
                        if (!in_array($activeTechnologyId, $completed, true)) {
                            $completed[] = $activeTechnologyId;
                        }
                        $playerResearch['completed'] = array_values($completed);
                        $playerResearch['activeTechnologyId'] = null;
                        $researchCompleted[] = [
                            'technologyId' => $activeTechnologyId,
                            'name' => (string) $definition['name'],
                            'field' => (string) $definition['field'],
                            'tier' => (int) $definition['tier'],
                            'cost' => $cost,
                            'effect' => (string) $definition['effect'],
                        ];
                    } else {
                        $researchProgress = [
                            'technologyId' => $activeTechnologyId,
                            'name' => (string) $definition['name'],
                            'field' => (string) $definition['field'],
                            'progress' => $progressAfter,
                            'cost' => $cost,
                            'spent' => $spent,
                            'income' => $researchIncome,
                        ];
                    }
                }
            }

            // Re-normalize to recalculate levels/modifiers after a completion.
            $playerResearch = ResearchCatalog::normalizeState($playerResearch);
            $playerResearch['income'] = $researchIncome;
            $researchByPlayer[(string) $playerId] = $playerResearch;

            $parts = [];
            if (count($movements) > 0) {
                $parts[] = sprintf('%d flådebevægelse(r)', count($movements));
            }
            if (count($colonizations) > 0) {
                $parts[] = sprintf('%d kolonisering(er)', count($colonizations));
            }
            if (count($productions) > 0) {
                $parts[] = sprintf('%d produktion(er)', count($productions));
            }
            if (count($researchCompleted) > 0) {
                $parts[] = sprintf('%d forskningsteknologi(er)', count($researchCompleted));
            }

            $reports[$playerId] = [
                'message' => $parts === []
                    ? 'Ingen flådebevægelser, koloniseringer, produktioner eller forskningsteknologier blev afsluttet i denne runde.'
                    : ucfirst(implode(', ', $parts)).' blev udført.',
                'movements' => $movements,
                'colonizations' => $colonizations,
                'productions' => $productions,
                'research_completed' => $researchCompleted,
                'research_progress' => $researchProgress,
                'research_income' => $researchIncome,
                'research_stockpile' => (int) ($playerResearch['stockpile'] ?? 0),
                'warnings' => $warnings,
                'orders' => $orders,
            ];
        }

        $nextState['universe']['systems'] = array_values($systems);
        $nextState['universe']['fleets'] = array_values($fleets);
        $nextState['research'] = $researchByPlayer;

        return new TurnGenerationResult($nextState, $reports);
    }

    /** @param list<mixed> $fleets */
    private function countScoutFleets(array $fleets, int $playerId, string $systemId): int
    {
        return count(array_filter(
            $fleets,
            static fn (mixed $fleet): bool => is_array($fleet)
                && (int) ($fleet['ownerPlayerId'] ?? 0) === $playerId
                && ($fleet['systemId'] ?? null) === $systemId
                && in_array(($fleet['role'] ?? null), ['Scout fleet', 'Exploration fleet'], true),
        ));
    }

    /** @param list<mixed> $fleets */
    private function findFleetIndex(array $fleets, string $fleetId): ?int
    {
        foreach ($fleets as $index => $fleet) {
            if (is_array($fleet) && ($fleet['id'] ?? null) === $fleetId) {
                return $index;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $system */
    private function findResourceIndex(array $system, string $resourceId): ?int
    {
        $resources = is_array($system['resources'] ?? null) ? $system['resources'] : [];
        foreach ($resources as $index => $resource) {
            if (is_array($resource) && ($resource['id'] ?? null) === $resourceId) {
                return $index;
            }
        }

        return null;
    }

    /** @return array{cost:int}|null */
    private function productionDefinition(string $item): ?array
    {
        return match ($item) {
            'Scout Wing' => ['cost' => 300],
            'Defense Grid' => ['cost' => 250],
            'Orbital Factory' => ['cost' => 400],
            'Deep Space Array' => ['cost' => 350],
            default => null,
        };
    }

    /** @param array<string, mixed> $fleet */
    private function colonyCapacity(array $fleet): int
    {
        if (isset($fleet['colonizationCapacity']) && is_numeric($fleet['colonizationCapacity'])) {
            return max(0, (int) $fleet['colonizationCapacity']);
        }

        // Backwards compatibility for 0.5.1/0.5.3 test games.
        return ($fleet['role'] ?? null) === 'Exploration fleet' ? 1 : 0;
    }

    /** @param list<mixed> $routes */
    private function systemDistance(array $routes, string $a, string $b, int $maxDistance): ?int
    {
        if ($a === '' || $b === '') {
            return null;
        }
        if ($a === $b) {
            return 0;
        }

        $adjacency = [];
        foreach ($routes as $route) {
            if (!is_array($route)) {
                continue;
            }
            $from = is_string($route['from'] ?? null) ? $route['from'] : '';
            $to = is_string($route['to'] ?? null) ? $route['to'] : '';
            if ($from === '' || $to === '') {
                continue;
            }
            $adjacency[$from][] = $to;
            $adjacency[$to][] = $from;
        }

        $visited = [$a => true];
        $frontier = [$a];
        for ($distance = 1; $distance <= max(1, $maxDistance); ++$distance) {
            $next = [];
            foreach ($frontier as $systemId) {
                foreach ($adjacency[$systemId] ?? [] as $neighbourId) {
                    if (isset($visited[$neighbourId])) {
                        continue;
                    }
                    if ($neighbourId === $b) {
                        return $distance;
                    }
                    $visited[$neighbourId] = true;
                    $next[] = $neighbourId;
                }
            }
            if ($next === []) {
                break;
            }
            $frontier = $next;
        }

        return null;
    }
}
