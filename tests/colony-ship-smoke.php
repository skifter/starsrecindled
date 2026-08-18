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

function checkColony(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$state = [
    'research' => ['1' => ['completed' => []]],
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
$base = TechnologyModelCatalog::currentDesign($state, 1);
checkColony(($base['name'] ?? '') === 'Scout Mk I', 'Baseline design should remain Scout Mk I.');
checkColony(($base['batchSize'] ?? 0) === 40, 'Ordinary Scout should still build in batches of 40.');
checkColony(($base['stats']['colonizationCapacity'] ?? 0) === 0, 'Baseline Scout must not gain colonization capacity automatically.');

$catalog = TechnologyModelCatalog::publicForPlayer($state, 1);
$colonyModule = null;
foreach ($catalog['components'] as $component) {
    if (($component['id'] ?? null) === 'colony_module_mk1') {
        $colonyModule = $component;
        break;
    }
}
checkColony(is_array($colonyModule), 'Colony Module Mk I must be present in the component catalog.');
checkColony(($colonyModule['unlocked'] ?? false) === true, 'Colony Module Mk I should be available without research in dev6.');

$selection = [];
foreach ($base['components'] as $component) {
    if (is_array($component) && is_string($component['category'] ?? null) && is_string($component['modelId'] ?? null)) {
        $selection[$component['category']] = $component['modelId'];
    }
}
$selection['utility'] = 'colony_module_mk1';

$colonyDesign = TechnologyModelCatalog::createDesign($state, 1, (string) $base['id'], 'Colony Ship Mk II', $selection);
checkColony(($colonyDesign['stats']['colonizationCapacity'] ?? 0) === 1, 'Colony module should provide one colonization charge per ship.');
checkColony(($colonyDesign['batchSize'] ?? 0) === 1, 'Colony-capable design should build as one strategic ship.');
checkColony(($colonyDesign['industryCost'] ?? 0) === 480, 'Colony Ship Mk II should cost the exact component sum of 480 industry.');
checkColony(count(array_filter($colonyDesign['components'], static fn (array $component): bool => ($component['category'] ?? null) === 'utility')) === 1, 'Colony design must retain the exact utility component snapshot.');

$state = TechnologyModelCatalog::appendDesign($state, 1, $colonyDesign, 20);
$designs = TechnologyModelCatalog::playerDesigns($state, 1);
checkColony(count($designs) === 2, 'Scout and colony design must coexist.');
checkColony((TechnologyModelCatalog::currentDesign($state, 1)['id'] ?? '') === ($colonyDesign['id'] ?? ''), 'Explicitly created colony design should become current without mutating Scout Mk I.');

$fleet = TechnologyModelCatalog::normalizeFleet([
    'id' => 'colony-fleet',
    'ownerPlayerId' => 1,
    'ships' => 2,
    'composition' => [[
        'designId' => $colonyDesign['id'],
        'designName' => $colonyDesign['name'],
        'generation' => $colonyDesign['generation'],
        'quantity' => 2,
        'components' => $colonyDesign['components'],
        'stats' => $colonyDesign['stats'],
    ]],
], $state);
checkColony(($fleet['colonizationCapacity'] ?? 0) === 2, 'Two colony ships should normalize to COL 2.');

$afterOne = $fleet;
$afterOne['ships'] = 1;
$afterOne['composition'][0]['quantity'] = 1;
$afterOne = TechnologyModelCatalog::normalizeFleet($afterOne, $state);
checkColony(($afterOne['colonizationCapacity'] ?? 0) === 1, 'One remaining colony ship should normalize to COL 1.');

$badUtilityRejected = false;
try {
    TechnologyModelCatalog::createDesign($state, 1, (string) $colonyDesign['id'], 'Bad Utility', [
        ...$selection,
        'utility' => 'defense_grid_mk1',
    ]);
} catch (InvalidArgumentException) {
    $badUtilityRejected = true;
}
checkColony($badUtilityRejected, 'Installation models must not be accepted in the utility slot.');

if (class_exists(DemoTurnEngine::class)) {
    $engine = new DemoTurnEngine();
    $consume = new ReflectionMethod($engine, 'consumeColonyCapacity');

    $consumed = $consume->invoke($engine, $fleet);
    checkColony(is_array($consumed['fleet'] ?? null), 'Consuming one of two colony ships should leave the fleet in service.');
    checkColony(($consumed['fleet']['ships'] ?? 0) === 1, 'Successful colonization should consume exactly one component-backed colony ship.');
    checkColony(($consumed['fleet']['colonizationCapacity'] ?? 0) === 1, 'One colony charge should remain after consuming one of two colony ships.');
    checkColony(($consumed['shipConsumed'] ?? false) === true, 'Component-backed colonization must report that a ship was consumed.');

    $last = $consume->invoke($engine, $consumed['fleet']);
    checkColony(($last['fleet'] ?? 'not-null') === null, 'The fleet should disappear when its last colony ship is consumed.');

    $legacy = $consume->invoke($engine, ['id' => 'legacy', 'ships' => 40, 'role' => 'Exploration fleet', 'colonizationCapacity' => 1]);
    checkColony(is_array($legacy['fleet'] ?? null) && ($legacy['fleet']['ships'] ?? 0) === 40, 'Legacy starting colony module must remain backwards compatible and not consume a ship.');
    checkColony(($legacy['fleet']['colonizationCapacity'] ?? -1) === 0, 'Legacy starting colony module should still be single use.');
}

fwrite(STDOUT, "Colony ship smoke test OK\n");
