<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Tests\Domain;

use Bellcom\StarsTurnBundle\Domain\TurnGenerationResult;
use Bellcom\StarsTurnBundle\Entity\Game;
use Bellcom\StarsTurnBundle\Entity\Turn;
use Bellcom\StarsTurnBundle\Enum\TurnStatus;
use PHPUnit\Framework\TestCase;

final class TurnLifecycleTest extends TestCase
{
    public function testTurnMovesThroughQueueGenerationAndPublication(): void
    {
        $turn = new Turn(new Game('Test'), 1, ['year' => 2400]);

        $turn->queue();
        self::assertSame(TurnStatus::QUEUED, $turn->getStatus());

        $turn->beginGeneration();
        self::assertSame(TurnStatus::GENERATING, $turn->getStatus());

        $turn->publish(new TurnGenerationResult(['year' => 2401]));
        self::assertSame(TurnStatus::PUBLISHED, $turn->getStatus());
        self::assertSame(['year' => 2401], $turn->getResultState());
    }
}
