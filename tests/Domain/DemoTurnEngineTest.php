<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Tests\Domain;

use Bellcom\StarsTurnBundle\Domain\DemoTurnEngine;
use Bellcom\StarsTurnBundle\Entity\Game;
use Bellcom\StarsTurnBundle\Entity\Turn;
use PHPUnit\Framework\TestCase;

final class DemoTurnEngineTest extends TestCase
{
    public function testItProducesDeterministicNextState(): void
    {
        $game = new Game('Test');
        $turn = new Turn($game, 1, ['year' => 2400], str_repeat('a', 64), 'test-rules');
        $engine = new DemoTurnEngine();
        $orders = [
            '2' => ['move' => 'B'],
            '1' => ['move' => 'A'],
        ];

        $first = $engine->generate($turn, $orders);
        $second = $engine->generate($turn, $orders);

        self::assertSame($first->nextState, $second->nextState);
        self::assertSame(2401, $first->nextState['year']);
        self::assertSame(['1', '2'], array_keys($first->nextState['submitted_orders']));
    }
}
