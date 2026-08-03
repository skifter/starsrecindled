<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class AccountAccessService
{
    public const COOKIE_NAME = 'stars_account_session';
    public const SESSION_TTL = 'P30D';

    public function __construct(
        private Connection $connection,
        private ParameterBagInterface $parameters,
    ) {
    }

    public function resolve(Request $request): ResolvedAccountAccess
    {
        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return $this->resolveDirectToken(trim($matches[1]));
        }

        $sessionToken = trim((string) $request->cookies->get(self::COOKIE_NAME, ''));
        if ($sessionToken === '') {
            throw new AccountAccessException('Account login is required.');
        }

        $session = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT s.id AS session_id, s.account_id, s.expires_at, a.email, a.display_name
FROM stars_account_session s
INNER JOIN stars_account a ON a.id = s.account_id
WHERE s.token_hash = :tokenHash
SQL,
            ['tokenHash' => hash('sha256', $sessionToken)],
        );

        if ($session === false) {
            throw new AccountAccessException('The account session is invalid.');
        }

        $expiresAt = new DateTimeImmutable((string) $session['expires_at']);
        if ($expiresAt <= new DateTimeImmutable()) {
            $this->connection->delete('stars_account_session', ['id' => (int) $session['session_id']]);
            throw new AccountAccessException('The account session has expired.');
        }

        $this->connection->update(
            'stars_account_session',
            ['last_used_at' => $this->now()],
            ['id' => (int) $session['session_id']],
        );

        return new ResolvedAccountAccess(
            (int) $session['account_id'],
            (string) $session['email'],
            (string) $session['display_name'],
            true,
            $sessionToken,
        );
    }

    /** @return array{token:string,expiresAt:DateTimeImmutable} */
    public function createWebSession(int $accountId): array
    {
        $token = $this->randomToken('srs_');
        $now = new DateTimeImmutable();
        $expiresAt = $now->add(new DateInterval(self::SESSION_TTL));

        $this->connection->insert('stars_account_session', [
            'account_id' => $accountId,
            'token_hash' => hash('sha256', $token),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'last_used_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    public function revokeWebSession(Request $request): void
    {
        $token = trim((string) $request->cookies->get(self::COOKIE_NAME, ''));
        if ($token !== '') {
            $this->connection->delete('stars_account_session', ['token_hash' => hash('sha256', $token)]);
        }
    }

    public function csrfToken(ResolvedAccountAccess $access): string
    {
        if (!$access->usesWebSession || $access->sessionToken === null) {
            return '';
        }

        return hash_hmac('sha256', $access->sessionToken, $this->kernelSecret());
    }

    public function requireWebWrite(Request $request, ResolvedAccountAccess $access): void
    {
        if (!$access->usesWebSession) {
            throw new AccountAccessException('This operation requires email and password login.', 403);
        }

        $supplied = trim((string) $request->headers->get('X-Stars-CSRF', ''));
        if ($supplied === '' || !hash_equals($this->csrfToken($access), $supplied)) {
            throw new AccountAccessException('The request security token is invalid.', 403);
        }
    }

    public function randomClientToken(): string
    {
        return $this->randomToken('srk_');
    }

    private function resolveDirectToken(string $token): ResolvedAccountAccess
    {
        if ($token === '') {
            throw new AccountAccessException('A client token is required.');
        }

        $account = $this->connection->fetchAssociative(
            'SELECT id, email, display_name FROM stars_account WHERE client_token_hash = :tokenHash',
            ['tokenHash' => hash('sha256', $token)],
        );

        if ($account === false) {
            throw new AccountAccessException('The client token is invalid.');
        }

        return new ResolvedAccountAccess(
            (int) $account['id'],
            (string) $account['email'],
            (string) $account['display_name'],
            false,
        );
    }

    private function randomToken(string $prefix): string
    {
        return $prefix.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function kernelSecret(): string
    {
        $secret = (string) $this->parameters->get('kernel.secret');
        if ($secret === '') {
            throw new \RuntimeException('kernel.secret must be configured.');
        }

        return $secret;
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
