<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Enum;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
}
