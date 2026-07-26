<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Application;

final readonly class SubmissionOutcome
{
    public function __construct(
        public bool $allPlayersSubmitted,
        public int $submittedPlayers,
        public int $totalPlayers,
    ) {
    }
}
