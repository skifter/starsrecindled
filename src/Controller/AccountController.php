<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Controller;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

final class AccountController extends AbstractController
{
    private const SESSION_TTL = 'P30D';

    public function __construct(
        private readonly Connection $connection,
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameters,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $payload = $this->jsonPayload($request);
            $displayName = $this->requiredString($payload, 'displayName', 2, 120);
            $email = mb_strtolower($this->requiredEmail($payload, 'email'));
            $password = $this->requiredString($payload, 'password', 12, 4096);
            $gameId = $this->requiredPositiveInt($payload, 'gameId');
            $playerId = $this->requiredPositiveInt($payload, 'playerId');
            $gameToken = $this->requiredString($payload, 'gameToken', 16, 4096);

            $player = $this->verifyPlayerToken($gameId, $playerId, $gameToken);

            if ($this->connection->fetchOne(
                'SELECT id FROM stars_account WHERE email = :email',
                ['email' => $email],
            ) !== false) {
                return $this->error('An account already exists for this email address.', Response::HTTP_CONFLICT);
            }

            $now = $this->now();
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
            if (!is_string($passwordHash)) {
                throw new \RuntimeException('Unable to hash password.');
            }

            $accountId = $this->connection->transactional(function (Connection $connection) use (
                $email,
                $displayName,
                $passwordHash,
                $gameId,
                $playerId,
                $gameToken,
                $now,
            ): int {
                $connection->insert('stars_account', [
                    'email' => $email,
                    'display_name' => $displayName,
                    'password_hash' => $passwordHash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $accountId = (int) $connection->lastInsertId();
                $connection->insert('stars_account_game_access', [
                    'account_id' => $accountId,
                    'game_id' => $gameId,
                    'player_id' => $playerId,
                    'token_ciphertext' => $this->encryptToken($gameToken),
                    'token_last_four' => substr($gameToken, -4),
                    'created_at' => $now,
                ]);

                return $accountId;
            });

            $mailWarning = $this->sendAccessEmail(
                $email,
                $displayName,
                $gameId,
                $playerId,
                $gameToken,
                $player,
            );

            return $this->authenticatedResponse($accountId, $mailWarning, Response::HTTP_CREATED);
        } catch (AccountInputException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $payload = $this->jsonPayload($request);
            $email = mb_strtolower($this->requiredEmail($payload, 'email'));
            $password = $this->requiredString($payload, 'password', 1, 4096);

            $account = $this->connection->fetchAssociative(
                'SELECT id, email, display_name, password_hash FROM stars_account WHERE email = :email',
                ['email' => $email],
            );

            if ($account === false || !password_verify($password, (string) $account['password_hash'])) {
                return $this->error('Invalid email address or password.', Response::HTTP_UNAUTHORIZED);
            }

            if (password_needs_rehash((string) $account['password_hash'], PASSWORD_ARGON2ID)) {
                $newHash = password_hash($password, PASSWORD_ARGON2ID);
                if (is_string($newHash)) {
                    $this->connection->update('stars_account', [
                        'password_hash' => $newHash,
                        'updated_at' => $this->now(),
                    ], ['id' => (int) $account['id']]);
                }
            }

            return $this->authenticatedResponse((int) $account['id']);
        } catch (AccountInputException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $accountId = $this->authenticatedAccountId($request);

            return new JsonResponse([
                'account' => $this->loadAccount($accountId),
                'games' => $this->loadGames($accountId),
            ]);
        } catch (AccountInputException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function linkGame(Request $request): JsonResponse
    {
        try {
            $accountId = $this->authenticatedAccountId($request);
            $payload = $this->jsonPayload($request);
            $gameId = $this->requiredPositiveInt($payload, 'gameId');
            $playerId = $this->requiredPositiveInt($payload, 'playerId');
            $gameToken = $this->requiredString($payload, 'gameToken', 16, 4096);
            $player = $this->verifyPlayerToken($gameId, $playerId, $gameToken);
            $account = $this->loadAccount($accountId);

            $existingOwner = $this->connection->fetchOne(
                'SELECT account_id FROM stars_account_game_access WHERE player_id = :playerId',
                ['playerId' => $playerId],
            );
            if ($existingOwner !== false && (int) $existingOwner !== $accountId) {
                return $this->error('This player is already linked to another account.', Response::HTTP_CONFLICT);
            }

            $existingAccess = $this->connection->fetchOne(
                'SELECT id FROM stars_account_game_access WHERE account_id = :accountId AND player_id = :playerId',
                ['accountId' => $accountId, 'playerId' => $playerId],
            );

            $values = [
                'game_id' => $gameId,
                'token_ciphertext' => $this->encryptToken($gameToken),
                'token_last_four' => substr($gameToken, -4),
            ];

            if ($existingAccess === false) {
                $this->connection->insert('stars_account_game_access', $values + [
                    'account_id' => $accountId,
                    'player_id' => $playerId,
                    'created_at' => $this->now(),
                ]);
            } else {
                $this->connection->update('stars_account_game_access', $values, ['id' => (int) $existingAccess]);
            }

            $mailWarning = $this->sendAccessEmail(
                (string) $account['email'],
                (string) $account['displayName'],
                $gameId,
                $playerId,
                $gameToken,
                $player,
            );

            return new JsonResponse([
                'account' => $account,
                'games' => $this->loadGames($accountId),
                'mailWarning' => $mailWarning,
            ]);
        } catch (AccountInputException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $this->accountSessionToken($request);
            if ($token !== '') {
                $this->connection->delete('stars_account_session', ['token_hash' => hash('sha256', $token)]);
            }

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    /** @return array<string, mixed> */
    private function jsonPayload(Request $request): array
    {
        try {
            $payload = $request->toArray();
        } catch (Throwable) {
            throw new AccountInputException('The request body must contain valid JSON.');
        }

        return $payload;
    }

    private function requiredString(array $payload, string $field, int $minimum, int $maximum): string
    {
        $value = trim((string) ($payload[$field] ?? ''));
        $length = mb_strlen($value);
        if ($length < $minimum || $length > $maximum) {
            throw new AccountInputException(sprintf('%s must contain between %d and %d characters.', $field, $minimum, $maximum));
        }

        return $value;
    }

    private function requiredEmail(array $payload, string $field): string
    {
        $email = $this->requiredString($payload, $field, 3, 180);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AccountInputException('Enter a valid email address.');
        }

        return $email;
    }

    private function requiredPositiveInt(array $payload, string $field): int
    {
        $value = filter_var($payload[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new AccountInputException(sprintf('%s must be a positive integer.', $field));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function verifyPlayerToken(int $gameId, int $playerId, string $token): array
    {
        $player = $this->connection->fetchAssociative(
            'SELECT * FROM stars_player WHERE id = :playerId',
            ['playerId' => $playerId],
        );

        if ($player === false || (int) ($player['game_id'] ?? 0) !== $gameId) {
            throw new AccountInputException('Unknown game or player.', Response::HTTP_UNAUTHORIZED);
        }

        $hashColumn = null;
        foreach (['access_token_hash', 'token_hash', 'api_token_hash'] as $candidate) {
            if (array_key_exists($candidate, $player)) {
                $hashColumn = $candidate;
                break;
            }
        }
        if ($hashColumn === null) {
            foreach (array_keys($player) as $candidate) {
                $normalized = mb_strtolower((string) $candidate);
                if (str_contains($normalized, 'token') && str_contains($normalized, 'hash')) {
                    $hashColumn = (string) $candidate;
                    break;
                }
            }
        }
        if ($hashColumn === null) {
            throw new \RuntimeException('No token hash column was found in stars_player.');
        }

        $storedHashRaw = (string) $player[$hashColumn];
        $storedHash = mb_strtolower(trim($storedHashRaw));
        $hexHash = hash('sha256', $token);
        $binaryHash = hash('sha256', $token, true);
        $matchesHex = strlen($storedHash) === 64 && hash_equals($storedHash, $hexHash);
        $matchesBinary = strlen($storedHashRaw) === 32 && hash_equals($storedHashRaw, $binaryHash);
        if (!$matchesHex && !$matchesBinary) {
            throw new AccountInputException('The game access token is invalid.', Response::HTTP_UNAUTHORIZED);
        }

        return $player;
    }

    private function authenticatedResponse(int $accountId, ?string $mailWarning = null, int $status = Response::HTTP_OK): JsonResponse
    {
        $sessionToken = bin2hex(random_bytes(32));
        $now = new DateTimeImmutable();
        $expiresAt = $now->add(new DateInterval(self::SESSION_TTL));

        $this->connection->insert('stars_account_session', [
            'account_id' => $accountId,
            'token_hash' => hash('sha256', $sessionToken),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'last_used_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return new JsonResponse([
            'sessionToken' => $sessionToken,
            'expiresAt' => $expiresAt->format(DATE_ATOM),
            'account' => $this->loadAccount($accountId),
            'games' => $this->loadGames($accountId),
            'mailWarning' => $mailWarning,
        ], $status);
    }

    private function authenticatedAccountId(Request $request): int
    {
        $token = $this->accountSessionToken($request);
        if ($token === '') {
            throw new AccountInputException('Account login is required.', Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->connection->fetchAssociative(
            'SELECT id, account_id, expires_at FROM stars_account_session WHERE token_hash = :tokenHash',
            ['tokenHash' => hash('sha256', $token)],
        );

        if ($session === false || new DateTimeImmutable((string) $session['expires_at']) <= new DateTimeImmutable()) {
            if ($session !== false) {
                $this->connection->delete('stars_account_session', ['id' => (int) $session['id']]);
            }
            throw new AccountInputException('The account session has expired.', Response::HTTP_UNAUTHORIZED);
        }

        $this->connection->update('stars_account_session', ['last_used_at' => $this->now()], ['id' => (int) $session['id']]);

        return (int) $session['account_id'];
    }

    private function accountSessionToken(Request $request): string
    {
        $token = trim((string) $request->headers->get('X-Stars-Account-Token', ''));
        if ($token !== '') {
            return $token;
        }

        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if (preg_match('/^Account\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    /** @return array{id:int,email:string,displayName:string} */
    private function loadAccount(int $accountId): array
    {
        $account = $this->connection->fetchAssociative(
            'SELECT id, email, display_name FROM stars_account WHERE id = :id',
            ['id' => $accountId],
        );
        if ($account === false) {
            throw new AccountInputException('Account not found.', Response::HTTP_UNAUTHORIZED);
        }

        return [
            'id' => (int) $account['id'],
            'email' => (string) $account['email'],
            'displayName' => (string) $account['display_name'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function loadGames(int $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT game_id, player_id, token_ciphertext, token_last_four FROM stars_account_game_access WHERE account_id = :accountId ORDER BY game_id, player_id',
            ['accountId' => $accountId],
        );

        $games = [];
        foreach ($rows as $row) {
            $gameId = (int) $row['game_id'];
            $games[] = [
                'gameId' => $gameId,
                'playerId' => (int) $row['player_id'],
                'turnNumber' => $this->currentTurnNumber($gameId),
                'token' => $this->decryptToken((string) $row['token_ciphertext']),
                'tokenLastFour' => (string) $row['token_last_four'],
                'label' => $this->gameLabel($gameId),
            ];
        }

        return $games;
    }

    private function currentTurnNumber(int $gameId): int
    {
        try {
            $turn = $this->connection->fetchOne(
                'SELECT MAX(turn_number) FROM stars_turn WHERE game_id = :gameId',
                ['gameId' => $gameId],
            );

            return max(1, (int) $turn);
        } catch (Throwable) {
            return 1;
        }
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

    private function encryptToken(string $token): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($token, $nonce, $this->encryptionKey());

        return base64_encode($nonce.$ciphertext);
    }

    private function decryptToken(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Stored game token is malformed.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $token = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey());
        if ($token === false) {
            throw new \RuntimeException('Stored game token could not be decrypted.');
        }

        return $token;
    }

    private function encryptionKey(): string
    {
        $secret = (string) $this->parameters->get('kernel.secret');
        if ($secret === '') {
            throw new \RuntimeException('kernel.secret must be configured.');
        }

        return hash('sha256', 'stars-account-token:'.$secret, true);
    }

    /** @param array<string, mixed> $player */
    private function sendAccessEmail(
        string $emailAddress,
        string $displayName,
        int $gameId,
        int $playerId,
        string $gameToken,
        array $player,
    ): ?string {
        $from = $this->environmentValue('STARS_MAILER_FROM', 'no-reply@example.invalid');
        $baseUrl = rtrim($this->environmentValue('STARS_FRONTEND_BASE_URL', ''), '/');
        $playerName = (string) ($player['display_name'] ?? $player['name'] ?? $displayName);

        $text = <<<TEXT
Hello {$displayName},

Your Stars Rekindled account now has access to {$playerName}.

Normal web login
----------------
Open: {$baseUrl}
Log in with your email address and password. The game token is then used automatically behind the login.

Access from another client
--------------------------
API base: {$baseUrl}
Game ID: {$gameId}
Player ID: {$playerId}
Access token: {$gameToken}

Required game API headers:
Authorization: Bearer {$gameToken}
X-Stars-Player-Id: {$playerId}
Content-Type: application/json

Example status request for turn 1:
curl -H 'Authorization: Bearer {$gameToken}' \
     -H 'X-Stars-Player-Id: {$playerId}' \
     '{$baseUrl}/stars/api/games/{$gameId}/turns/1'

Keep this token private. Anyone with the token, game ID and player ID can act as this player.
TEXT;

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($emailAddress)
                    ->subject(sprintf('Stars Rekindled access — game %d, player %d', $gameId, $playerId))
                    ->text($text),
            );

            return null;
        } catch (Throwable $exception) {
            return 'The account was created, but the access email could not be sent: '.$exception->getMessage();
        }
    }


    private function environmentValue(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    private function serverError(Throwable $exception): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Account operation failed.',
            'detail' => $this->getParameter('kernel.environment') === 'prod' ? null : $exception->getMessage(),
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

final class AccountInputException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = Response::HTTP_BAD_REQUEST)
    {
        parent::__construct($message);
    }
}
