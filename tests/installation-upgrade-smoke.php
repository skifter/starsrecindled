<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root.'/vendor/autoload.php')) {
    require $root.'/vendor/autoload.php';
} else {
    require $root.'/src/Domain/ResearchCatalog.php';
    require $root.'/src/Domain/TechnologyModelCatalog.php';
}

use Bellcom\StarsTurnBundle\Domain\TechnologyModelCatalog;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$state = [
    'research' => [
        '1' => [
            'completed' => ['defenses_1', 'industry_1', 'sensors_1'],
        ],
    ],
];

check(
    TechnologyModelCatalog::installationUpgradeDefinition($state, 1, 'defense_grid_mk1', 'defense_grid_mk2') !== null,
    'Defense Grid Mk II should be unlocked after defenses_1.'
);
check(
    TechnologyModelCatalog::installationUpgradeDefinition($state, 1, 'defense_grid_mk1', 'defense_grid_mk3') === null,
    'Sequential upgrades must not skip Defense Grid Mk II.'
);

$defenseSystem = [
    'id' => 'alpha',
    'defenses' => 500,
    'installations' => [[
        'family' => 'defense_grid',
        'modelId' => 'defense_grid_mk1',
        'name' => 'Defense Grid Mk I',
        'version' => 1,
        'installedTurn' => 1,
    ]],
];
$defenseTarget = TechnologyModelCatalog::model('defense_grid_mk2');
check(is_array($defenseTarget), 'Defense Grid Mk II model is missing.');

$started = TechnologyModelCatalog::startInstallationUpgrade($defenseSystem, $defenseTarget, 10);
check(($started['installations'][0]['modelId'] ?? '') === 'defense_grid_mk1', 'Mk I must remain active while the upgrade is in progress.');
check(($started['installationUpgrades'][0]['toModelId'] ?? '') === 'defense_grid_mk2', 'Pending upgrade target was not stored.');
check(($started['installationUpgrades'][0]['turnsRemaining'] ?? 0) === 1, 'A two-turn upgrade should have one turn remaining after its start turn.');

$advanced = TechnologyModelCatalog::advanceInstallationUpgrades($started, 11);
$completedSystem = $advanced['system'];
check(($completedSystem['installations'][0]['modelId'] ?? '') === 'defense_grid_mk2', 'Defense Grid Mk II did not replace Mk I on completion.');
check(($completedSystem['defenses'] ?? 0) === 600, 'Defense upgrade should add only the Mk II minus Mk I defense delta.');
check(count($advanced['completed']) === 1, 'Exactly one defense upgrade completion event was expected.');
check(($advanced['completed'][0]['industryCost'] ?? 0) === 190, 'Defense upgrade completion should retain the 190 industry cost.');

$factorySystem = [
    'id' => 'beta',
    'development' => 40,
    'resources' => [[
        'id' => 'industry',
        'label' => 'Industry',
        'value' => 500,
        'income' => 20,
        'icon' => 'industry',
    ]],
    'installations' => [[
        'family' => 'orbital_factory',
        'modelId' => 'orbital_factory_mk1',
        'name' => 'Orbital Factory Mk I',
        'version' => 1,
        'installedTurn' => 1,
    ]],
];
$factoryTarget = TechnologyModelCatalog::model('orbital_factory_mk2');
check(is_array($factoryTarget), 'Orbital Factory Mk II model is missing.');
$factoryDone = TechnologyModelCatalog::upgradeInstallation($factorySystem, $factoryTarget, 11);
check(($factoryDone['development'] ?? 0) === 42, 'Factory upgrade should add only the +2 development delta.');
check(($factoryDone['resources'][0]['income'] ?? 0) === 23, 'Factory upgrade should add only the +3 industry-income delta.');

$arraySystem = [
    'id' => 'gamma',
    'sensorRange' => 2,
    'installations' => [[
        'family' => 'deep_space_array',
        'modelId' => 'deep_space_array_mk1',
        'name' => 'Deep Space Array Mk I',
        'version' => 1,
        'installedTurn' => 1,
    ]],
];
$arrayTarget = TechnologyModelCatalog::model('deep_space_array_mk2');
check(is_array($arrayTarget), 'Deep Space Array Mk II model is missing.');
$arrayDone = TechnologyModelCatalog::upgradeInstallation($arraySystem, $arrayTarget, 11);
check(($arrayDone['sensorRange'] ?? 0) === 3, 'Deep Space Array Mk II should raise sensor range to 3.');

$skipTarget = TechnologyModelCatalog::model('orbital_factory_mk3');
check(is_array($skipTarget), 'Orbital Factory Mk III model is missing.');
$skipAttempt = TechnologyModelCatalog::startInstallationUpgrade($factorySystem, $skipTarget, 10);
check(!isset($skipAttempt['installationUpgrades']), 'Mk I -> Mk III must not start directly.');

fwrite(STDOUT, "Installation upgrade smoke test OK\n");
