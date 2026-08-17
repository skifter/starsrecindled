<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

final readonly class PlayerVisibilityService
{
    /**
     * @param array<string, mixed> $state
     * @return array{
     *   state: array<string, mixed>,
     *   sensorSystemIds: list<string>,
     *   visibleEnemyFleetCount: int,
     *   colonySensorRanges: array<string, int>
     * }
     */
    public function project(array $state, int $playerId): array
    {
        $universe = is_array($state['universe'] ?? null) ? $state['universe'] : [];
        $systems = is_array($universe['systems'] ?? null) ? $universe['systems'] : [];
        $routes = is_array($universe['routes'] ?? null) ? $universe['routes'] : [];
        $fleets = is_array($universe['fleets'] ?? null) ? $universe['fleets'] : [];

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

        /** @var array<string, true> $sensorSystemIds */
        $sensorSystemIds = [];
        /** @var array<string, int> $colonySensorRanges */
        $colonySensorRanges = [];

        $markWithinRange = static function (string $origin, int $range) use (&$sensorSystemIds, $adjacency): void {
            if ($origin === '') {
                return;
            }

            $range = max(0, $range);
            $visited = [$origin => true];
            $frontier = [$origin];
            $sensorSystemIds[$origin] = true;

            for ($depth = 0; $depth < $range; ++$depth) {
                $next = [];
                foreach ($frontier as $systemId) {
                    foreach ($adjacency[$systemId] ?? [] as $neighbourId) {
                        if (isset($visited[$neighbourId])) {
                            continue;
                        }
                        $visited[$neighbourId] = true;
                        $sensorSystemIds[$neighbourId] = true;
                        $next[] = $neighbourId;
                    }
                }
                if ($next === []) {
                    break;
                }
                $frontier = $next;
            }
        };

        // Every owned colony has a one-hop sensor by default. A Deep Space Array
        // increases that colony's graph distance one step at a time, up to 3 hops.
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

        // All own fleets reveal their current system. Scout and exploration fleets
        // also reveal all directly connected systems (one hop).
        foreach ($fleets as $fleet) {
            if (!is_array($fleet) || (int) ($fleet['ownerPlayerId'] ?? 0) !== $playerId) {
                continue;
            }
            $systemId = is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : '';
            $role = is_string($fleet['role'] ?? null) ? $fleet['role'] : '';
            $isScout = in_array($role, ['Scout fleet', 'Exploration fleet'], true);
            $markWithinRange($systemId, $isScout ? 1 : 0);
        }

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

            if ($systemId !== '' && isset($sensorSystemIds[$systemId])) {
                $visibleFleets[] = $fleet;
                ++$visibleEnemyFleetCount;
            }
        }

        $state['universe'] = $universe;
        $state['universe']['fleets'] = array_values($visibleFleets);

        $sensorIds = array_keys($sensorSystemIds);
        sort($sensorIds, SORT_STRING);
        ksort($colonySensorRanges, SORT_STRING);

        return [
            'state' => $state,
            'sensorSystemIds' => array_values($sensorIds),
            'visibleEnemyFleetCount' => $visibleEnemyFleetCount,
            'colonySensorRanges' => $colonySensorRanges,
        ];
    }
}
