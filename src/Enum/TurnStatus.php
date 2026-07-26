<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Enum;

enum TurnStatus: string
{
    case OPEN = 'open';
    case QUEUED = 'queued';
    case GENERATING = 'generating';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}
