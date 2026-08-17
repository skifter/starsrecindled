<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

final class TechnologyModelCatalog
{
    /** @var array<string, array<string, mixed>> */
    private const COMPONENTS = [
        'scout_hull_mk1' => ['id'=>'scout_hull_mk1','category'=>'hull','family'=>'scout_hull','name'=>'Scout Hull Mk I','version'=>1,'requires'=>[],'description'=>'Baseline light exploration hull.','stats'=>['fuelCapacity'=>100,'industryCost'=>120]],
        'chemical_drive_mk1' => ['id'=>'chemical_drive_mk1','category'=>'engine','family'=>'drive','name'=>'Chemical Drive Mk I','version'=>1,'requires'=>[],'description'=>'Reliable first-generation drive.','stats'=>['movementRange'=>1,'fuelUsePerHop'=>35,'industryCost'=>70]],
        'ion_drive_mk1' => ['id'=>'ion_drive_mk1','category'=>'engine','family'=>'drive','name'=>'Ion Drive Mk II','version'=>2,'requires'=>['propulsion_1'],'description'=>'New-build ion propulsion. Existing ships are unchanged.','stats'=>['movementRange'=>2,'fuelUsePerHop'=>32,'industryCost'=>105]],
        'fusion_drive_mk1' => ['id'=>'fusion_drive_mk1','category'=>'engine','family'=>'drive','name'=>'Fusion Drive Mk III','version'=>3,'requires'=>['propulsion_2'],'description'=>'High-energy drive for new ship generations.','stats'=>['movementRange'=>3,'fuelUsePerHop'=>29,'industryCost'=>150]],
        'survey_scanner_mk1' => ['id'=>'survey_scanner_mk1','category'=>'scanner','family'=>'scanner','name'=>'Survey Scanner Mk I','version'=>1,'requires'=>[],'description'=>'Baseline fleet sensor package.','stats'=>['sensorRange'=>1,'industryCost'=>35]],
        'survey_scanner_mk2' => ['id'=>'survey_scanner_mk2','category'=>'scanner','family'=>'scanner','name'=>'Survey Scanner Mk II','version'=>2,'requires'=>['sensors_1'],'description'=>'Improved hardware for newly constructed ships.','stats'=>['sensorRange'=>2,'industryCost'=>60]],
        'deep_space_scanner_mk1' => ['id'=>'deep_space_scanner_mk1','category'=>'scanner','family'=>'scanner','name'=>'Deep Space Scanner Mk III','version'=>3,'requires'=>['sensors_2'],'description'=>'Long-range fleet sensor hardware.','stats'=>['sensorRange'=>3,'industryCost'=>90]],
        'light_laser_mk1' => ['id'=>'light_laser_mk1','category'=>'weapon','family'=>'beam_weapon','name'=>'Light Laser Mk I','version'=>1,'requires'=>[],'description'=>'Baseline defensive beam armament.','stats'=>['attack'=>2,'industryCost'=>30]],
        'beam_emitter_mk2' => ['id'=>'beam_emitter_mk2','category'=>'weapon','family'=>'beam_weapon','name'=>'Beam Emitter Mk II','version'=>2,'requires'=>['weapons_1'],'description'=>'Higher-output beam hardware for new ship models.','stats'=>['attack'=>4,'industryCost'=>55]],
        'phase_emitter_mk1' => ['id'=>'phase_emitter_mk1','category'=>'weapon','family'=>'beam_weapon','name'=>'Phase Emitter Mk III','version'=>3,'requires'=>['weapons_2'],'description'=>'Advanced coherent weapon system.','stats'=>['attack'=>7,'industryCost'=>85]],
        'light_armor_mk1' => ['id'=>'light_armor_mk1','category'=>'armor','family'=>'armor','name'=>'Light Armor Mk I','version'=>1,'requires'=>[],'description'=>'Baseline structural protection.','stats'=>['defense'=>2,'industryCost'=>45]],
        'reinforced_armor_mk2' => ['id'=>'reinforced_armor_mk2','category'=>'armor','family'=>'armor','name'=>'Reinforced Armor Mk II','version'=>2,'requires'=>['defenses_1'],'description'=>'Heavier protection for new-build ships.','stats'=>['defense'=>4,'industryCost'=>70]],
        'shielded_armor_mk1' => ['id'=>'shielded_armor_mk1','category'=>'armor','family'=>'armor','name'=>'Shielded Armor Mk III','version'=>3,'requires'=>['defenses_2'],'description'=>'Integrated shielding and armor package.','stats'=>['defense'=>7,'industryCost'=>105]],
    ];

    /** @var array<string, array<string, mixed>> */
    private const INSTALLATIONS = [
        'defense_grid_mk1' => ['id'=>'defense_grid_mk1','category'=>'installation','family'=>'defense_grid','name'=>'Defense Grid Mk I','version'=>1,'requires'=>[],'description'=>'First-generation planetary defense installation.','stats'=>['defenseAdd'=>250,'industryCost'=>250],'upgradeFrom'=>null,'upgradeCost'=>null],
        'defense_grid_mk2' => ['id'=>'defense_grid_mk2','category'=>'installation','family'=>'defense_grid','name'=>'Defense Grid Mk II','version'=>2,'requires'=>['defenses_1'],'description'=>'Improved planetary defense hardware.','stats'=>['defenseAdd'=>350,'industryCost'=>360],'upgradeFrom'=>'defense_grid_mk1','upgradeCost'=>190],
        'defense_grid_mk3' => ['id'=>'defense_grid_mk3','category'=>'installation','family'=>'defense_grid','name'=>'Defense Grid Mk III','version'=>3,'requires'=>['defenses_2'],'description'=>'Shield-assisted planetary defense network.','stats'=>['defenseAdd'=>500,'industryCost'=>500],'upgradeFrom'=>'defense_grid_mk2','upgradeCost'=>260],
        'orbital_factory_mk1' => ['id'=>'orbital_factory_mk1','category'=>'installation','family'=>'orbital_factory','name'=>'Orbital Factory Mk I','version'=>1,'requires'=>[],'description'=>'Baseline orbital production complex.','stats'=>['industryIncome'=>8,'developmentAdd'=>10,'industryCost'=>400],'upgradeFrom'=>null,'upgradeCost'=>null],
        'orbital_factory_mk2' => ['id'=>'orbital_factory_mk2','category'=>'installation','family'=>'orbital_factory','name'=>'Orbital Factory Mk II','version'=>2,'requires'=>['industry_1'],'description'=>'Automated second-generation production hardware.','stats'=>['industryIncome'=>11,'developmentAdd'=>12,'industryCost'=>520],'upgradeFrom'=>'orbital_factory_mk1','upgradeCost'=>260],
        'orbital_factory_mk3' => ['id'=>'orbital_factory_mk3','category'=>'installation','family'=>'orbital_factory','name'=>'Orbital Factory Mk III','version'=>3,'requires'=>['industry_2'],'description'=>'Autonomous high-throughput orbital industry.','stats'=>['industryIncome'=>15,'developmentAdd'=>15,'industryCost'=>690],'upgradeFrom'=>'orbital_factory_mk2','upgradeCost'=>340],
        'deep_space_array_mk1' => ['id'=>'deep_space_array_mk1','category'=>'installation','family'=>'deep_space_array','name'=>'Deep Space Array Mk I','version'=>1,'requires'=>[],'description'=>'Extends colony sensor coverage to two hops.','stats'=>['sensorRange'=>2,'industryCost'=>350],'upgradeFrom'=>null,'upgradeCost'=>null],
        'deep_space_array_mk2' => ['id'=>'deep_space_array_mk2','category'=>'installation','family'=>'deep_space_array','name'=>'Deep Space Array Mk II','version'=>2,'requires'=>['sensors_1'],'description'=>'Second-generation colony sensor installation.','stats'=>['sensorRange'=>3,'industryCost'=>470],'upgradeFrom'=>'deep_space_array_mk1','upgradeCost'=>230],
        'deep_space_array_mk3' => ['id'=>'deep_space_array_mk3','category'=>'installation','family'=>'deep_space_array','name'=>'Deep Space Array Mk III','version'=>3,'requires'=>['sensors_2'],'description'=>'Long-range colony intelligence array.','stats'=>['sensorRange'=>4,'industryCost'=>620],'upgradeFrom'=>'deep_space_array_mk2','upgradeCost'=>300],
    ];

    /** @return array<string, mixed>|null */
    public static function model(string $id): ?array
    {
        return self::COMPONENTS[$id] ?? self::INSTALLATIONS[$id] ?? null;
    }

    /** @param list<string> $completed */
    private static function unlocked(array $definition, array $completed): bool
    {
        foreach (($definition['requires'] ?? []) as $requirement) {
            if (!in_array($requirement, $completed, true)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $completed @return list<array<string, mixed>> */
    private static function publicModels(array $definitions, array $completed): array
    {
        $result = [];
        foreach ($definitions as $definition) {
            $definition['unlocked'] = self::unlocked($definition, $completed);
            $result[] = $definition;
        }
        return $result;
    }

    /** @param list<string> $completed @return array<string, mixed> */
    private static function bestComponent(string $category, array $completed): array
    {
        $candidates = array_values(array_filter(
            self::COMPONENTS,
            static fn (array $definition): bool => ($definition['category'] ?? null) === $category,
        ));
        usort($candidates, static fn (array $a, array $b): int => ((int) $b['version']) <=> ((int) $a['version']));
        foreach ($candidates as $candidate) {
            if (self::unlocked($candidate, $completed)) {
                return $candidate;
            }
        }
        throw new \LogicException(sprintf('No baseline component for category %s.', $category));
    }

    /** @param list<string> $completed @return array<string, mixed> */
    private static function generatedScoutDesign(array $completed, int $generation): array
    {
        $hull = self::bestComponent('hull', $completed);
        $engine = self::bestComponent('engine', $completed);
        $scanner = self::bestComponent('scanner', $completed);
        $weapon = self::bestComponent('weapon', $completed);
        $armor = self::bestComponent('armor', $completed);
        $components = [$hull, $engine, $scanner, $weapon, $armor];
        $componentRefs = array_map(static fn (array $component): array => [
            'category' => (string) $component['category'],
            'modelId' => (string) $component['id'],
            'name' => (string) $component['name'],
            'version' => (int) $component['version'],
        ], $components);
        $industryCost = 0;
        foreach ($components as $component) {
            $industryCost += (int) ($component['stats']['industryCost'] ?? 0);
        }
        $stats = [
            'movementRange' => max(1, (int) ($engine['stats']['movementRange'] ?? 1)),
            'sensorRange' => max(0, (int) ($scanner['stats']['sensorRange'] ?? 0)),
            'attack' => max(0, (int) ($weapon['stats']['attack'] ?? 0)),
            'defense' => max(0, (int) ($armor['stats']['defense'] ?? 0)),
            'fuelCapacity' => max(1, (int) ($hull['stats']['fuelCapacity'] ?? 100)),
            'fuelUsePerHop' => max(1, (int) ($engine['stats']['fuelUsePerHop'] ?? 35)),
        ];
        $signature = implode(':', array_map(static fn (array $component): string => (string) $component['id'], $components));

        return [
            'id' => sprintf('scout-g%d-%s', $generation, substr(sha1($signature), 0, 10)),
            'name' => sprintf('Scout Mk %s', self::roman($generation)),
            'family' => 'scout',
            'generation' => $generation,
            'components' => $componentRefs,
            'stats' => $stats,
            'industryCost' => max(300, $industryCost),
            'batchSize' => 40,
            'unlocked' => true,
            'current' => true,
            'obsolete' => false,
            'signature' => $signature,
        ];
    }

    /** @param array<string, mixed> $state @return list<array<string, mixed>> */
    public static function playerDesigns(array $state, int $playerId): array
    {
        $research = ResearchCatalog::playerState($state, $playerId);
        $completed = is_array($research['completed'] ?? null) ? array_values($research['completed']) : [];
        $designRoot = is_array($state['designs'] ?? null) ? $state['designs'] : [];
        $raw = $designRoot[(string) $playerId] ?? $designRoot[$playerId] ?? [];
        $designs = [];
        foreach (is_array($raw) ? $raw : [] as $design) {
            if (!is_array($design) || !is_string($design['id'] ?? null)) {
                continue;
            }
            $design['current'] = false;
            $design['obsolete'] = (bool) ($design['obsolete'] ?? false);
            $design['unlocked'] = true;
            $design['signature'] = self::designSignature($design);
            $designs[] = $design;
        }

        if ($designs === []) {
            $designs[] = self::generatedScoutDesign([], 1);
            $designs[0]['current'] = false;
        }

        $latestGeneration = max(array_map(static fn (array $design): int => (int) ($design['generation'] ?? 1), $designs));
        $currentCandidate = self::generatedScoutDesign($completed, $latestGeneration + 1);
        $currentSignature = (string) ($currentCandidate['signature'] ?? '');
        $matchingIndex = null;
        foreach ($designs as $index => $design) {
            if (($design['signature'] ?? null) === $currentSignature) {
                $matchingIndex = $index;
                break;
            }
        }
        if ($matchingIndex === null) {
            $designs[] = $currentCandidate;
            $matchingIndex = array_key_last($designs);
        }
        foreach ($designs as $index => &$design) {
            $design['current'] = $index === $matchingIndex;
            unset($design['signature']);
        }
        unset($design);

        return array_values($designs);
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public static function publicForPlayer(array $state, int $playerId): array
    {
        $research = ResearchCatalog::playerState($state, $playerId);
        $completed = is_array($research['completed'] ?? null) ? array_values($research['completed']) : [];
        return [
            'components' => self::publicModels(self::COMPONENTS, $completed),
            'installations' => self::publicModels(self::INSTALLATIONS, $completed),
            'designs' => self::playerDesigns($state, $playerId),
        ];
    }

    /** @param array<string, mixed> $state @return array<string, mixed>|null */
    public static function resolveDesign(array $state, int $playerId, string $designId): ?array
    {
        foreach (self::playerDesigns($state, $playerId) as $design) {
            if (($design['id'] ?? null) === $designId) {
                return $design;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public static function currentDesign(array $state, int $playerId): array
    {
        foreach (self::playerDesigns($state, $playerId) as $design) {
            if (($design['current'] ?? false) === true) {
                return $design;
            }
        }
        return self::generatedScoutDesign([], 1);
    }

    /** @param array<string, mixed> $fleet @param array<string, mixed> $state @return array<string, mixed> */
    public static function normalizeFleet(array $fleet, array $state): array
    {
        $playerId = (int) ($fleet['ownerPlayerId'] ?? 0);
        $ships = max(0, (int) ($fleet['ships'] ?? 0));
        $composition = is_array($fleet['composition'] ?? null) ? array_values($fleet['composition']) : [];
        if ($composition === []) {
            $base = self::generatedScoutDesign([], 1);
            $composition = [[
                'designId' => (string) $base['id'],
                'designName' => (string) $base['name'],
                'generation' => (int) $base['generation'],
                'quantity' => $ships,
                'components' => $base['components'],
                'stats' => $base['stats'],
            ]];
        }

        $movement = null;
        $sensor = 0;
        $attack = 0;
        $defense = 0;
        $fuelCapacity = 0;
        $fuelUse = 0;
        foreach ($composition as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $quantity = max(0, (int) ($entry['quantity'] ?? 0));
            $stats = is_array($entry['stats'] ?? null) ? $entry['stats'] : [];
            $entryMovement = max(1, (int) ($stats['movementRange'] ?? 1));
            $movement = $movement === null ? $entryMovement : min($movement, $entryMovement);
            $sensor = max($sensor, max(0, (int) ($stats['sensorRange'] ?? 0)));
            $attack += $quantity * max(0, (int) ($stats['attack'] ?? 0));
            $defense += $quantity * max(0, (int) ($stats['defense'] ?? 0));
            $fuelCapacity += $quantity * max(0, (int) ($stats['fuelCapacity'] ?? 0));
            $fuelUse += $quantity * max(0, (int) ($stats['fuelUsePerHop'] ?? 0));
        }
        $research = ResearchCatalog::playerState($state, $playerId);
        $fuelEfficiency = max(0, min(80, (int) ($research['modifiers']['fuelEfficiencyPercent'] ?? 0)));
        if ($fuelEfficiency > 0) {
            $fuelUse = (int) ceil($fuelUse * (100 - $fuelEfficiency) / 100);
        }

        $fleet['composition'] = $composition;
        $fleet['movementRange'] = max(1, $movement ?? 1);
        $fleet['sensorRange'] = $sensor;
        $fleet['attack'] = $attack;
        $fleet['defense'] = $defense;
        $fleet['fuelCapacity'] = $fuelCapacity;
        $fleet['fuelUsePerHop'] = $fuelUse;
        return $fleet;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public static function normalizeState(array $state): array
    {
        $fleets = is_array($state['universe']['fleets'] ?? null) ? array_values($state['universe']['fleets']) : [];
        foreach ($fleets as $index => $fleet) {
            if (is_array($fleet)) {
                $fleets[$index] = self::normalizeFleet($fleet, $state);
            }
        }
        $state['universe']['fleets'] = $fleets;

        $systems = is_array($state['universe']['systems'] ?? null) ? array_values($state['universe']['systems']) : [];
        foreach ($systems as $index => $system) {
            if (!is_array($system)) {
                continue;
            }
            $installations = is_array($system['installations'] ?? null) ? array_values($system['installations']) : [];
            $families = [];
            foreach ($installations as $installation) {
                if (is_array($installation) && is_string($installation['family'] ?? null)) {
                    $families[$installation['family']] = true;
                }
            }
            if ((int) ($system['sensorRange'] ?? 1) > 1 && !isset($families['deep_space_array'])) {
                $range = (int) $system['sensorRange'];
                $id = $range >= 4 ? 'deep_space_array_mk3' : ($range >= 3 ? 'deep_space_array_mk2' : 'deep_space_array_mk1');
                $model = self::INSTALLATIONS[$id];
                $installations[] = ['family'=>'deep_space_array','modelId'=>$id,'name'=>$model['name'],'version'=>$model['version']];
                $families['deep_space_array'] = true;
            }
            if ((int) ($system['defenses'] ?? 0) > 0 && !isset($families['defense_grid'])) {
                $model = self::INSTALLATIONS['defense_grid_mk1'];
                $installations[] = ['family'=>'defense_grid','modelId'=>'defense_grid_mk1','name'=>$model['name'],'version'=>1];
                $families['defense_grid'] = true;
            }
            if ((int) ($system['development'] ?? 0) >= 18 && !isset($families['orbital_factory'])) {
                $model = self::INSTALLATIONS['orbital_factory_mk1'];
                $installations[] = ['family'=>'orbital_factory','modelId'=>'orbital_factory_mk1','name'=>$model['name'],'version'=>1];
            }
            $systems[$index]['installations'] = $installations;
        }
        $state['universe']['systems'] = $systems;

        $playerIds = [];
        foreach ($systems as $system) {
            if (is_array($system) && isset($system['ownerPlayerId']) && is_numeric($system['ownerPlayerId'])) {
                $playerIds[(int) $system['ownerPlayerId']] = true;
            }
        }
        foreach ($fleets as $fleet) {
            if (is_array($fleet) && isset($fleet['ownerPlayerId']) && is_numeric($fleet['ownerPlayerId'])) {
                $playerIds[(int) $fleet['ownerPlayerId']] = true;
            }
        }
        $state['designs'] = is_array($state['designs'] ?? null) ? $state['designs'] : [];
        foreach (array_keys($playerIds) as $playerId) {
            $state['designs'][(string) $playerId] = self::playerDesigns($state, $playerId);
        }
        return $state;
    }

    /** @param array<string, mixed> $system */
    public static function installationForFamily(array $system, string $family): ?array
    {
        foreach (is_array($system['installations'] ?? null) ? $system['installations'] : [] as $installation) {
            if (is_array($installation) && ($installation['family'] ?? null) === $family) {
                return $installation;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $system @param array<string, mixed> $model @return array<string, mixed> */
    public static function install(array $system, array $model, int $turnNumber): array
    {
        $family = (string) ($model['family'] ?? '');
        $installations = is_array($system['installations'] ?? null) ? array_values($system['installations']) : [];
        foreach ($installations as $existing) {
            if (is_array($existing) && ($existing['family'] ?? null) === $family) {
                return $system; // Upgrade/refit is intentionally reserved for 0.7.2.
            }
        }
        $installations[] = [
            'family' => $family,
            'modelId' => (string) $model['id'],
            'name' => (string) $model['name'],
            'version' => (int) $model['version'],
            'installedTurn' => $turnNumber,
        ];
        $system['installations'] = $installations;
        $stats = is_array($model['stats'] ?? null) ? $model['stats'] : [];
        if ($family === 'defense_grid') {
            $system['defenses'] = max(0, (int) ($system['defenses'] ?? 0)) + max(0, (int) ($stats['defenseAdd'] ?? 0));
        } elseif ($family === 'orbital_factory') {
            $system['development'] = min(100, max(0, (int) ($system['development'] ?? 0)) + max(0, (int) ($stats['developmentAdd'] ?? 0)));
        } elseif ($family === 'deep_space_array') {
            $system['sensorRange'] = max((int) ($system['sensorRange'] ?? 1), max(1, (int) ($stats['sensorRange'] ?? 1)));
        }
        return $system;
    }

    /** @param array<string, mixed> $design */
    private static function designSignature(array $design): string
    {
        $ids = [];
        foreach (is_array($design['components'] ?? null) ? $design['components'] : [] as $component) {
            if (is_array($component) && is_string($component['modelId'] ?? null)) {
                $ids[] = $component['modelId'];
            }
        }
        return implode(':', $ids);
    }

    private static function roman(int $value): string
    {
        return match ($value) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
            default => (string) $value,
        };
    }
}
