<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

use Bellcom\StarsTurnBundle\Entity\Turn;

interface TurnEngineInterface
{
    /**
     * @param array<string, array<string, mixed>> $submittedOrders Indexed by player-id as string.
     */
    public function generate(Turn $turn, array $submittedOrders): TurnGenerationResult;
}
