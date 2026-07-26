<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Message;

final readonly class GenerateTurnMessage
{
    public function __construct(public int $turnId)
    {
    }
}
