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
use Bellcom\StarsTurnBundle\Domain\ResearchCatalog;
use Bellcom\StarsTurnBundle\Domain\TechnologyModelCatalog;
use Bellcom\StarsTurnBundle\Entity\Game;
use Bellcom\StarsTurnBundle\Entity\Turn;

function checkResource075(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param array<string,mixed> $system */
function resource075Value(array $system, string $id, string $field = 'income'): int
{
    foreach (is_array($system['resources'] ?? null) ? $system['resources'] : [] as $resource) {
        if (is_array($resource) && ($resource['id'] ?? null) === $id) {
            return (int) ($resource[$field] ?? 0);
        }
    }
    return 0;
}

$state = [
    'year' => 2400,
    'research' => ['1' => ['completed' => []]],
    'universe' => [
        'systems' => [[
            'id' => 'economy-home', 'name' => 'Economy Home', 'x' => 20, 'y' => 20,
            'ownerPlayerId' => 1, 'population' => 4.0, 'capacity' => 8.0,
            'happiness' => 70, 'security' => 50, 'development' => 10,
            'defenses' => 0, 'sensorRange' => 1, 'isCapital' => false,
            'resources' => [
                ['id'=>'industry','label'=>'Industry','value'=>1000,'income'=>999,'icon'=>'industry'],
                ['id'=>'science','label'=>'Science','value'=>100,'income'=>999,'icon'=>'research'],
                ['id'=>'bio','label'=>'Biomass','value'=>100,'income'=>999,'icon'=>'planet'],
                ['id'=>'energy','label'=>'Energy','value'=>100,'income'=>999,'icon'=>'energy'],
            ],
            'production' => [], 'installations' => [],
        ]],
        'routes' => [], 'fleets' => [],
    ],
];

$normalized = TechnologyModelCatalog::normalizeState($state);
$system = $normalized['universe']['systems'][0];
checkResource075(is_array($system['resourcePotential'] ?? null), 'Every system must get deterministic natural resource potential.');
checkResource075(isset($system['resourcePotential']['industry'], $system['resourcePotential']['energy'], $system['resourcePotential']['bio'], $system['resourcePotential']['science']), 'All four natural potential values must exist.');
checkResource075(is_array($system['asteroids'] ?? null) && array_key_exists('present', $system['asteroids']), 'Every system must get deterministic asteroid survey data.');
checkResource075(resource075Value($system, 'industry') === 2, 'A non-capital colony without extraction hardware should only get bootstrap industry, not legacy free income.');
checkResource075(resource075Value($system, 'science') === 0, 'A colony without a Research Complex should not get hidden science extraction.');
checkResource075(ResearchCatalog::estimateIncome($normalized, 1) === 1, 'Research income may retain the small development contribution, but must not restore the old missing-science fallback.');

$mining = TechnologyModelCatalog::model('mining_complex_mk1');
$power = TechnologyModelCatalog::model('power_plant_mk1');
$hydro = TechnologyModelCatalog::model('hydroponics_mk1');
$research = TechnologyModelCatalog::model('research_complex_mk1');
checkResource075(is_array($mining) && is_array($power) && is_array($hydro) && is_array($research), 'All Mk I planetary extraction installations must exist.');

$withMining = TechnologyModelCatalog::install($system, $mining, 1);
checkResource075(resource075Value($withMining, 'industry') > resource075Value($system, 'industry'), 'Mining Complex Mk I must increase industry output based on mineral potential.');
$withPower = TechnologyModelCatalog::install($withMining, $power, 1);
checkResource075(resource075Value($withPower, 'energy') > resource075Value($system, 'energy'), 'Power Plant Mk I must increase energy output.');
$withHydro = TechnologyModelCatalog::install($withPower, $hydro, 1);
checkResource075(resource075Value($withHydro, 'bio') > resource075Value($system, 'bio'), 'Hydroponics Mk I must increase biomass output.');
$withResearch = TechnologyModelCatalog::install($withHydro, $research, 1);
checkResource075(resource075Value($withResearch, 'science') > resource075Value($system, 'science'), 'Research Complex Mk I must increase science output.');

$mining2 = TechnologyModelCatalog::model('mining_complex_mk2');
checkResource075(is_array($mining2), 'Mining Complex Mk II must exist.');
$upgraded = TechnologyModelCatalog::upgradeInstallation($withResearch, $mining2, 2);
checkResource075(resource075Value($upgraded, 'industry') > resource075Value($withResearch, 'industry'), 'Explicit Mining Complex Mk I -> Mk II upgrade must improve output.');

$asteroidSystem = null;
for ($i = 1; $i <= 80; ++$i) {
    $candidate = TechnologyModelCatalog::normalizeSystemEconomy([
        'id' => 'asteroid-smoke-'.$i, 'name' => 'Asteroid Smoke '.$i,
        'ownerPlayerId' => 1, 'population' => 2.0, 'capacity' => 6.0,
        'development' => 20, 'isCapital' => false, 'resources' => [], 'installations' => [],
    ]);
    if (($candidate['asteroids']['present'] ?? false) === true) {
        $asteroidSystem = $candidate;
        break;
    }
}
checkResource075(is_array($asteroidSystem), 'Deterministic generator should expose asteroid deposits in the smoke sample.');
$asteroidStation = TechnologyModelCatalog::model('asteroid_mining_mk1');
$asteroidMined = TechnologyModelCatalog::install($asteroidSystem, $asteroidStation, 1);
checkResource075((int) ($asteroidMined['resourceOutput']['industry']['asteroid'] ?? 0) > 0, 'Asteroid Mining Station Mk I must produce asteroid industry on a system with a deposit.');

$galaxySource = @file_get_contents($root.'/frontend/src/lib/components/GalaxyMap.svelte');
if (is_string($galaxySource)) {
    checkResource075(str_contains($galaxySource, 'function clampPan('), 'Galaxy map must clamp panning to world bounds.');
    checkResource075(str_contains($galaxySource, 'Math.min(4, Math.round(system.sensorRange ?? 1))'), 'Galaxy map must support Deep Space Array sensor range 4.');
    checkResource075(str_contains($galaxySource, 'miniViewportX'), 'Mini galaxy viewport must derive from the clamped main camera.');
    checkResource075(str_contains($galaxySource, 'class:active={showSensorLayer} class="mini-sensor-territory"'), 'Mini galaxy must always retain the real sensor territory footprint.');
}

$engineSource = @file_get_contents($root.'/src/Domain/DemoTurnEngine.php');
if (is_string($engineSource)) {
    checkResource075(str_contains($engineSource, "'resource_income' => \$resourceIncome"), 'Turn report payload must contain per-colony resource income.');
    checkResource075(str_contains($engineSource, "\$definitionFamily === 'asteroid_mining'"), 'Engine must reject asteroid mining where no deposit exists.');
}

if (class_exists(DemoTurnEngine::class) && class_exists(Game::class) && class_exists(Turn::class)) {
    $engineState = $normalized;
    $engineState['universe']['systems'][0] = $withResearch;
    $beforeIndustry = resource075Value($withResearch, 'industry', 'value');
    $expectedIndustry = resource075Value($withResearch, 'industry', 'income');
    $game = new Game('Resource 0.7.5 smoke');
    $turn = new Turn($game, 1, $engineState, randomSeed: str_repeat('e', 64), rulesVersion: 'smoke-0.7.5');
    $result = (new DemoTurnEngine())->generate($turn, [
        '1' => ['fleets'=>[], 'production'=>[], 'research'=>[], 'designs'=>[]],
    ]);
    $nextSystem = $result->nextState['universe']['systems'][0] ?? [];
    checkResource075(resource075Value($nextSystem, 'industry', 'value') === $beforeIndustry + $expectedIndustry, 'Turn processing must add the computed extraction income exactly once.');
    $report = is_array($result->playerReports['1'] ?? null) ? $result->playerReports['1'] : (is_array($result->playerReports[1] ?? null) ? $result->playerReports[1] : []);
    checkResource075(count(is_array($report['resource_income'] ?? null) ? $report['resource_income'] : []) === 1, 'Player turn report must include one resource-income row per owned colony.');
}

fwrite(STDOUT, "Resource economy and galaxy polish 0.7.5 smoke test OK\n");
