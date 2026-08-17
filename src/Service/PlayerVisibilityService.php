<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

final readonly class PlayerVisibilityService
{
    /**
     * @param array<string, mixed> $state
     * @return array{state: array<string, mixed>, sensorSystemIds: list<string>, visibleEnemyFleetCount: int}
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
        $markSystem = static function (string $systemId, bool $includeNeighbours = false) use (&$sensorSystemIds, $adjacency): void {
            if ($systemId === '') {
                return;
            }
            $sensorSystemIds[$systemId] = true;
            if (!$includeNeighbours) {
                return;
            }
            foreach ($adjacency[$systemId] ?? [] as $neighbourId) {
                $sensorSystemIds[$neighbourId] = true;
            }
        };

        // Colonies provide one-hop sensor coverage.
        foreach ($systems as $system) {
            if (!is_array($system) || (int) ($system['ownerPlayerId'] ?? 0) !== $playerId) {
                continue;
            }
            $markSystem(is_string($system['id'] ?? null) ? $system['id'] : '', true);
        }

        // All own fleets reveal their current system. Scouts and exploration fleets
        // additionally reveal directly connected systems.
        foreach ($fleets as $fleet) {
            if (!is_array($fleet) || (int) ($fleet['ownerPlayerId'] ?? 0) !== $playerId) {
                continue;
            }
            $systemId = is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : '';
            $role = is_string($fleet['role'] ?? null) ? $fleet['role'] : '';
            $isScout = in_array($role, ['Scout fleet', 'Exploration fleet'], true);
            $markSystem($systemId, $isScout);
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

        return [
            'state' => $state,
            'sensorSystemIds' => array_values($sensorIds),
            'visibleEnemyFleetCount' => $visibleEnemyFleetCount,
        ];
    }
}
