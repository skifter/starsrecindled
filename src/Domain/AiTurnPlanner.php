<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

use Bellcom\StarsTurnBundle\Entity\Player;
final class AiTurnPlanner
{
    /** @return array<string, mixed> */
    public static function plan(Player $player): array
    {
        if (!$player->isAi()) {
            throw new \InvalidArgumentException('AiTurnPlanner kan kun planlægge for AI-spillere.');
        }

        if ($player->getAiLevel() !== 'standard') {
            throw new \InvalidArgumentException(sprintf(
                'Ukendt AI-niveau: %s',
                $player->getAiLevel() ?? '<none>',
            ));
        }

        // Dev5 is intentionally conservative: the AI is a real player seat and
        // submits a valid order envelope, but it does not yet make strategic
        // choices. This keeps multiplayer turn processing deterministic while
        // production/refit/combat rules are still being implemented.
        return [
            'fleets' => [],
            'production' => [],
            'research' => [],
            'designs' => [],
        ];
    }
}
