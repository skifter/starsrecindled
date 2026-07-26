<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Message;

final readonly class SendNotificationMessage
{
    public function __construct(public int $deliveryId)
    {
    }
}
