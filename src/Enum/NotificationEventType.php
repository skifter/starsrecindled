<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Enum;

enum NotificationEventType: string
{
    case ALL_PLAYERS_SUBMITTED = 'all_players_submitted';
    case TURN_PUBLISHED = 'turn_published';
}
