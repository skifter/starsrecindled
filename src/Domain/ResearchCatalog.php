<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

final class ResearchCatalog
{
    /** @var array<string, array<string, mixed>> */
    private const TECHNOLOGIES = [
        'propulsion_1' => [
            'id' => 'propulsion_1', 'field' => 'propulsion', 'tier' => 1,
            'name' => 'Ion Propulsion', 'cost' => 360, 'prerequisites' => [],
            'kind' => 'hardware', 'unlocks' => ['ion_drive_mk1'], 'globalEffects' => [],
            'effect' => 'Unlocks Ion Drive Mk II for new ship designs. Existing engines are unchanged.',
        ],
        'propulsion_2' => [
            'id' => 'propulsion_2', 'field' => 'propulsion', 'tier' => 2,
            'name' => 'Fusion Propulsion', 'cost' => 760, 'prerequisites' => ['propulsion_1'],
            'kind' => 'hardware', 'unlocks' => ['fusion_drive_mk1'], 'globalEffects' => [],
            'effect' => 'Unlocks Fusion Drive Mk III for new ship designs. Existing ships retain their installed drives.',
        ],
        'fuel_optimization_1' => [
            'id' => 'fuel_optimization_1', 'field' => 'propulsion', 'tier' => 1,
            'name' => 'Refined Fuel Chemistry', 'cost' => 420, 'prerequisites' => ['propulsion_1'],
            'kind' => 'applied', 'unlocks' => [], 'globalEffects' => ['fuel_efficiency_10'],
            'effect' => 'Applied fuel improvement: all fleets use 10% less fuel without replacing engines.',
        ],
        'fuel_optimization_2' => [
            'id' => 'fuel_optimization_2', 'field' => 'propulsion', 'tier' => 2,
            'name' => 'Catalytic Fuel Processing', 'cost' => 820, 'prerequisites' => ['fuel_optimization_1', 'propulsion_2'],
            'kind' => 'applied', 'unlocks' => [], 'globalEffects' => ['fuel_efficiency_20'],
            'effect' => 'Applied fuel improvement: all fleets use 20% less fuel without changing their engine model.',
        ],
        'sensors_1' => [
            'id' => 'sensors_1', 'field' => 'sensors', 'tier' => 1,
            'name' => 'Long-Range Telemetry', 'cost' => 320, 'prerequisites' => [],
            'kind' => 'hardware', 'unlocks' => ['survey_scanner_mk2', 'deep_space_array_mk2'], 'globalEffects' => [],
            'effect' => 'Unlocks Survey Scanner Mk II and Deep Space Array Mk II. Existing scanners are unchanged.',
        ],
        'sensors_2' => [
            'id' => 'sensors_2', 'field' => 'sensors', 'tier' => 2,
            'name' => 'Deep-Space Signal Analysis', 'cost' => 720, 'prerequisites' => ['sensors_1'],
            'kind' => 'hardware', 'unlocks' => ['deep_space_scanner_mk1', 'deep_space_array_mk3'], 'globalEffects' => [],
            'effect' => 'Unlocks Deep Space Scanner Mk III and Deep Space Array Mk III for new construction.',
        ],
        'weapons_1' => [
            'id' => 'weapons_1', 'field' => 'weapons', 'tier' => 1,
            'name' => 'Coherent Beam Theory', 'cost' => 340, 'prerequisites' => [],
            'kind' => 'hardware', 'unlocks' => ['beam_emitter_mk2'], 'globalEffects' => [],
            'effect' => 'Unlocks Beam Emitter Mk II for new ship designs. Existing weapons are unchanged.',
        ],
        'weapons_2' => [
            'id' => 'weapons_2', 'field' => 'weapons', 'tier' => 2,
            'name' => 'Phase-Locked Emitters', 'cost' => 780, 'prerequisites' => ['weapons_1'],
            'kind' => 'hardware', 'unlocks' => ['phase_emitter_mk1'], 'globalEffects' => [],
            'effect' => 'Unlocks Phase Emitter Mk III for future ship designs.',
        ],
        'defenses_1' => [
            'id' => 'defenses_1', 'field' => 'defenses', 'tier' => 1,
            'name' => 'Layered Defense Systems', 'cost' => 340, 'prerequisites' => [],
            'kind' => 'hardware', 'unlocks' => ['reinforced_armor_mk2', 'defense_grid_mk2'], 'globalEffects' => [],
            'effect' => 'Unlocks Reinforced Armor Mk II and Defense Grid Mk II. Existing hardware is unchanged.',
        ],
        'defenses_2' => [
            'id' => 'defenses_2', 'field' => 'defenses', 'tier' => 2,
            'name' => 'Planetary Shield Networks', 'cost' => 780, 'prerequisites' => ['defenses_1'],
            'kind' => 'hardware', 'unlocks' => ['shielded_armor_mk1', 'defense_grid_mk3'], 'globalEffects' => [],
            'effect' => 'Unlocks Shielded Armor Mk III and Defense Grid Mk III for new construction.',
        ],
        'industry_1' => [
            'id' => 'industry_1', 'field' => 'industry', 'tier' => 1,
            'name' => 'Automated Fabrication', 'cost' => 360, 'prerequisites' => [],
            'kind' => 'hardware', 'unlocks' => ['orbital_factory_mk2'], 'globalEffects' => [],
            'effect' => 'Unlocks Orbital Factory Mk II. Existing factories keep their installed model.',
        ],
        'industry_2' => [
            'id' => 'industry_2', 'field' => 'industry', 'tier' => 2,
            'name' => 'Autonomous Orbital Industry', 'cost' => 800, 'prerequisites' => ['industry_1'],
            'kind' => 'hardware', 'unlocks' => ['orbital_factory_mk3'], 'globalEffects' => [],
            'effect' => 'Unlocks Orbital Factory Mk III for new installations and future upgrades.',
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
        $fuelEfficiency = in_array('fuel_optimization_2', $completed, true)
            ? 20
            : (in_array('fuel_optimization_1', $completed, true) ? 10 : 0);

        // Hardware research no longer mutates equipment already in service.
        // These compatibility keys remain until all callers are model-aware.
        return [
            'fleetMovementRange' => 1,
            'colonySensorBonus' => 0,
            'fleetAttackPercent' => 0,
            'planetDefensePercent' => 0,
            'defenseGridAmount' => 250,
            'industryIncomePercent' => 0,
            'fuelEfficiencyPercent' => $fuelEfficiency,
        ];
    }
}
