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
        'colony_module_mk1' => ['id'=>'colony_module_mk1','category'=>'utility','family'=>'colony_module','name'=>'Colony Module Mk I','version'=>1,'requires'=>[],'description'=>'Single-use colonization package. A ship carrying it can establish one colony and is consumed when the colony is founded.','stats'=>['colonizationCapacity'=>1,'industryCost'=>180]],
    ];

    /** @var array<string, array<string, mixed>> */
    private const INSTALLATIONS = [
        'defense_grid_mk1' => ['id'=>'defense_grid_mk1','category'=>'installation','family'=>'defense_grid','name'=>'Defense Grid Mk I','version'=>1,'requires'=>[],'description'=>'First-generation planetary defense installation.','stats'=>['defenseAdd'=>250,'industryCost'=>250],'upgradeFrom'=>null,'upgradeCost'=>null],
        'defense_grid_mk2' => ['id'=>'defense_grid_mk2','category'=>'installation','family'=>'defense_grid','name'=>'Defense Grid Mk II','version'=>2,'requires'=>['defenses_1'],'description'=>'Improved planetary defense hardware.','stats'=>['defenseAdd'=>350,'industryCost'=>360],'upgradeFrom'=>'defense_grid_mk1','upgradeCost'=>190,'upgradeTurns'=>2],
        'defense_grid_mk3' => ['id'=>'defense_grid_mk3','category'=>'installation','family'=>'defense_grid','name'=>'Defense Grid Mk III','version'=>3,'requires'=>['defenses_2'],'description'=>'Shield-assisted planetary defense network.','stats'=>['defenseAdd'=>500,'industryCost'=>500],'upgradeFrom'=>'defense_grid_mk2','upgradeCost'=>260,'upgradeTurns'=>2],
        'orbital_factory_mk1' => ['id'=>'orbital_factory_mk1','category'=>'installation','family'=>'orbital_factory','name'=>'Orbital Factory Mk I','version'=>1,'requires'=>[],'description'=>'Baseline orbital production complex.','stats'=>['industryIncome'=>8,'developmentAdd'=>10,'industryCost'=>400],'upgradeFrom'=>null,'upgradeCost'=>null],
        'orbital_factory_mk2' => ['id'=>'orbital_factory_mk2','category'=>'installation','family'=>'orbital_factory','name'=>'Orbital Factory Mk II','version'=>2,'requires'=>['industry_1'],'description'=>'Automated second-generation production hardware.','stats'=>['industryIncome'=>11,'developmentAdd'=>12,'industryCost'=>520],'upgradeFrom'=>'orbital_factory_mk1','upgradeCost'=>260,'upgradeTurns'=>2],
        'orbital_factory_mk3' => ['id'=>'orbital_factory_mk3','category'=>'installation','family'=>'orbital_factory','name'=>'Orbital Factory Mk III','version'=>3,'requires'=>['industry_2'],'description'=>'Autonomous high-throughput orbital industry.','stats'=>['industryIncome'=>15,'developmentAdd'=>15,'industryCost'=>690],'upgradeFrom'=>'orbital_factory_mk2','upgradeCost'=>340,'upgradeTurns'=>2],
        'deep_space_array_mk1' => ['id'=>'deep_space_array_mk1','category'=>'installation','family'=>'deep_space_array','name'=>'Deep Space Array Mk I','version'=>1,'requires'=>[],'description'=>'Extends colony sensor coverage to two hops.','stats'=>['sensorRange'=>2,'industryCost'=>350],'upgradeFrom'=>null,'upgradeCost'=>null],
        'deep_space_array_mk2' => ['id'=>'deep_space_array_mk2','category'=>'installation','family'=>'deep_space_array','name'=>'Deep Space Array Mk II','version'=>2,'requires'=>['sensors_1'],'description'=>'Second-generation colony sensor installation.','stats'=>['sensorRange'=>3,'industryCost'=>470],'upgradeFrom'=>'deep_space_array_mk1','upgradeCost'=>230,'upgradeTurns'=>2],
        'deep_space_array_mk3' => ['id'=>'deep_space_array_mk3','category'=>'installation','family'=>'deep_space_array','name'=>'Deep Space Array Mk III','version'=>3,'requires'=>['sensors_2'],'description'=>'Long-range colony intelligence array.','stats'=>['sensorRange'=>4,'industryCost'=>620],'upgradeFrom'=>'deep_space_array_mk2','upgradeCost'=>300,'upgradeTurns'=>2],
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

    /**
     * @param list<array<string, mixed>> $components
     * @return array<string, mixed>
     */
    private static function buildDesign(array $components, int $generation, string $name, string $family, ?string $basedOnDesignId = null): array
    {
        $byCategory = [];
        $componentRefs = [];
        $industryCost = 0;
        foreach ($components as $component) {
            $category = (string) ($component['category'] ?? '');
            if ($category === '') {
                continue;
            }
            $byCategory[$category] = $component;
            $componentRefs[] = [
                'category' => $category,
                'modelId' => (string) $component['id'],
                'name' => (string) $component['name'],
                'version' => (int) $component['version'],
            ];
            $industryCost += max(0, (int) ($component['stats']['industryCost'] ?? 0));
        }

        foreach (['hull', 'engine', 'scanner', 'weapon', 'armor'] as $requiredCategory) {
            if (!isset($byCategory[$requiredCategory])) {
                throw new \LogicException(sprintf('Ship design is missing category %s.', $requiredCategory));
            }
        }

        $hull = $byCategory['hull'];
        $engine = $byCategory['engine'];
        $scanner = $byCategory['scanner'];
        $weapon = $byCategory['weapon'];
        $armor = $byCategory['armor'];
        $utility = $byCategory['utility'] ?? null;
        $colonizationCapacity = is_array($utility)
            ? max(0, (int) ($utility['stats']['colonizationCapacity'] ?? 0))
            : 0;
        $signature = implode(':', array_map(static fn (array $component): string => (string) $component['id'], $components));
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($family)) ?: 'ship';

        return [
            'id' => sprintf('%s-g%d-%s', trim($slug, '-'), $generation, substr(sha1($signature), 0, 10)),
            'name' => $name,
            'family' => $family,
            'generation' => $generation,
            'components' => $componentRefs,
            'stats' => [
                'movementRange' => max(1, (int) ($engine['stats']['movementRange'] ?? 1)),
                'sensorRange' => max(0, (int) ($scanner['stats']['sensorRange'] ?? 0)),
                'attack' => max(0, (int) ($weapon['stats']['attack'] ?? 0)),
                'defense' => max(0, (int) ($armor['stats']['defense'] ?? 0)),
                'fuelCapacity' => max(1, (int) ($hull['stats']['fuelCapacity'] ?? 100)),
                'fuelUsePerHop' => max(1, (int) ($engine['stats']['fuelUsePerHop'] ?? 35)),
                'colonizationCapacity' => $colonizationCapacity,
            ],
            'industryCost' => max(300, $industryCost),
            // Colony ships are individual strategic units. Ordinary light ships are
            // still produced in the existing 40-ship batch until production is
            // generalized further.
            'batchSize' => $colonizationCapacity > 0 ? 1 : 40,
            'unlocked' => true,
            'current' => true,
            'obsolete' => false,
            'basedOnDesignId' => $basedOnDesignId,
            'signature' => $signature,
        ];
    }

    /** @param list<string> $completed @return array<string, mixed> */
    private static function generatedScoutDesign(array $completed, int $generation): array
    {
        $components = [
            self::bestComponent('hull', $completed),
            self::bestComponent('engine', $completed),
            self::bestComponent('scanner', $completed),
            self::bestComponent('weapon', $completed),
            self::bestComponent('armor', $completed),
        ];

        return self::buildDesign(
            $components,
            $generation,
            sprintf('Scout Mk %s', self::roman($generation)),
            'scout',
        );
    }

    /** @param array<string, mixed> $state @return list<array<string, mixed>> */
    public static function playerDesigns(array $state, int $playerId): array
    {
        $designRoot = is_array($state['designs'] ?? null) ? $state['designs'] : [];
        $raw = $designRoot[(string) $playerId] ?? $designRoot[$playerId] ?? [];
        $designs = [];
        foreach (is_array($raw) ? $raw : [] as $design) {
            if (!is_array($design) || !is_string($design['id'] ?? null)) {
                continue;
            }
            $design['current'] = (bool) ($design['current'] ?? false);
            $design['obsolete'] = (bool) ($design['obsolete'] ?? false);
            $design['unlocked'] = true;
            $designs[] = $design;
        }

        // Research only unlocks component models. It must never silently create a
        // new ship generation. A new game therefore starts with exactly one
        // baseline Scout design and later generations are explicit player choices.
        if ($designs === []) {
            $baseline = self::generatedScoutDesign([], 1);
            unset($baseline['signature']);
            $designs[] = $baseline;
        }

        $currentIndex = null;
        $currentGeneration = -1;
        foreach ($designs as $index => $design) {
            if (($design['current'] ?? false) !== true || ($design['obsolete'] ?? false) === true) {
                continue;
            }
            $generation = (int) ($design['generation'] ?? 1);
            if ($generation > $currentGeneration) {
                $currentGeneration = $generation;
                $currentIndex = $index;
            }
        }
        if ($currentIndex === null) {
            foreach ($designs as $index => $design) {
                if (($design['obsolete'] ?? false) === true) {
                    continue;
                }
                $generation = (int) ($design['generation'] ?? 1);
                if ($generation > $currentGeneration) {
                    $currentGeneration = $generation;
                    $currentIndex = $index;
                }
            }
        }
        if ($currentIndex === null) {
            $currentIndex = array_key_last($designs);
        }

        foreach ($designs as $index => &$design) {
            $design['current'] = $index === $currentIndex;
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

    /**
     * Build and validate a new immutable ship generation from an existing design.
     * Research unlocks components; this method is the explicit step that turns
     * unlocked hardware into a new design.
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $componentModelIds category => model id
     * @return array<string, mixed>
     */
    public static function createDesign(array $state, int $playerId, string $baseDesignId, string $name, array $componentModelIds): array
    {
        $base = self::resolveDesign($state, $playerId, $baseDesignId);
        if ($base === null) {
            throw new \InvalidArgumentException(sprintf('Base ship design %s does not exist.', $baseDesignId));
        }

        $name = trim($name);
        if ($name === '' || strlen($name) > 48) {
            throw new \InvalidArgumentException('Ship design name must contain 1-48 characters.');
        }

        $research = ResearchCatalog::playerState($state, $playerId);
        $completed = is_array($research['completed'] ?? null) ? array_values($research['completed']) : [];
        $requiredCategories = ['hull', 'engine', 'scanner', 'weapon', 'armor'];
        $components = [];
        foreach ($requiredCategories as $category) {
            $modelId = is_string($componentModelIds[$category] ?? null) ? trim((string) $componentModelIds[$category]) : '';
            $component = $modelId !== '' ? (self::COMPONENTS[$modelId] ?? null) : null;
            if (!is_array($component) || ($component['category'] ?? null) !== $category) {
                throw new \InvalidArgumentException(sprintf('Ship design requires a valid %s component.', $category));
            }
            if (!self::unlocked($component, $completed)) {
                throw new \InvalidArgumentException(sprintf('%s is not unlocked by completed research.', (string) $component['name']));
            }
            $components[] = $component;
        }

        // Utility is optional. Dev6 starts with a single Colony Module model;
        // later utility modules can reuse the same immutable design slot.
        $utilityModelId = is_string($componentModelIds['utility'] ?? null) ? trim((string) $componentModelIds['utility']) : '';
        if ($utilityModelId !== '') {
            $utility = self::COMPONENTS[$utilityModelId] ?? null;
            if (!is_array($utility) || ($utility['category'] ?? null) !== 'utility') {
                throw new \InvalidArgumentException('Ship design requires a valid utility component.');
            }
            if (!self::unlocked($utility, $completed)) {
                throw new \InvalidArgumentException(sprintf('%s is not unlocked by completed research.', (string) $utility['name']));
            }
            $components[] = $utility;
        }

        $baseHullId = null;
        foreach (is_array($base['components'] ?? null) ? $base['components'] : [] as $component) {
            if (is_array($component) && ($component['category'] ?? null) === 'hull') {
                $baseHullId = is_string($component['modelId'] ?? null) ? $component['modelId'] : null;
                break;
            }
        }
        $baseHull = $baseHullId !== null ? (self::COMPONENTS[$baseHullId] ?? null) : null;
        $newHull = $components[0] ?? null;
        if (is_array($baseHull) && is_array($newHull) && ($baseHull['family'] ?? null) !== ($newHull['family'] ?? null)) {
            throw new \InvalidArgumentException('A new generation must keep the same hull family as its base design.');
        }

        $designs = self::playerDesigns($state, $playerId);
        foreach ($designs as $existing) {
            if (strcasecmp((string) ($existing['name'] ?? ''), $name) === 0) {
                throw new \InvalidArgumentException(sprintf('A ship design named %s already exists.', $name));
            }
        }

        $family = (string) ($base['family'] ?? 'scout');
        $latestGeneration = 0;
        foreach ($designs as $existing) {
            if (($existing['family'] ?? null) === $family) {
                $latestGeneration = max($latestGeneration, (int) ($existing['generation'] ?? 1));
            }
        }
        $candidate = self::buildDesign($components, $latestGeneration + 1, $name, $family, $baseDesignId);
        $candidateSignature = (string) ($candidate['signature'] ?? '');
        foreach ($designs as $existing) {
            if (self::designSignature($existing) === $candidateSignature) {
                throw new \InvalidArgumentException('That exact component combination already exists as a ship design.');
            }
        }
        unset($candidate['signature']);

        return $candidate;
    }

    /** @param array<string, mixed> $state @param array<string, mixed> $design @return array<string, mixed> */
    public static function appendDesign(array $state, int $playerId, array $design, int $turnNumber): array
    {
        $designs = self::playerDesigns($state, $playerId);
        foreach ($designs as &$existing) {
            $existing['current'] = false;
        }
        unset($existing);

        $design['current'] = true;
        $design['obsolete'] = false;
        $design['unlocked'] = true;
        $design['createdTurn'] = $turnNumber;
        $designs[] = $design;
        $state['designs'] = is_array($state['designs'] ?? null) ? $state['designs'] : [];
        $state['designs'][(string) $playerId] = array_values($designs);
        return $state;
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
        $compositionColonizationCapacity = 0;
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
            $compositionColonizationCapacity += $quantity * max(0, (int) ($stats['colonizationCapacity'] ?? 0));
        }

        // Pre-dev6 starting exploration fleets carried one fleet-level colony
        // module. Keep that capacity separate from component-backed colony ships
        // so split/merge/transfer operations never duplicate or silently discard it.
        $legacyColonizationCapacity = max(0, (int) ($fleet['legacyColonizationCapacity'] ?? 0));
        if (!array_key_exists('legacyColonizationCapacity', $fleet) && isset($fleet['colonizationCapacity']) && is_numeric($fleet['colonizationCapacity'])) {
            $legacyColonizationCapacity = max(
                0,
                (int) $fleet['colonizationCapacity'] - $compositionColonizationCapacity,
            );
        }
        $research = ResearchCatalog::playerState($state, $playerId);
        $fuelEfficiency = max(0, min(80, (int) ($research['modifiers']['fuelEfficiencyPercent'] ?? 0)));
        if ($fuelEfficiency > 0) {
            $fuelUse = (int) ceil($fuelUse * (100 - $fuelEfficiency) / 100);
        }

        $fuel = array_key_exists('fuel', $fleet) && is_numeric($fleet['fuel'])
            ? max(0, min($fuelCapacity, (int) $fleet['fuel']))
            : $fuelCapacity;
        $operationalRange = $fuelUse > 0 ? intdiv($fuel, $fuelUse) : 0;

        $fleet['composition'] = $composition;
        $fleet['movementRange'] = max(1, $movement ?? 1);
        $fleet['sensorRange'] = $sensor;
        $fleet['attack'] = $attack;
        $fleet['defense'] = $defense;
        $fleet['fuelCapacity'] = $fuelCapacity;
        $fleet['fuelUsePerHop'] = $fuelUse;
        $fleet['fuel'] = $fuel;
        $fleet['operationalRange'] = max(0, $operationalRange);
        $fleet['legacyColonizationCapacity'] = $legacyColonizationCapacity;
        $fleet['colonizationCapacity'] = $compositionColonizationCapacity + $legacyColonizationCapacity;
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
                return $system;
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

    /** @param array<string, mixed> $state @return array<string, mixed>|null */
    public static function installationUpgradeDefinition(array $state, int $playerId, string $sourceModelId, string $targetModelId): ?array
    {
        $target = self::model($targetModelId);
        if ($target === null || ($target['category'] ?? null) !== 'installation') {
            return null;
        }
        if (($target['upgradeFrom'] ?? null) !== $sourceModelId) {
            return null;
        }

        foreach (self::publicForPlayer($state, $playerId)['installations'] as $candidate) {
            if (($candidate['id'] ?? null) === $targetModelId && ($candidate['unlocked'] ?? false) === true) {
                return $target;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $system @return array<string, mixed>|null */
    public static function pendingUpgradeForFamily(array $system, string $family): ?array
    {
        foreach (is_array($system['installationUpgrades'] ?? null) ? $system['installationUpgrades'] : [] as $upgrade) {
            if (is_array($upgrade) && ($upgrade['family'] ?? null) === $family) {
                return $upgrade;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $system @param array<string, mixed> $targetModel @return array<string, mixed> */
    public static function startInstallationUpgrade(array $system, array $targetModel, int $turnNumber): array
    {
        $family = (string) ($targetModel['family'] ?? '');
        $sourceModelId = is_string($targetModel['upgradeFrom'] ?? null) ? (string) $targetModel['upgradeFrom'] : '';
        $source = self::installationForFamily($system, $family);
        if ($family === '' || $sourceModelId === '' || $source === null || ($source['modelId'] ?? null) !== $sourceModelId) {
            return $system;
        }
        if (self::pendingUpgradeForFamily($system, $family) !== null) {
            return $system;
        }

        $sourceModel = self::model($sourceModelId);
        $upgradeTurns = max(1, (int) ($targetModel['upgradeTurns'] ?? 1));
        $upgrades = is_array($system['installationUpgrades'] ?? null) ? array_values($system['installationUpgrades']) : [];
        $upgrades[] = [
            'family' => $family,
            'fromModelId' => $sourceModelId,
            'fromName' => (string) ($sourceModel['name'] ?? $source['name'] ?? $sourceModelId),
            'fromVersion' => (int) ($sourceModel['version'] ?? $source['version'] ?? 1),
            'toModelId' => (string) $targetModel['id'],
            'toName' => (string) $targetModel['name'],
            'toVersion' => (int) $targetModel['version'],
            'industryCost' => max(1, (int) ($targetModel['upgradeCost'] ?? 0)),
            'turnsTotal' => $upgradeTurns,
            // The turn in which the order is processed counts as the first work turn.
            'turnsRemaining' => max(1, $upgradeTurns - 1),
            'startedTurn' => $turnNumber,
        ];
        $system['installationUpgrades'] = $upgrades;
        return $system;
    }

    /**
     * @param array<string, mixed> $system
     * @return array{system:array<string,mixed>,completed:list<array<string,mixed>>,warnings:list<string>}
     */
    public static function advanceInstallationUpgrades(array $system, int $turnNumber): array
    {
        $pending = is_array($system['installationUpgrades'] ?? null) ? array_values($system['installationUpgrades']) : [];
        if ($pending === []) {
            return ['system' => $system, 'completed' => [], 'warnings' => []];
        }

        $remainingUpgrades = [];
        $completed = [];
        $warnings = [];
        foreach ($pending as $upgrade) {
            if (!is_array($upgrade)) {
                continue;
            }
            $family = is_string($upgrade['family'] ?? null) ? $upgrade['family'] : '';
            $fromModelId = is_string($upgrade['fromModelId'] ?? null) ? $upgrade['fromModelId'] : '';
            $toModelId = is_string($upgrade['toModelId'] ?? null) ? $upgrade['toModelId'] : '';
            $turnsRemaining = max(1, (int) ($upgrade['turnsRemaining'] ?? 1));

            if ($turnsRemaining > 1) {
                $upgrade['turnsRemaining'] = $turnsRemaining - 1;
                $remainingUpgrades[] = $upgrade;
                continue;
            }

            $target = self::model($toModelId);
            $installed = self::installationForFamily($system, $family);
            if ($target === null || ($target['category'] ?? null) !== 'installation' || ($target['upgradeFrom'] ?? null) !== $fromModelId) {
                $warnings[] = sprintf('Installation upgrade %s -> %s is no longer valid.', $fromModelId, $toModelId);
                continue;
            }
            if ($installed === null || ($installed['modelId'] ?? null) !== $fromModelId) {
                $warnings[] = sprintf('Installation upgrade %s -> %s could not complete because the source model changed.', $fromModelId, $toModelId);
                continue;
            }

            $system = self::upgradeInstallation($system, $target, $turnNumber);
            $completed[] = [
                'systemId' => (string) ($system['id'] ?? ''),
                'family' => $family,
                'fromModelId' => $fromModelId,
                'fromName' => (string) ($upgrade['fromName'] ?? $fromModelId),
                'fromVersion' => (int) ($upgrade['fromVersion'] ?? 1),
                'toModelId' => $toModelId,
                'toName' => (string) $target['name'],
                'toVersion' => (int) $target['version'],
                'industryCost' => max(1, (int) ($upgrade['industryCost'] ?? $target['upgradeCost'] ?? 0)),
                'completedTurn' => $turnNumber,
            ];
        }

        $system['installationUpgrades'] = array_values($remainingUpgrades);
        return ['system' => $system, 'completed' => $completed, 'warnings' => $warnings];
    }

    /** @param array<string, mixed> $system @param array<string, mixed> $targetModel @return array<string, mixed> */
    public static function upgradeInstallation(array $system, array $targetModel, int $turnNumber): array
    {
        $family = (string) ($targetModel['family'] ?? '');
        $sourceModelId = is_string($targetModel['upgradeFrom'] ?? null) ? (string) $targetModel['upgradeFrom'] : '';
        $sourceModel = self::model($sourceModelId);
        if ($family === '' || $sourceModel === null) {
            return $system;
        }

        $installations = is_array($system['installations'] ?? null) ? array_values($system['installations']) : [];
        $replaced = false;
        foreach ($installations as $index => $installation) {
            if (!is_array($installation) || ($installation['family'] ?? null) !== $family || ($installation['modelId'] ?? null) !== $sourceModelId) {
                continue;
            }
            $installations[$index] = [
                'family' => $family,
                'modelId' => (string) $targetModel['id'],
                'name' => (string) $targetModel['name'],
                'version' => (int) $targetModel['version'],
                'installedTurn' => $turnNumber,
            ];
            $replaced = true;
            break;
        }
        if (!$replaced) {
            return $system;
        }
        $system['installations'] = $installations;

        $fromStats = is_array($sourceModel['stats'] ?? null) ? $sourceModel['stats'] : [];
        $toStats = is_array($targetModel['stats'] ?? null) ? $targetModel['stats'] : [];
        if ($family === 'defense_grid') {
            $delta = (int) ($toStats['defenseAdd'] ?? 0) - (int) ($fromStats['defenseAdd'] ?? 0);
            $system['defenses'] = max(0, (int) ($system['defenses'] ?? 0) + $delta);
        } elseif ($family === 'orbital_factory') {
            $developmentDelta = (int) ($toStats['developmentAdd'] ?? 0) - (int) ($fromStats['developmentAdd'] ?? 0);
            $system['development'] = min(100, max(0, (int) ($system['development'] ?? 0) + $developmentDelta));
            $incomeDelta = (int) ($toStats['industryIncome'] ?? 0) - (int) ($fromStats['industryIncome'] ?? 0);
            $resources = is_array($system['resources'] ?? null) ? array_values($system['resources']) : [];
            foreach ($resources as $resourceIndex => $resource) {
                if (is_array($resource) && ($resource['id'] ?? null) === 'industry') {
                    $resources[$resourceIndex]['income'] = max(0, (int) ($resource['income'] ?? 0) + $incomeDelta);
                    break;
                }
            }
            $system['resources'] = $resources;
        } elseif ($family === 'deep_space_array') {
            $system['sensorRange'] = max((int) ($system['sensorRange'] ?? 1), max(1, (int) ($toStats['sensorRange'] ?? 1)));
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
