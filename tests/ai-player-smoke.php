<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root.'/vendor/autoload.php')) {
    require $root.'/vendor/autoload.php';
} else {
    require $root.'/src/Entity/Player.php';
    require $root.'/src/Domain/AiTurnPlanner.php';
}

use Bellcom\StarsTurnBundle\Domain\AiTurnPlanner;
use Bellcom\StarsTurnBundle\Entity\Player;

function checkAi(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$reflection = new ReflectionClass(Player::class);
/** @var Player $human */
$human = $reflection->newInstanceWithoutConstructor();
/** @var Player $ai */
$ai = $reflection->newInstanceWithoutConstructor();
$ai->configureAi('standard');

checkAi(!$human->isAi(), 'Human player must remain human');
checkAi($human->getControllerType() === 'human', 'Human controller type');
checkAi($human->getAiLevel() === null, 'Human AI level must be null');
checkAi($ai->isAi(), 'AI player must be marked as AI');
checkAi($ai->getControllerType() === 'ai', 'AI controller type');
checkAi($ai->getAiLevel() === 'standard', 'AI level');

$orders = AiTurnPlanner::plan($ai);
checkAi(array_keys($orders) === ['fleets', 'production', 'research', 'designs'], 'AI order envelope keys');
foreach ($orders as $ordersForSystem) {
    checkAi(is_array($ordersForSystem) && $ordersForSystem === [], 'Dev5 Standard AI must submit conservative empty orders');
}

$threw = false;
try {
    AiTurnPlanner::plan($human);
} catch (InvalidArgumentException) {
    $threw = true;
}
checkAi($threw, 'Planner must reject human players');

fwrite(STDOUT, "AI player smoke test OK\n");
