<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

final class ResearchCatalog
{
    /** @var array<string, array<string, mixed>> */
    private const TECHNOLOGIES = [
        'propulsion_1' => [
            'id' => 'propulsion_1',
            'field' => 'propulsion',
            'tier' => 1,
            'name' => 'Advanced Drives',
            'cost' => 360,
            'prerequisites' => [],
            'effect' => 'Fleet movement range increases from 1 to 2 hops per turn.',
        ],
        'propulsion_2' => [
            'id' => 'propulsion_2',
            'field' => 'propulsion',
            'tier' => 2,
            'name' => 'Slipstream Navigation',
            'cost' => 760,
            'prerequisites' => ['propulsion_1'],
            'effect' => 'Fleet movement range increases to 3 hops per turn.',
        ],
        'sensors_1' => [
            'id' => 'sensors_1',
            'field' => 'sensors',
            'tier' => 1,
            'name' => 'Long-Range Telemetry',
            'cost' => 320,
            'prerequisites' => [],
            'effect' => 'All colony sensor coverage gains +1 hop.',
        ],
        'sensors_2' => [
            'id' => 'sensors_2',
            'field' => 'sensors',
            'tier' => 2,
            'name' => 'Deep-Space Signal Analysis',
            'cost' => 720,
            'prerequisites' => ['sensors_1'],
            'effect' => 'All colony sensor coverage gains another +1 hop.',
        ],
        'weapons_1' => [
            'id' => 'weapons_1',
            'field' => 'weapons',
            'tier' => 1,
            'name' => 'Coherent Beam Theory',
            'cost' => 340,
            'prerequisites' => [],
            'effect' => 'Fleet attack modifier +15% for the upcoming combat system.',
        ],
        'weapons_2' => [
            'id' => 'weapons_2',
            'field' => 'weapons',
            'tier' => 2,
            'name' => 'Phase-Locked Emitters',
            'cost' => 780,
            'prerequisites' => ['weapons_1'],
            'effect' => 'Fleet attack modifier increases to +35%.',
        ],
        'defenses_1' => [
            'id' => 'defenses_1',
            'field' => 'defenses',
            'tier' => 1,
            'name' => 'Layered Defense Doctrine',
            'cost' => 340,
            'prerequisites' => [],
            'effect' => 'Defense Grid builds +350 defenses instead of +250; combat defense +15%.',
        ],
        'defenses_2' => [
            'id' => 'defenses_2',
            'field' => 'defenses',
            'tier' => 2,
            'name' => 'Planetary Shield Networks',
            'cost' => 780,
            'prerequisites' => ['defenses_1'],
            'effect' => 'Defense Grid builds +500 defenses; combat defense modifier increases to +35%.',
        ],
        'industry_1' => [
            'id' => 'industry_1',
            'field' => 'industry',
            'tier' => 1,
            'name' => 'Automated Fabrication',
            'cost' => 360,
            'prerequisites' => [],
            'effect' => 'Industry income credited each turn increases by 12%.',
        ],
        'industry_2' => [
            'id' => 'industry_2',
            'field' => 'industry',
            'tier' => 2,
            'name' => 'Autonomous Orbital Industry',
            'cost' => 800,
            'prerequisites' => ['industry_1'],
            'effect' => 'Industry income bonus increases to 27%.',
        ],
    ];

    /** @return list<array<string, mixed>> */
    public static function publicCatalog(): array
    {
        return array_values(self::TECHNOLOGIES);
    }

    /** @return array<string, mixed>|null */
    public static function technology(string $id): ?array
    {
        return self::TECHNOLOGIES[$id] ?? null;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public static function playerState(array $state, int $playerId): array
    {
        $research = is_array($state['research'] ?? null) ? $state['research'] : [];
        $raw = $research[(string) $playerId] ?? $research[$playerId] ?? [];

        return self::normalizeState(is_array($raw) ? $raw : []);
    }

    /** @param array<string, mixed> $raw @return array<string, mixed> */
    public static function normalizeState(array $raw): array
    {
        $completed = array_values(array_unique(array_filter(
            is_array($raw['completed'] ?? null) ? $raw['completed'] : [],
            static fn (mixed $id): bool => is_string($id) && isset(self::TECHNOLOGIES[$id]),
        )));
        $progress = [];
        foreach (is_array($raw['progress'] ?? null) ? $raw['progress'] : [] as $id => $value) {
            if (is_string($id) && isset(self::TECHNOLOGIES[$id]) && is_numeric($value)) {
                $progress[$id] = max(0, min((int) self::TECHNOLOGIES[$id]['cost'], (int) $value));
            }
        }

        $active = is_string($raw['activeTechnologyId'] ?? null) ? $raw['activeTechnologyId'] : null;
        if ($active !== null && (!isset(self::TECHNOLOGIES[$active]) || in_array($active, $completed, true))) {
            $active = null;
        }

        return [
            'stockpile' => max(0, (int) ($raw['stockpile'] ?? 0)),
            'income' => max(0, (int) ($raw['income'] ?? 0)),
            'activeTechnologyId' => $active,
            'progress' => $progress,
            'completed' => $completed,
            'levels' => self::levels($completed),
            'modifiers' => self::modifiers($completed),
        ];
    }

    /** @param array<string, mixed> $playerState */
    public static function canResearch(array $playerState, string $technologyId): bool
    {
        $definition = self::technology($technologyId);
        if ($definition === null) {
            return false;
        }

        $state = self::normalizeState($playerState);
        if (in_array($technologyId, $state['completed'], true)) {
            return false;
        }

        foreach ($definition['prerequisites'] as $prerequisite) {
            if (!in_array($prerequisite, $state['completed'], true)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $state */
    public static function estimateIncome(array $state, int $playerId): int
    {
        $systems = is_array($state['universe']['systems'] ?? null) ? $state['universe']['systems'] : [];
        $income = 0;

        foreach ($systems as $system) {
            if (!is_array($system) || (int) ($system['ownerPlayerId'] ?? 0) !== $playerId) {
                continue;
            }

            $scienceIncome = 0;
            foreach (is_array($system['resources'] ?? null) ? $system['resources'] : [] as $resource) {
                if (is_array($resource) && ($resource['id'] ?? null) === 'science') {
                    $scienceIncome = max(0, (int) ($resource['income'] ?? 0));
                    break;
                }
            }

            $development = max(0, min(100, (int) ($system['development'] ?? 0)));
            $population = max(0.0, (float) ($system['population'] ?? 0.0));
            // Older test games may not contain a dedicated science resource yet.
            // Give those colonies a deterministic development/population fallback
            // so research is immediately playable after upgrading to 0.7.0.
            if ($scienceIncome === 0) {
                $scienceIncome = 20 + intdiv($development, 2) + (int) floor($population * 4);
            }
            $capitalBonus = ($system['isCapital'] ?? false) === true ? 12 : 0;
            $income += $scienceIncome + intdiv($development, 8) + $capitalBonus;
        }

        return max(0, $income);
    }

    /** @param list<string> $completed @return array<string, int> */
    private static function levels(array $completed): array
    {
        $levels = [
            'propulsion' => 0,
            'sensors' => 0,
            'weapons' => 0,
            'defenses' => 0,
            'industry' => 0,
        ];

        foreach ($completed as $id) {
            $definition = self::TECHNOLOGIES[$id] ?? null;
            if ($definition === null) {
                continue;
            }
            $field = (string) $definition['field'];
            $levels[$field] = max($levels[$field] ?? 0, (int) $definition['tier']);
        }

        return $levels;
    }

    /** @param list<string> $completed @return array<string, int> */
    private static function modifiers(array $completed): array
    {
        $levels = self::levels($completed);
        $defenseLevel = $levels['defenses'];
        $industryLevel = $levels['industry'];
        $weaponsLevel = $levels['weapons'];

        return [
            'fleetMovementRange' => 1 + $levels['propulsion'],
            'colonySensorBonus' => $levels['sensors'],
            'fleetAttackPercent' => $weaponsLevel >= 2 ? 35 : ($weaponsLevel >= 1 ? 15 : 0),
            'planetDefensePercent' => $defenseLevel >= 2 ? 35 : ($defenseLevel >= 1 ? 15 : 0),
            'defenseGridAmount' => $defenseLevel >= 2 ? 500 : ($defenseLevel >= 1 ? 350 : 250),
            'industryIncomePercent' => $industryLevel >= 2 ? 27 : ($industryLevel >= 1 ? 12 : 0),
        ];
    }
}
