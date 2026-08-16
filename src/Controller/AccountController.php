<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Controller;

use Bellcom\StarsTurnBundle\Service\AccountAccessException;
use Bellcom\StarsTurnBundle\Service\AccountAccessService;
use Bellcom\StarsTurnBundle\Service\GameInvitationService;
use Bellcom\StarsTurnBundle\Service\ResolvedAccountAccess;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AccountAccessService $access,
        private readonly GameInvitationService $invitations,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $payload = $this->jsonPayload($request);
            $displayName = $this->requiredString($payload, 'displayName', 2, 120);
            $email = mb_strtolower($this->requiredEmail($payload, 'email'));
            $password = $this->requiredString($payload, 'password', 12, 4096);

            if ($this->connection->fetchOne('SELECT id FROM stars_account WHERE email = :email', ['email' => $email]) !== false) {
                return $this->error('An account already exists for this email address.', Response::HTTP_CONFLICT);
            }

            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
            if (!is_string($passwordHash)) {
                throw new \RuntimeException('Unable to hash password.');
            }

            $clientToken = $this->access->randomClientToken();
            $now = $this->now();
            $accountId = $this->connection->transactional(function (Connection $connection) use (
                $email,
                $displayName,
                $passwordHash,
                $clientToken,
                $now,
            ): int {
                $connection->insert('stars_account', [
                    'email' => $email,
                    'display_name' => $displayName,
                    'password_hash' => $passwordHash,
                    'client_token_hash' => hash('sha256', $clientToken),
                    'client_token_last_four' => substr($clientToken, -4),
                    'client_token_created_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return (int) $connection->lastInsertId();
            });

            $mailWarning = $this->sendClientTokenEmail($email, $displayName, $clientToken);
            $session = $this->access->createWebSession($accountId);
            $resolved = new ResolvedAccountAccess($accountId, $email, $displayName, true, $session['token']);
            $response = new JsonResponse($this->profile($resolved, $mailWarning), Response::HTTP_CREATED);

            return $this->withSessionCookie($response, $session['token'], $session['expiresAt'], $request);
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
                'SELECT id, email, display_name, password_hash, client_token_hash FROM stars_account WHERE email = :email',
                ['email' => $email],
            );

            if ($account === false || !password_verify($password, (string) $account['password_hash'])) {
                return $this->error('Invalid email address or password.', Response::HTTP_UNAUTHORIZED);
            }

            $accountId = (int) $account['id'];
            $displayName = (string) $account['display_name'];
            $mailWarning = null;

            if (password_needs_rehash((string) $account['password_hash'], PASSWORD_ARGON2ID)) {
                $newHash = password_hash($password, PASSWORD_ARGON2ID);
                if (is_string($newHash)) {
                    $this->connection->update('stars_account', [
                        'password_hash' => $newHash,
                        'updated_at' => $this->now(),
                    ], ['id' => $accountId]);
                }
            }

            // Accounts created by the earlier game-token model receive their user token at first login.
            if (trim((string) ($account['client_token_hash'] ?? '')) === '') {
                $clientToken = $this->access->randomClientToken();
                $this->connection->update('stars_account', [
                    'client_token_hash' => hash('sha256', $clientToken),
                    'client_token_last_four' => substr($clientToken, -4),
                    'client_token_created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ], ['id' => $accountId]);
                $mailWarning = $this->sendClientTokenEmail($email, $displayName, $clientToken);
            }

            $session = $this->access->createWebSession($accountId);
            $resolved = new ResolvedAccountAccess($accountId, $email, $displayName, true, $session['token']);
            $response = new JsonResponse($this->profile($resolved, $mailWarning));

            return $this->withSessionCookie($response, $session['token'], $session['expiresAt'], $request);
        } catch (AccountInputException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            return new JsonResponse($this->profile($this->access->resolve($request)));
        } catch (AccountAccessException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function directLogin(Request $request): JsonResponse
    {
        try {
            $resolved = $this->access->resolve($request);
            if ($resolved->usesWebSession) {
                return $this->error('Use a client token for direct client access.', Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse($this->profile($resolved));
        } catch (AccountAccessException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function joinGame(Request $request): JsonResponse
    {
        try {
            $resolved = $this->access->resolve($request);
            $this->access->requireWebWrite($request, $resolved);
            $payload = $this->jsonPayload($request);
            $invitationId = $this->requiredPositiveInt($payload, 'invitationId');

            $this->invitations->acceptById(
                $resolved->accountId,
                $resolved->email,
                $invitationId,
            );

            return new JsonResponse($this->profile($resolved, 'The invitation was accepted and the game is now linked to your account.'));
        } catch (AccountInputException|AccountAccessException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function acceptInvitationLink(Request $request): JsonResponse
    {
        try {
            $resolved = $this->access->resolve($request);
            $this->access->requireWebWrite($request, $resolved);
            $payload = $this->jsonPayload($request);
            $token = $this->requiredString($payload, 'token', 20, 4096);

            $this->invitations->acceptByLink(
                $resolved->accountId,
                $resolved->email,
                $token,
            );

            return new JsonResponse($this->profile($resolved, 'Invitation accepted. The game is ready in your account.'));
        } catch (AccountInputException|AccountAccessException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function rotateClientToken(Request $request): JsonResponse
    {
        try {
            $resolved = $this->access->resolve($request);
            $this->access->requireWebWrite($request, $resolved);
            $clientToken = $this->access->randomClientToken();

            $mailWarning = $this->sendClientTokenEmail($resolved->email, $resolved->displayName, $clientToken);
            if ($mailWarning !== null) {
                return $this->error($mailWarning, Response::HTTP_BAD_GATEWAY);
            }

            $this->connection->update('stars_account', [
                'client_token_hash' => hash('sha256', $clientToken),
                'client_token_last_four' => substr($clientToken, -4),
                'client_token_created_at' => $this->now(),
                'updated_at' => $this->now(),
            ], ['id' => $resolved->accountId]);

            return new JsonResponse($this->profile($resolved, 'A new client token was sent to your email address.'));
        } catch (AccountAccessException $exception) {
            return $this->error($exception->getMessage(), $exception->statusCode);
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->access->revokeWebSession($request);
            $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
            $response->headers->setCookie(Cookie::create(
                AccountAccessService::COOKIE_NAME,
                '',
                new DateTimeImmutable('-1 day'),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                Cookie::SAMESITE_LAX,
            ));

            return $response;
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    /** @return array<string, mixed> */
    private function profile(ResolvedAccountAccess $resolved, ?string $notice = null): array
    {
        $account = $this->connection->fetchAssociative(
            'SELECT id, email, display_name, client_token_last_four, client_token_created_at FROM stars_account WHERE id = :id',
            ['id' => $resolved->accountId],
        );
        if ($account === false) {
            throw new AccountAccessException('Account not found.');
        }

        return [
            'account' => [
                'id' => (int) $account['id'],
                'email' => (string) $account['email'],
                'displayName' => (string) $account['display_name'],
                'clientTokenLastFour' => (string) ($account['client_token_last_four'] ?? ''),
                'clientTokenCreatedAt' => $account['client_token_created_at'] !== null
                    ? (new DateTimeImmutable((string) $account['client_token_created_at']))->format(DATE_ATOM)
                    : null,
            ],
            'games' => $this->loadGames($resolved->accountId),
            'invitations' => $this->invitations->pendingForAccount($resolved->accountId, $resolved->email),
            'csrfToken' => $this->access->csrfToken($resolved),
            'authMode' => $resolved->usesWebSession ? 'web' : 'direct',
            'notice' => $notice,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function loadGames(int $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT game_id, player_id FROM stars_account_game_access WHERE account_id = :accountId ORDER BY game_id, player_id',
            ['accountId' => $accountId],
        );

        $games = [];
        foreach ($rows as $row) {
            $gameId = (int) $row['game_id'];
            $playerId = (int) $row['player_id'];
            $games[] = [
                'gameId' => $gameId,
                'playerId' => $playerId,
                'turnNumber' => $this->currentTurnNumber($gameId),
                'label' => $this->gameLabel($gameId),
                'playerLabel' => $this->playerLabel($playerId),
                'players' => $this->gamePlayers($gameId),
            ];
        }

        return $games;
    }

    /** @return list<array{playerId:int, displayName:string, active:bool}> */
    private function gamePlayers(int $gameId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, display_name, active FROM stars_player WHERE game_id = :gameId ORDER BY id',
                ['gameId' => $gameId],
            );

            return array_map(
                static fn (array $row): array => [
                    'playerId' => (int) $row['id'],
                    'displayName' => (string) $row['display_name'],
                    'active' => (bool) $row['active'],
                ],
                $rows,
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function currentTurnNumber(int $gameId): int
    {
        try {
            return max(1, (int) $this->connection->fetchOne(
                'SELECT MAX(turn_number) FROM stars_turn WHERE game_id = :gameId',
                ['gameId' => $gameId],
            ));
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

    private function sendClientTokenEmail(string $address, string $displayName, string $clientToken): ?string
    {
        $baseUrl = rtrim($this->environmentValue('STARS_FRONTEND_BASE_URL', ''), '/');
        $from = $this->environmentValue('STARS_MAILER_FROM', 'no-reply@example.invalid');
        $text = <<<TEXT
Hello {$displayName},

Your Stars Rekindled account is ready.

Web player login
----------------
Open: {$baseUrl}
Email: {$address}
Log in with your email address and password. The web player uses the same account permissions as another client.

Access from another client
--------------------------
API base: {$baseUrl}
Client token: {$clientToken}

The client token belongs to your user account and works across all games you have joined.
It does not belong to one specific game.

Discover account and games:
POST {$baseUrl}/stars/api/account/direct-login
Authorization: Bearer {$clientToken}

Play a game:
GET {$baseUrl}/stars/api/account/games/{gameId}/turns/{turnNumber}
Authorization: Bearer {$clientToken}

Draft, submit and reopen use the same URL with /draft, /submit or /reopen.
Keep this token private. Generate a new token from the web account screen if it is exposed.
TEXT;

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($address)
                    ->subject('Your Stars Rekindled client token')
                    ->text($text),
            );

            return null;
        } catch (Throwable $exception) {
            return 'The account was saved, but the client-token email could not be sent: '.$exception->getMessage();
        }
    }

    /** @return array<string, mixed> */
    private function jsonPayload(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (Throwable) {
            throw new AccountInputException('The request body must contain valid JSON.');
        }
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

    private function withSessionCookie(
        JsonResponse $response,
        string $token,
        DateTimeImmutable $expiresAt,
        Request $request,
    ): JsonResponse {
        $response->headers->setCookie(Cookie::create(
            AccountAccessService::COOKIE_NAME,
            $token,
            $expiresAt,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));

        return $response;
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
