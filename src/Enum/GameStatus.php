<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Enum;

enum GameStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case FINISHED = 'finished';
}
