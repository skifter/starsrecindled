<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Enum;

enum PlayerTurnStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
}
