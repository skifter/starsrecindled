<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

final readonly class TurnGenerationResult
{
    /**
     * @param array<string, mixed> $nextState
     * @param array<string, mixed> $playerReports Indexed by player-id as string.
     */
    public function __construct(
        public array $nextState,
        public array $playerReports = [],
    ) {
    }
}
