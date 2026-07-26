<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

use Bellcom\StarsTurnBundle\Entity\Turn;

final class DemoTurnEngine implements TurnEngineInterface
{
    public function generate(Turn $turn, array $submittedOrders): TurnGenerationResult
    {
        $state = $turn->getInitialState();
        $currentYear = (int) ($state['year'] ?? 2400);

        ksort($submittedOrders, SORT_STRING);

        $nextState = $state;
        $nextState['year'] = $currentYear + 1;
        $nextState['last_turn'] = $turn->getNumber();
        $nextState['rules_version'] = $turn->getRulesVersion();
        $nextState['seed'] = $turn->getRandomSeed();
        $nextState['submitted_orders'] = $submittedOrders;

        $reports = [];
        foreach ($submittedOrders as $playerId => $orders) {
            $reports[$playerId] = [
                'message' => 'Demo-engine: ordrerne blev registreret, men ingen rigtig 4X-logik er kørt.',
                'orders' => $orders,
            ];
        }

        return new TurnGenerationResult($nextState, $reports);
    }
}
