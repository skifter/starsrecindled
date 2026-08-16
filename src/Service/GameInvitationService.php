<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

final readonly class GameInvitationService
{
    public function __construct(
        private Connection $connection,
        private AccountCredentialVault $vault,
        private MailerInterface $mailer,
    ) {
    }

    /** @return array{created:int,emailed:int,failed:int,pending:int} */
    public function reconcile(): array
    {
        $created = 0;
        $emailed = 0;
        $failed = 0;
        $emailColumn = $this->playerEmailColumn();
        $quotedEmail = $this->connection->getDatabasePlatform()->quoteSingleIdentifier($emailColumn);

        $players = $this->connection->fetchAllAssociative(sprintf(
            <<<'SQL'
SELECT p.id, p.game_id, p.%s AS invitation_email
FROM stars_player p
LEFT JOIN stars_account_game_access a ON a.player_id = p.id
LEFT JOIN stars_game_invitation i ON i.player_id = p.id
WHERE a.id IS NULL
  AND i.id IS NULL
  AND p.%s IS NOT NULL
  AND TRIM(p.%s) <> ''
ORDER BY p.game_id, p.id
SQL,
            $quotedEmail,
            $quotedEmail,
            $quotedEmail,
        ));

        foreach ($players as $player) {
            $email = mb_strtolower(trim((string) $player['invitation_email']));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $linkToken = $this->randomInvitationToken();
            $this->connection->insert('stars_game_invitation', [
                'game_id' => (int) $player['game_id'],
                'player_id' => (int) $player['id'],
                'email' => $email,
                'link_token_hash' => hash('sha256', $linkToken),
                'link_token_ciphertext' => $this->vault->encrypt($linkToken),
                'created_at' => $this->now(),
                'emailed_at' => null,
                'accepted_at' => null,
                'accepted_account_id' => null,
                'last_error' => null,
            ]);
            ++$created;
        }

        $pending = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT i.id, i.game_id, i.player_id, i.email, i.link_token_ciphertext
FROM stars_game_invitation i
LEFT JOIN stars_account_game_access a ON a.player_id = i.player_id
WHERE i.accepted_at IS NULL
  AND i.emailed_at IS NULL
  AND a.id IS NULL
ORDER BY i.id
SQL,
        );

        foreach ($pending as $invitation) {
            try {
                $linkToken = $this->vault->decrypt((string) $invitation['link_token_ciphertext']);
                $this->sendInvitationEmail(
                    (string) $invitation['email'],
                    (int) $invitation['game_id'],
                    (int) $invitation['player_id'],
                    $linkToken,
                );
                $this->connection->update('stars_game_invitation', [
                    'emailed_at' => $this->now(),
                    'last_error' => null,
                ], ['id' => (int) $invitation['id']]);
                ++$emailed;
            } catch (Throwable $exception) {
                $this->connection->update('stars_game_invitation', [
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                ], ['id' => (int) $invitation['id']]);
                ++$failed;
            }
        }

        return [
            'created' => $created,
            'emailed' => $emailed,
            'failed' => $failed,
            'pending' => count($pending),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function pendingForAccount(int $accountId, string $accountEmail): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT i.id, i.game_id, i.player_id, i.created_at, i.emailed_at
FROM stars_game_invitation i
LEFT JOIN stars_account_game_access a ON a.player_id = i.player_id
WHERE LOWER(i.email) = LOWER(:email)
  AND i.accepted_at IS NULL
  AND a.id IS NULL
ORDER BY i.created_at, i.id
SQL,
            ['email' => $accountEmail],
        );

        $result = [];
        foreach ($rows as $row) {
            $gameId = (int) $row['game_id'];
            $playerId = (int) $row['player_id'];
            $result[] = [
                'id' => (int) $row['id'],
                'gameId' => $gameId,
                'playerId' => $playerId,
                'label' => $this->gameLabel($gameId),
                'playerLabel' => $this->playerLabel($playerId),
                'createdAt' => (new DateTimeImmutable((string) $row['created_at']))->format(DATE_ATOM),
                'emailedAt' => $row['emailed_at'] !== null
                    ? (new DateTimeImmutable((string) $row['emailed_at']))->format(DATE_ATOM)
                    : null,
            ];
        }

        return $result;
    }

    public function acceptById(int $accountId, string $accountEmail, int $invitationId): void
    {
        $invitation = $this->connection->fetchAssociative(
            'SELECT * FROM stars_game_invitation WHERE id = :id',
            ['id' => $invitationId],
        );
        if ($invitation === false) {
            throw new AccountAccessException('The invitation was not found.', 404);
        }

        $this->accept($accountId, $accountEmail, $invitation);
    }

    public function acceptByLink(int $accountId, string $accountEmail, string $linkToken): void
    {
        $invitation = $this->connection->fetchAssociative(
            'SELECT * FROM stars_game_invitation WHERE link_token_hash = :tokenHash',
            ['tokenHash' => hash('sha256', $linkToken)],
        );
        if ($invitation === false) {
            throw new AccountAccessException('The invitation link is invalid.', 404);
        }

        $this->accept($accountId, $accountEmail, $invitation);
    }

    /** @param array<string, mixed> $invitation */
    private function accept(int $accountId, string $accountEmail, array $invitation): void
    {
        if (mb_strtolower(trim((string) $invitation['email'])) !== mb_strtolower(trim($accountEmail))) {
            throw new AccountAccessException('This invitation was sent to another email address.', 403);
        }

        $acceptedAccountId = $invitation['accepted_account_id'] !== null
            ? (int) $invitation['accepted_account_id']
            : null;
        if ($acceptedAccountId !== null && $acceptedAccountId !== $accountId) {
            throw new AccountAccessException('This invitation has already been accepted by another account.', 409);
        }

        $gameId = (int) $invitation['game_id'];
        $playerId = (int) $invitation['player_id'];
        $ownerId = $this->connection->fetchOne(
            'SELECT account_id FROM stars_account_game_access WHERE player_id = :playerId',
            ['playerId' => $playerId],
        );
        if ($ownerId !== false) {
            if ((int) $ownerId !== $accountId) {
                throw new AccountAccessException('This player seat is already linked to another account.', 409);
            }
            $this->markAccepted((int) $invitation['id'], $accountId);
            return;
        }

        $existingGame = $this->connection->fetchOne(
            'SELECT id FROM stars_account_game_access WHERE account_id = :accountId AND game_id = :gameId',
            ['accountId' => $accountId, 'gameId' => $gameId],
        );
        if ($existingGame !== false) {
            throw new AccountAccessException('This account already participates in the game.', 409);
        }

        $tokenColumn = $this->playerTokenHashColumn();
        $legacyPlayerToken = bin2hex(random_bytes(32));
        $tokenHash = $this->playerTokenHashValue($tokenColumn, $playerId, $legacyPlayerToken);

        $this->connection->transactional(function (Connection $connection) use (
            $accountId,
            $gameId,
            $playerId,
            $invitation,
            $tokenColumn,
            $tokenHash,
            $legacyPlayerToken,
        ): void {
            $connection->update('stars_player', [$tokenColumn => $tokenHash], ['id' => $playerId]);
            $connection->insert('stars_account_game_access', [
                'account_id' => $accountId,
                'game_id' => $gameId,
                'player_id' => $playerId,
                'token_ciphertext' => $this->vault->encrypt($legacyPlayerToken),
                'token_last_four' => substr($legacyPlayerToken, -4),
                'created_at' => $this->now(),
            ]);
            $connection->update('stars_game_invitation', [
                'accepted_at' => $this->now(),
                'accepted_account_id' => $accountId,
                'last_error' => null,
            ], ['id' => (int) $invitation['id']]);
        });
    }

    private function markAccepted(int $invitationId, int $accountId): void
    {
        $this->connection->update('stars_game_invitation', [
            'accepted_at' => $this->now(),
            'accepted_account_id' => $accountId,
            'last_error' => null,
        ], ['id' => $invitationId]);
    }

    private function sendInvitationEmail(string $address, int $gameId, int $playerId, string $linkToken): void
    {
        $baseUrl = rtrim($this->environmentValue('STARS_FRONTEND_BASE_URL', ''), '/');
        $from = $this->environmentValue('STARS_MAILER_FROM', 'no-reply@example.invalid');
        $gameLabel = $this->gameLabel($gameId);
        $playerLabel = $this->playerLabel($playerId);
        $link = $baseUrl.'/?invite='.rawurlencode($linkToken);
        $text = <<<TEXT
Hello,

You have been invited to join {$gameLabel} in Stars Rekindled as {$playerLabel}.

Accept invitation
-----------------
{$link}

Open the link in a browser. If you are not logged in, log in or create an account with this email address. The game will then be linked automatically to your account.

You can also log in at {$baseUrl}. The invitation will appear in the Join a game list, where you can select it and press Join game.

Game: {$gameLabel}
Game ID: {$gameId}
Player: {$playerLabel}
TEXT;

        $this->mailer->send(
            (new Email())
                ->from($from)
                ->to($address)
                ->subject('Stars Rekindled invitation: '.$gameLabel)
                ->text($text),
        );
    }

    private function playerEmailColumn(): string
    {
        $columns = array_change_key_case($this->connection->createSchemaManager()->listTableColumns('stars_player'), CASE_LOWER);
        foreach (['email', 'email_address', 'mail'] as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }
        foreach (array_keys($columns) as $candidate) {
            if (str_contains($candidate, 'email') || str_contains($candidate, 'mail')) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No email column was found in stars_player.');
    }

    private function playerTokenHashColumn(): string
    {
        $columns = array_change_key_case($this->connection->createSchemaManager()->listTableColumns('stars_player'), CASE_LOWER);
        foreach (['access_token_hash', 'token_hash', 'api_token_hash'] as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }
        foreach (array_keys($columns) as $candidate) {
            if (str_contains($candidate, 'token') && str_contains($candidate, 'hash')) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No token hash column was found in stars_player.');
    }

    private function playerTokenHashValue(string $column, int $playerId, string $token): string
    {
        $quotedColumn = $this->connection->getDatabasePlatform()->quoteSingleIdentifier($column);
        $current = $this->connection->fetchOne(
            sprintf('SELECT %s FROM stars_player WHERE id = :id', $quotedColumn),
            ['id' => $playerId],
        );

        if (is_string($current) && strlen($current) === 32) {
            return hash('sha256', $token, true);
        }

        return hash('sha256', $token);
    }

    private function gameLabel(int $gameId): string
    {
        try {
            $game = $this->connection->fetchAssociative('SELECT * FROM stars_game WHERE id = :id', ['id' => $gameId]);
            if ($game !== false) {
                foreach (['name', 'title'] as $column) {
                    if (isset($game[$column]) && trim((string) $game[$column]) !== '') {
                        return (string) $game[$column];
                    }
                }
            }
        } catch (Throwable) {
        }

        return sprintf('Game %d', $gameId);
    }

    private function playerLabel(int $playerId): string
    {
        try {
            $player = $this->connection->fetchAssociative('SELECT * FROM stars_player WHERE id = :id', ['id' => $playerId]);
            if ($player !== false) {
                foreach (['display_name', 'name', 'email'] as $column) {
                    if (isset($player[$column]) && trim((string) $player[$column]) !== '') {
                        return (string) $player[$column];
                    }
                }
            }
        } catch (Throwable) {
        }

        return sprintf('Player %d', $playerId);
    }

    private function randomInvitationToken(): string
    {
        return 'sri_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function environmentValue(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
