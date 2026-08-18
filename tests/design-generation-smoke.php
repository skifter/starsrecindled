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

function checkDesign(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$state = [
    'research' => [
        '1' => [
            'completed' => ['propulsion_1', 'sensors_1', 'weapons_1', 'defenses_1'],
        ],
    ],
    'universe' => [
        'systems' => [[
            'id' => 'home-1',
            'ownerPlayerId' => 1,
            'development' => 10,
            'defenses' => 0,
            'sensorRange' => 1,
        ]],
        'fleets' => [],
    ],
];

$state = TechnologyModelCatalog::normalizeState($state);
$designs = TechnologyModelCatalog::playerDesigns($state, 1);
checkDesign(count($designs) === 1, 'Completed research must not auto-create a ship generation.');
checkDesign(($designs[0]['name'] ?? '') === 'Scout Mk I', 'Baseline design should be Scout Mk I.');
checkDesign(($designs[0]['stats']['movementRange'] ?? 0) === 1, 'Baseline Scout must keep its Mk I engine after research unlocks Mk II.');

$baseId = (string) $designs[0]['id'];
$selection = [
    'hull' => 'scout_hull_mk1',
    'engine' => 'ion_drive_mk1',
    'scanner' => 'survey_scanner_mk2',
    'weapon' => 'beam_emitter_mk2',
    'armor' => 'reinforced_armor_mk2',
];

$mk2 = TechnologyModelCatalog::createDesign($state, 1, $baseId, 'Scout Mk II', $selection);
checkDesign(($mk2['generation'] ?? 0) === 2, 'Explicit clone should create generation 2.');
checkDesign(($mk2['stats']['movementRange'] ?? 0) === 2, 'Ion Drive Mk II should give movement range 2.');
checkDesign(($mk2['stats']['sensorRange'] ?? 0) === 2, 'Survey Scanner Mk II should give sensor range 2.');
checkDesign(($mk2['stats']['attack'] ?? 0) === 4, 'Beam Emitter Mk II should give attack 4.');
checkDesign(($mk2['stats']['defense'] ?? 0) === 4, 'Reinforced Armor Mk II should give defense 4.');
checkDesign(($mk2['basedOnDesignId'] ?? '') === $baseId, 'Design lineage should retain the base design id.');

$state = TechnologyModelCatalog::appendDesign($state, 1, $mk2, 12);
$designs = TechnologyModelCatalog::playerDesigns($state, 1);
checkDesign(count($designs) === 2, 'The new generation should persist alongside the old one.');
$current = TechnologyModelCatalog::currentDesign($state, 1);
checkDesign(($current['id'] ?? '') === ($mk2['id'] ?? ''), 'New generation should become the current new-build design.');
checkDesign(($designs[0]['current'] ?? true) === false, 'Old generation must stop being current without being mutated.');
checkDesign(($designs[0]['stats']['movementRange'] ?? 0) === 1, 'Old Scout Mk I stats must remain unchanged.');

$duplicateRejected = false;
try {
    TechnologyModelCatalog::createDesign($state, 1, (string) $mk2['id'], 'Scout Mk III duplicate', $selection);
} catch (InvalidArgumentException) {
    $duplicateRejected = true;
}
checkDesign($duplicateRejected, 'An identical component snapshot must not create a duplicate generation.');

$lockedRejected = false;
try {
    TechnologyModelCatalog::createDesign($state, 1, (string) $mk2['id'], 'Scout Mk III', [
        ...$selection,
        'engine' => 'fusion_drive_mk1',
    ]);
} catch (InvalidArgumentException) {
    $lockedRejected = true;
}
checkDesign($lockedRejected, 'Locked component models must be rejected by the server.');

fwrite(STDOUT, "Design generation smoke test OK\n");
