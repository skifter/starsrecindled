<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

final readonly class PlayerVisibilityService
{
    /**
     * @param array<string, mixed> $state
     * @param list<array{turnNumber:int,state:array<string,mixed>}> $history
     * @return array{
     *   state: array<string, mixed>,
     *   sensorSystemIds: list<string>,
     *   visibleEnemyFleetCount: int,
     *   colonySensorRanges: array<string, int>,
     *   systemVisibility: array<string, array{state:string,last_seen_turn:int}>,
     *   knownSystemIds: list<string>,
     *   unknownSystemCount: int
     * }
     */
    public function project(array $state, int $playerId, array $history = [], int $currentTurnNumber = 0): array
    {
        $currentTurnNumber = max(1, $currentTurnNumber);

        /** @var array<string, array<string, mixed>> $memory */
        $memory = [];
        foreach ($history as $entry) {
            $turnNumber = max(1, (int) ($entry['turnNumber'] ?? 1));
            $historicalState = is_array($entry['state'] ?? null) ? $entry['state'] : [];
            if ($historicalState === []) {
                continue;
            }

            $coverage = $this->sensorCoverage($historicalState, $playerId);
            foreach ($this->systems($historicalState) as $system) {
                $systemId = is_string($system['id'] ?? null) ? $system['id'] : '';
                if ($systemId === '' || !isset($coverage['sensorMap'][$systemId])) {
                    continue;
                }
                $memory[$systemId] = $this->systemSnapshot($system, $turnNumber);
            }
        }

        $coverage = $this->sensorCoverage($state, $playerId);
        $systems = $this->systems($state);
        $routes = $this->routes($state);
        $fleets = $this->fleets($state);

        foreach ($systems as $system) {
            $systemId = is_string($system['id'] ?? null) ? $system['id'] : '';
            if ($systemId === '' || !isset($coverage['sensorMap'][$systemId])) {
                continue;
            }
            $memory[$systemId] = $this->systemSnapshot($system, $currentTurnNumber);
        }

        /** @var list<array<string, mixed>> $projectedSystems */
        $projectedSystems = [];
        /** @var array<string, true> $knownMap */
        $knownMap = [];
        /** @var array<string, array{state:string,last_seen_turn:int}> $systemVisibility */
        $systemVisibility = [];

        foreach ($systems as $system) {
            $systemId = is_string($system['id'] ?? null) ? $system['id'] : '';
            if ($systemId === '') {
                continue;
            }

            if (isset($coverage['sensorMap'][$systemId])) {
                $visible = $system;
                $visible['visibilityState'] = 'visible';
                $visible['lastSeenTurn'] = $currentTurnNumber;
                $projectedSystems[] = $visible;
                $knownMap[$systemId] = true;
                $systemVisibility[$systemId] = [
                    'state' => 'visible',
                    'last_seen_turn' => $currentTurnNumber,
                ];
                continue;
            }

            if (!isset($memory[$systemId])) {
                continue;
            }

            $remembered = $memory[$systemId];
            $remembered['visibilityState'] = 'explored';
            $lastSeenTurn = max(1, (int) ($remembered['lastSeenTurn'] ?? 1));
            $remembered['lastSeenTurn'] = $lastSeenTurn;
            $projectedSystems[] = $remembered;
            $knownMap[$systemId] = true;
            $systemVisibility[$systemId] = [
                'state' => 'explored',
                'last_seen_turn' => $lastSeenTurn,
            ];
        }

        $projectedRoutes = array_values(array_filter(
            $routes,
            static function (mixed $route) use ($knownMap): bool {
                if (!is_array($route)) {
                    return false;
                }
                $from = is_string($route['from'] ?? null) ? $route['from'] : '';
                $to = is_string($route['to'] ?? null) ? $route['to'] : '';

                return $from !== '' && $to !== '' && isset($knownMap[$from], $knownMap[$to]);
            },
        ));

        $visibleFleets = [];
        $visibleEnemyFleetCount = 0;
        foreach ($fleets as $fleet) {
            if (!is_array($fleet)) {
                continue;
            }
            $ownerPlayerId = (int) ($fleet['ownerPlayerId'] ?? 0);
            $systemId = is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : '';

            if ($ownerPlayerId === $playerId) {
                $visibleFleets[] = $fleet;
                continue;
            }

            if ($systemId !== '' && isset($coverage['sensorMap'][$systemId])) {
                $visibleFleets[] = $fleet;
                ++$visibleEnemyFleetCount;
            }
        }

        $universe = is_array($state['universe'] ?? null) ? $state['universe'] : [];
        $universe['systems'] = array_values($projectedSystems);
        $universe['routes'] = $projectedRoutes;
        $universe['fleets'] = array_values($visibleFleets);
        $state['universe'] = $universe;

        // Never expose server-side intelligence/history data if such fields are added later.
        unset($state['intelligence']);

        $sensorIds = array_keys($coverage['sensorMap']);
        sort($sensorIds, SORT_STRING);
        $knownSystemIds = array_keys($knownMap);
        sort($knownSystemIds, SORT_STRING);
        ksort($systemVisibility, SORT_STRING);
        ksort($coverage['colonySensorRanges'], SORT_STRING);

        return [
            'state' => $state,
            'sensorSystemIds' => array_values($sensorIds),
            'visibleEnemyFleetCount' => $visibleEnemyFleetCount,
            'colonySensorRanges' => $coverage['colonySensorRanges'],
            'systemVisibility' => $systemVisibility,
            'knownSystemIds' => array_values($knownSystemIds),
            'unknownSystemCount' => max(0, count($systems) - count($knownMap)),
        ];
    }

    /**
     * @param array<string, mixed> $beforeState
     * @param array<string, mixed> $afterState
     * @return list<array<string, mixed>>
     */
    public function contactEvents(array $beforeState, array $afterState, int $playerId): array
    {
        $before = $this->visibleEnemyFleetMap($beforeState, $playerId);
        $after = $this->visibleEnemyFleetMap($afterState, $playerId);
        $events = [];

        foreach ($after as $fleetId => $fleet) {
            if (isset($before[$fleetId])) {
                continue;
            }
            $events[] = [
                'type' => 'detected',
                'fleetId' => $fleetId,
                'fleetName' => is_string($fleet['name'] ?? null) ? $fleet['name'] : $fleetId,
                'systemId' => is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : null,
                'ownerPlayerId' => (int) ($fleet['ownerPlayerId'] ?? 0),
                'ships' => max(0, (int) ($fleet['ships'] ?? 0)),
            ];
        }

        foreach ($before as $fleetId => $fleet) {
            if (isset($after[$fleetId])) {
                continue;
            }
            $events[] = [
                'type' => 'lost',
                'fleetId' => $fleetId,
                'fleetName' => is_string($fleet['name'] ?? null) ? $fleet['name'] : $fleetId,
                'systemId' => is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : null,
                'ownerPlayerId' => (int) ($fleet['ownerPlayerId'] ?? 0),
                'ships' => max(0, (int) ($fleet['ships'] ?? 0)),
            ];
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $state
     * @return array{sensorMap:array<string,true>,colonySensorRanges:array<string,int>}
     */
    private function sensorCoverage(array $state, int $playerId): array
    {
        $routes = $this->routes($state);
        $systems = $this->systems($state);
        $fleets = $this->fleets($state);

        /** @var array<string, list<string>> $adjacency */
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
            $adjacency[$from] ??= [];
            $adjacency[$to] ??= [];
            $adjacency[$from][] = $to;
            $adjacency[$to][] = $from;
        }

        /** @var array<string, true> $sensorMap */
        $sensorMap = [];
        /** @var array<string, int> $colonySensorRanges */
        $colonySensorRanges = [];

        $markWithinRange = static function (string $origin, int $range) use (&$sensorMap, $adjacency): void {
            if ($origin === '') {
                return;
            }

            $range = max(0, $range);
            $visited = [$origin => true];
            $frontier = [$origin];
            $sensorMap[$origin] = true;

            for ($depth = 0; $depth < $range; ++$depth) {
                $next = [];
                foreach ($frontier as $systemId) {
                    foreach ($adjacency[$systemId] ?? [] as $neighbourId) {
                        if (isset($visited[$neighbourId])) {
                            continue;
                        }
                        $visited[$neighbourId] = true;
                        $sensorMap[$neighbourId] = true;
                        $next[] = $neighbourId;
                    }
                }
                if ($next === []) {
                    break;
                }
                $frontier = $next;
            }
        };

        foreach ($systems as $system) {
            if (!is_array($system) || (int) ($system['ownerPlayerId'] ?? 0) !== $playerId) {
                continue;
            }
            $systemId = is_string($system['id'] ?? null) ? $system['id'] : '';
            if ($systemId === '') {
                continue;
            }
            $range = max(1, min(3, (int) ($system['sensorRange'] ?? 1)));
            $colonySensorRanges[$systemId] = $range;
            $markWithinRange($systemId, $range);
        }

        foreach ($fleets as $fleet) {
            if (!is_array($fleet) || (int) ($fleet['ownerPlayerId'] ?? 0) !== $playerId) {
                continue;
            }
            $systemId = is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : '';
            $role = is_string($fleet['role'] ?? null) ? $fleet['role'] : '';
            $isScout = in_array($role, ['Scout fleet', 'Exploration fleet'], true);
            $markWithinRange($systemId, $isScout ? 1 : 0);
        }

        return [
            'sensorMap' => $sensorMap,
            'colonySensorRanges' => $colonySensorRanges,
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, array<string, mixed>>
     */
    private function visibleEnemyFleetMap(array $state, int $playerId): array
    {
        $coverage = $this->sensorCoverage($state, $playerId);
        $visible = [];

        foreach ($this->fleets($state) as $fleet) {
            if (!is_array($fleet)) {
                continue;
            }
            $ownerPlayerId = (int) ($fleet['ownerPlayerId'] ?? 0);
            $systemId = is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : '';
            $fleetId = is_string($fleet['id'] ?? null) ? $fleet['id'] : '';
            if ($fleetId === '' || $ownerPlayerId === $playerId || $systemId === '' || !isset($coverage['sensorMap'][$systemId])) {
                continue;
            }
            $visible[$fleetId] = $fleet;
        }

        return $visible;
    }

    /** @param array<string, mixed> $system @return array<string, mixed> */
    private function systemSnapshot(array $system, int $turnNumber): array
    {
        // Fleets are deliberately not retained as stale contacts. A lost fleet contact
        // disappears, while ownership/system information remains as last-known intel.
        unset($system['fleets']);
        $system['lastSeenTurn'] = max(1, $turnNumber);

        return $system;
    }

    /** @param array<string, mixed> $state @return list<mixed> */
    private function systems(array $state): array
    {
        $universe = is_array($state['universe'] ?? null) ? $state['universe'] : [];

        return is_array($universe['systems'] ?? null) ? array_values($universe['systems']) : [];
    }

    /** @param array<string, mixed> $state @return list<mixed> */
    private function routes(array $state): array
    {
        $universe = is_array($state['universe'] ?? null) ? $state['universe'] : [];

        return is_array($universe['routes'] ?? null) ? array_values($universe['routes']) : [];
    }

    /** @param array<string, mixed> $state @return list<mixed> */
    private function fleets(array $state): array
    {
        $universe = is_array($state['universe'] ?? null) ? $state['universe'] : [];

        return is_array($universe['fleets'] ?? null) ? array_values($universe['fleets']) : [];
    }
}
