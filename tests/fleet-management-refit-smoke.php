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

function checkFleet073(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param array<string,mixed> $design @return array<string,mixed> */
function fleet073Entry(array $design, int $quantity): array
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
function fleet073Find(array $fleets, string $id): ?array
{
    foreach ($fleets as $fleet) {
        if (is_array($fleet) && ($fleet['id'] ?? null) === $id) {
            return $fleet;
        }
    }
    return null;
}

/** @param array<string,mixed> $fleet */
function fleet073CompositionQuantity(array $fleet, string $designId): int
{
    foreach (is_array($fleet['composition'] ?? null) ? $fleet['composition'] : [] as $entry) {
        if (is_array($entry) && ($entry['designId'] ?? null) === $designId) {
            return max(0, (int) ($entry['quantity'] ?? 0));
        }
    }
    return 0;
}

$state = [
    'year' => 2400,
    'research' => ['1' => ['completed' => ['propulsion_1']]],
    'universe' => [
        'systems' => [[
            'id' => 'home-1',
            'name' => 'Home',
            'x' => 10,
            'y' => 10,
            'ownerPlayerId' => 1,
            'population' => 5.0,
            'capacity' => 10.0,
            'happiness' => 75,
            'security' => 60,
            'development' => 50,
            'defenses' => 0,
            'sensorRange' => 1,
            'resources' => [
                ['id' => 'industry', 'label' => 'Industry', 'value' => 5000, 'income' => 0, 'icon' => 'industry'],
                ['id' => 'science', 'label' => 'Science', 'value' => 0, 'income' => 0, 'icon' => 'research'],
            ],
            'production' => [],
            'installations' => [],
        ]],
        'routes' => [],
        'fleets' => [],
    ],
];

$state = TechnologyModelCatalog::normalizeState($state);
$base = TechnologyModelCatalog::currentDesign($state, 1);
$selection = [];
foreach ($base['components'] as $component) {
    if (is_array($component) && is_string($component['category'] ?? null) && is_string($component['modelId'] ?? null)) {
        $selection[$component['category']] = $component['modelId'];
    }
}
$selection['engine'] = 'ion_drive_mk1';
$newer = TechnologyModelCatalog::createDesign($state, 1, (string) $base['id'], 'Scout Mk II Refit', $selection);
$state = TechnologyModelCatalog::appendDesign($state, 1, $newer, 1);

checkFleet073(($base['generation'] ?? 0) === 1, 'Smoke test requires Scout generation 1 as the source.');
checkFleet073(($newer['generation'] ?? 0) === 2, 'Refit target must be an explicit newer generation.');
checkFleet073(($newer['stats']['movementRange'] ?? 0) === 2, 'Ion target generation should have movement range 2.');

$legacyFleet = TechnologyModelCatalog::normalizeFleet([
    'id' => 'legacy-colony',
    'ownerPlayerId' => 1,
    'systemId' => 'home-1',
    'name' => 'Legacy Pathfinder',
    'ships' => 40,
    'role' => 'Exploration fleet',
    'colonizationCapacity' => 1,
    'composition' => [fleet073Entry($base, 40)],
], $state);
checkFleet073(($legacyFleet['legacyColonizationCapacity'] ?? -1) === 1, 'Legacy colony capacity must be tracked separately from component-backed COL capacity.');
checkFleet073(($legacyFleet['colonizationCapacity'] ?? -1) === 1, 'Fleet normalization must preserve the one legacy colony module.');

if (class_exists(DemoTurnEngine::class)) {
    $engine = new DemoTurnEngine();
    $costMethod = new ReflectionMethod($engine, 'refitCost');
    $refitCost = (int) $costMethod->invoke($engine, $base, $newer, 20);
    checkFleet073($refitCost > 0, 'Refit must have a positive industry cost.');
    checkFleet073($refitCost < (int) ($newer['industryCost'] ?? PHP_INT_MAX), 'Half-batch engine refit should cost less than a full new-build batch.');

    $refitting = TechnologyModelCatalog::normalizeFleet([
        'id' => 'helper-refit',
        'ownerPlayerId' => 1,
        'systemId' => 'home-1',
        'name' => 'Helper Refit',
        'ships' => 40,
        'role' => 'Scout',
        'legacyColonizationCapacity' => 1,
        'composition' => [fleet073Entry($base, 40)],
        'refit' => [
            'fromDesignId' => $base['id'],
            'fromDesignName' => $base['name'],
            'toDesignId' => $newer['id'],
            'toDesignName' => $newer['name'],
            'quantity' => 20,
            'industryCost' => $refitCost,
            'turnsTotal' => 2,
            'turnsRemaining' => 1,
            'startedTurn' => 2,
            'systemId' => 'home-1',
        ],
    ], $state);

    $advanceMethod = new ReflectionMethod($engine, 'advanceFleetRefit');
    $advanced = $advanceMethod->invoke($engine, $refitting, $state, 3);
    checkFleet073(is_array($advanced['completed'] ?? null), 'Due refit must produce a completion report.');
    checkFleet073(!isset($advanced['fleet']['refit']), 'Completed refit must clear its work state.');
    checkFleet073(($advanced['fleet']['ships'] ?? 0) === 40, 'Refit must never create or destroy ships.');
    checkFleet073(fleet073CompositionQuantity($advanced['fleet'], (string) $base['id']) === 20, 'Unrefitted source generation must remain in the fleet.');
    checkFleet073(fleet073CompositionQuantity($advanced['fleet'], (string) $newer['id']) === 20, 'Refitted ships must become the exact target generation.');
    checkFleet073(($advanced['fleet']['legacyColonizationCapacity'] ?? -1) === 1, 'Refit must not lose the legacy colony module.');
}

// When the real entities/autoloader are available, exercise all structural fleet
// actions through the public turn engine, not only through helper methods.
if (class_exists(DemoTurnEngine::class) && class_exists(Game::class) && class_exists(Turn::class)) {
    $fleet = static fn (string $id, string $name, int $ships): array => TechnologyModelCatalog::normalizeFleet([
        'id' => $id,
        'ownerPlayerId' => 1,
        'systemId' => 'home-1',
        'name' => $name,
        'ships' => $ships,
        'role' => 'Scout',
        'composition' => [fleet073Entry($base, $ships)],
    ], $state);

    $state['universe']['fleets'] = [
        $fleet('rename-a', 'Rename A', 8),
        $fleet('split-b', 'Split B', 10),
        $fleet('transfer-c', 'Transfer C', 10),
        $fleet('transfer-d', 'Transfer D', 5),
        $fleet('merge-e', 'Merge E', 7),
        $fleet('merge-f', 'Merge F', 4),
        $fleet('refit-g', 'Refit G', 40),
    ];

    $game = new Game('Fleet 0.7.3 smoke');
    $turn1 = new Turn($game, 1, $state, randomSeed: str_repeat('a', 64), rulesVersion: 'smoke-0.7.3');
    $result1 = (new DemoTurnEngine())->generate($turn1, [
        '1' => [
            'fleets' => [
                ['fleetId' => 'rename-a', 'action' => 'rename', 'name' => 'Renamed Fleet'],
                ['fleetId' => 'split-b', 'action' => 'split', 'designId' => $base['id'], 'quantity' => 3, 'name' => 'Split Detachment'],
                ['fleetId' => 'transfer-c', 'action' => 'transfer', 'targetFleetId' => 'transfer-d', 'designId' => $base['id'], 'quantity' => 3],
                ['fleetId' => 'merge-e', 'action' => 'merge', 'targetFleetId' => 'merge-f'],
                ['fleetId' => 'refit-g', 'action' => 'refit', 'designId' => $base['id'], 'targetDesignId' => $newer['id'], 'quantity' => 20],
            ],
            'production' => [],
            'research' => [],
            'designs' => [],
        ],
    ]);

    $next1 = $result1->nextState;
    $report1 = is_array($result1->playerReports['1'] ?? null)
        ? $result1->playerReports['1']
        : (is_array($result1->playerReports[1] ?? null) ? $result1->playerReports[1] : []);
    $fleets1 = is_array($next1['universe']['fleets'] ?? null) ? $next1['universe']['fleets'] : [];

    checkFleet073(count(is_array($report1['fleet_actions'] ?? null) ? $report1['fleet_actions'] : []) === 4, 'Rename, split, transfer and merge should all be reported.');
    checkFleet073(count(is_array($report1['refits_started'] ?? null) ? $report1['refits_started'] : []) === 1, 'Refit start should be reported once.');
    checkFleet073((fleet073Find($fleets1, 'rename-a')['name'] ?? '') === 'Renamed Fleet', 'Rename must persist into next state.');
    checkFleet073((fleet073Find($fleets1, 'split-b')['ships'] ?? 0) === 7, 'Split must reduce the source fleet by the exact quantity.');
    $splitFleet = null;
    foreach ($fleets1 as $candidate) {
        if (is_array($candidate) && str_starts_with((string) ($candidate['id'] ?? ''), 'fleet-1-split-')) {
            $splitFleet = $candidate;
            break;
        }
    }
    checkFleet073(is_array($splitFleet) && ($splitFleet['ships'] ?? 0) === 3, 'Split must create a new three-ship fleet.');
    checkFleet073((fleet073Find($fleets1, 'transfer-c')['ships'] ?? 0) === 7, 'Transfer must subtract ships from source.');
    checkFleet073((fleet073Find($fleets1, 'transfer-d')['ships'] ?? 0) === 8, 'Transfer must add ships to target.');
    checkFleet073(fleet073Find($fleets1, 'merge-e') === null, 'Merge must remove the source fleet.');
    checkFleet073((fleet073Find($fleets1, 'merge-f')['ships'] ?? 0) === 11, 'Merge must preserve all ships in the target fleet.');
    checkFleet073(is_array(fleet073Find($fleets1, 'refit-g')['refit'] ?? null), 'Refit should remain queued/in progress after its start turn.');
    checkFleet073(fleet073CompositionQuantity(fleet073Find($fleets1, 'refit-g') ?? [], (string) $base['id']) === 40, 'Old hardware must remain installed while refit is in progress.');

    $turn2 = new Turn($game, 2, $next1, randomSeed: str_repeat('b', 64), rulesVersion: 'smoke-0.7.3');
    $result2 = (new DemoTurnEngine())->generate($turn2, [
        '1' => ['fleets' => [], 'production' => [], 'research' => [], 'designs' => []],
    ]);
    $next2 = $result2->nextState;
    $report2 = is_array($result2->playerReports['1'] ?? null)
        ? $result2->playerReports['1']
        : (is_array($result2->playerReports[1] ?? null) ? $result2->playerReports[1] : []);
    $refitted = fleet073Find(is_array($next2['universe']['fleets'] ?? null) ? $next2['universe']['fleets'] : [], 'refit-g');

    checkFleet073(count(is_array($report2['refits_completed'] ?? null) ? $report2['refits_completed'] : []) === 1, 'Second processing cycle must report refit completion.');
    checkFleet073(is_array($refitted) && !isset($refitted['refit']), 'Completed fleet must no longer be locked by refit.');
    checkFleet073(fleet073CompositionQuantity($refitted ?? [], (string) $base['id']) === 20, 'Twenty source-generation ships must remain after partial refit.');
    checkFleet073(fleet073CompositionQuantity($refitted ?? [], (string) $newer['id']) === 20, 'Twenty ships must become the newer generation after refit completes.');
    checkFleet073(($refitted['ships'] ?? 0) === 40, 'Completed refit must preserve total ship count.');
}

fwrite(STDOUT, "Fleet management/refit 0.7.3 smoke test OK\n");
