<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root.'/vendor/autoload.php')) {
    require $root.'/vendor/autoload.php';
} else {
    require $root.'/src/Domain/ResearchCatalog.php';
    require $root.'/src/Domain/TechnologyModelCatalog.php';
}

use Bellcom\StarsTurnBundle\Domain\DemoTurnEngine;
use Bellcom\StarsTurnBundle\Domain\TechnologyModelCatalog;
use Bellcom\StarsTurnBundle\Entity\Game;
use Bellcom\StarsTurnBundle\Entity\Turn;

function checkFuel074(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param array<string,mixed> $design @return array<string,mixed> */
function fuel074Entry(array $design, int $quantity): array
{
    return [
        'designId' => (string) $design['id'],
        'designName' => (string) $design['name'],
        'generation' => (int) $design['generation'],
        'quantity' => $quantity,
        'components' => $design['components'] ?? [],
        'stats' => $design['stats'] ?? [],
    ];
}

/** @param list<array<string,mixed>> $fleets */
function fuel074Find(array $fleets, string $id): ?array
{
    foreach ($fleets as $fleet) {
        if (is_array($fleet) && ($fleet['id'] ?? null) === $id) {
            return $fleet;
        }
    }
    return null;
}

$baseState = [
    'year' => 2400,
    'research' => ['1' => ['completed' => []]],
    'universe' => [
        'systems' => [],
        'routes' => [],
        'fleets' => [],
    ],
];
$baseState = TechnologyModelCatalog::normalizeState($baseState);
$base = TechnologyModelCatalog::currentDesign($baseState, 1);

$full = TechnologyModelCatalog::normalizeFleet([
    'id' => 'fuel-full',
    'ownerPlayerId' => 1,
    'systemId' => 'home',
    'name' => 'Fuel Full',
    'ships' => 40,
    'role' => 'Scout',
    'composition' => [fuel074Entry($base, 40)],
], $baseState);

checkFuel074(($full['fuelCapacity'] ?? 0) === 4000, '40 Scout Mk I ships should aggregate 4000 fuel capacity.');
checkFuel074(($full['fuel'] ?? -1) === 4000, 'A fleet without persisted fuel must initialize with a full tank for backwards compatibility.');
checkFuel074(($full['fuelUsePerHop'] ?? 0) === 1400, '40 Chemical Drive Mk I ships should use 1400 fuel per hop.');
checkFuel074(($full['operationalRange'] ?? -1) === 2, 'Full Scout Mk I fleet should have two-hop fuel endurance even though speed is one hop/turn.');

$partial = TechnologyModelCatalog::normalizeFleet([
    ...$full,
    'fuel' => 1399,
], $baseState);
checkFuel074(($partial['fuel'] ?? -1) === 1399, 'Persisted partial fuel must survive normalization.');
checkFuel074(($partial['operationalRange'] ?? -1) === 0, '1399 fuel is insufficient for one 1400-fuel hop.');

$optimizedState = $baseState;
$optimizedState['research']['1'] = ['completed' => ['propulsion_1', 'fuel_optimization_1']];
$optimized = TechnologyModelCatalog::normalizeFleet([
    ...$full,
    'fuel' => 4000,
], $optimizedState);
checkFuel074(($optimized['fuelUsePerHop'] ?? 0) === 1260, 'Applied fuel optimization must reduce existing Chemical Drive fleet consumption by 10%.');
checkFuel074(($optimized['operationalRange'] ?? -1) === 3, 'Fuel optimization should improve existing fleet endurance without changing its engine model.');

if (class_exists(DemoTurnEngine::class)) {
    $engine = new DemoTurnEngine();
    $share = new ReflectionMethod($engine, 'fuelShareForCapacity');
    $halfTank = TechnologyModelCatalog::normalizeFleet([
        ...$full,
        'fuel' => 2000,
    ], $baseState);
    $movedFuel = (int) $share->invoke($engine, $halfTank, 1000);
    checkFuel074($movedFuel === 500, 'Splitting 25% of a half-full fleet must move 25% of its fuel pool.');
}

if (class_exists(DemoTurnEngine::class) && class_exists(Game::class) && class_exists(Turn::class)) {
    $state = [
        'year' => 2400,
        'research' => ['1' => ['completed' => ['propulsion_1']]],
        'universe' => [
            'systems' => [
                ['id' => 'home', 'name' => 'Home', 'x' => 0, 'y' => 0, 'ownerPlayerId' => 1, 'population' => 5.0, 'capacity' => 10.0, 'happiness' => 75, 'security' => 60, 'development' => 50, 'defenses' => 0, 'sensorRange' => 1, 'resources' => [], 'production' => [], 'installations' => []],
                ['id' => 'mid', 'name' => 'Mid', 'x' => 10, 'y' => 0, 'ownerPlayerId' => null, 'population' => 0.0, 'capacity' => 5.0, 'happiness' => 0, 'security' => 0, 'development' => 0, 'defenses' => 0, 'sensorRange' => 0, 'resources' => [], 'production' => [], 'installations' => []],
                ['id' => 'far', 'name' => 'Far', 'x' => 20, 'y' => 0, 'ownerPlayerId' => null, 'population' => 0.0, 'capacity' => 5.0, 'happiness' => 0, 'security' => 0, 'development' => 0, 'defenses' => 0, 'sensorRange' => 0, 'resources' => [], 'production' => [], 'installations' => []],
            ],
            'routes' => [
                ['from' => 'home', 'to' => 'mid'],
                ['from' => 'mid', 'to' => 'far'],
            ],
            'fleets' => [],
        ],
    ];
    $state = TechnologyModelCatalog::normalizeState($state);
    $source = TechnologyModelCatalog::currentDesign($state, 1);
    $selection = [];
    foreach ($source['components'] as $component) {
        if (is_array($component) && is_string($component['category'] ?? null) && is_string($component['modelId'] ?? null)) {
            $selection[$component['category']] = $component['modelId'];
        }
    }
    $selection['engine'] = 'ion_drive_mk1';
    $ion = TechnologyModelCatalog::createDesign($state, 1, (string) $source['id'], 'Fuel Test Scout Mk II', $selection);
    $state = TechnologyModelCatalog::appendDesign($state, 1, $ion, 1);

    $state['universe']['fleets'] = [TechnologyModelCatalog::normalizeFleet([
        'id' => 'fuel-runner',
        'ownerPlayerId' => 1,
        'systemId' => 'home',
        'name' => 'Fuel Runner',
        'ships' => 40,
        'role' => 'Scout',
        'fuel' => 100,
        'composition' => [fuel074Entry($ion, 40)],
    ], $state)];

    $game = new Game('Fuel 0.7.4 smoke');
    $turn1 = new Turn($game, 1, $state, randomSeed: str_repeat('c', 64), rulesVersion: 'smoke-0.7.4');
    $result1 = (new DemoTurnEngine())->generate($turn1, [
        '1' => [
            'fleets' => [['fleetId' => 'fuel-runner', 'action' => 'move', 'targetSystemId' => 'far']],
            'production' => [],
            'research' => [],
            'designs' => [],
        ],
    ]);
    $fleet1 = fuel074Find(is_array($result1->nextState['universe']['fleets'] ?? null) ? $result1->nextState['universe']['fleets'] : [], 'fuel-runner');
    $report1 = is_array($result1->playerReports['1'] ?? null)
        ? $result1->playerReports['1']
        : (is_array($result1->playerReports[1] ?? null) ? $result1->playerReports[1] : []);

    checkFuel074(count(is_array($report1['fuel_refills'] ?? null) ? $report1['fuel_refills'] : []) === 1, 'Low-fuel fleet at own colony must automatically refuel before movement.');
    checkFuel074(($fleet1['systemId'] ?? '') === 'far', 'Refuelled Ion fleet should be able to move two hops in one turn.');
    checkFuel074(($fleet1['fuel'] ?? -1) === 1440, 'Two Ion-drive hops should consume 2560 of 4000 fuel.');
    checkFuel074(($fleet1['operationalRange'] ?? -1) === 1, 'After two Ion-drive hops, remaining fuel should permit one additional hop.');
    $movement = is_array($report1['movements'][0] ?? null) ? $report1['movements'][0] : [];
    checkFuel074(($movement['distance'] ?? 0) === 2 && ($movement['fuelUsed'] ?? 0) === 2560, 'Movement report must record exact distance and fuel use.');

    $turn2 = new Turn($game, 2, $result1->nextState, randomSeed: str_repeat('d', 64), rulesVersion: 'smoke-0.7.4');
    $result2 = (new DemoTurnEngine())->generate($turn2, [
        '1' => [
            'fleets' => [['fleetId' => 'fuel-runner', 'action' => 'move', 'targetSystemId' => 'home']],
            'production' => [],
            'research' => [],
            'designs' => [],
        ],
    ]);
    $fleet2 = fuel074Find(is_array($result2->nextState['universe']['fleets'] ?? null) ? $result2->nextState['universe']['fleets'] : [], 'fuel-runner');
    $report2 = is_array($result2->playerReports['1'] ?? null)
        ? $result2->playerReports['1']
        : (is_array($result2->playerReports[1] ?? null) ? $result2->playerReports[1] : []);

    checkFuel074(($fleet2['systemId'] ?? '') === 'far', 'Fleet outside owned colonies must not receive free fuel and must stay put when fuel is insufficient.');
    checkFuel074(count(is_array($report2['fuel_refills'] ?? null) ? $report2['fuel_refills'] : []) === 0, 'Unclaimed system must not refuel the fleet.');
    checkFuel074(count(is_array($report2['movements'] ?? null) ? $report2['movements'] : []) === 0, 'Insufficient-fuel order must not move the fleet.');
    checkFuel074(
        count(array_filter(
            is_array($report2['warnings'] ?? null) ? $report2['warnings'] : [],
            static fn (mixed $warning): bool => is_string($warning) && str_contains($warning, 'mangler brændstof'),
        )) === 1,
        'Insufficient fuel must create an explicit warning.',
    );
}

fwrite(STDOUT, "Fuel operations 0.7.4 smoke test OK\n");
