<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

final readonly class ResolvedAccountAccess
{
    public function __construct(
        public int $accountId,
        public string $email,
        public string $displayName,
        public bool $usesWebSession,
        public ?string $sessionToken = null,
    ) {
    }
}
