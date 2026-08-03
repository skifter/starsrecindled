<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

final class AccountAccessException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 401)
    {
        parent::__construct($message);
    }
}
