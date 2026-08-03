<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Controller;

use Bellcom\StarsTurnBundle\Service\AccountAccessException;
use Bellcom\StarsTurnBundle\Service\AccountAccessService;
use Bellcom\StarsTurnBundle\Service\AccountCredentialVault;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

final readonly class AccountGameProxyController
{
    public function __construct(
        private Connection $connection,
        private AccountAccessService $access,
        private AccountCredentialVault $vault,
        private HttpKernelInterface $kernel,
    ) {
    }

    public function status(Request $request, int $gameId, int $turnNumber): Response
    {
        return $this->forward($request, $gameId, $turnNumber, '');
    }

    public function draft(Request $request, int $gameId, int $turnNumber): Response
    {
        return $this->forward($request, $gameId, $turnNumber, '/draft');
    }

    public function submit(Request $request, int $gameId, int $turnNumber): Response
    {
        return $this->forward($request, $gameId, $turnNumber, '/submit');
    }

    public function reopen(Request $request, int $gameId, int $turnNumber): Response
    {
        return $this->forward($request, $gameId, $turnNumber, '/reopen');
    }

    private function forward(Request $request, int $gameId, int $turnNumber, string $suffix): Response
    {
        try {
            $resolved = $this->access->resolve($request);
            if ($request->getMethod() !== Request::METHOD_GET && $resolved->usesWebSession) {
                $this->access->requireWebWrite($request, $resolved);
            }

            $credential = $this->connection->fetchAssociative(
                <<<'SQL'
SELECT player_id, token_ciphertext
FROM stars_account_game_access
WHERE account_id = :accountId AND game_id = :gameId
ORDER BY id
LIMIT 1
SQL,
                ['accountId' => $resolved->accountId, 'gameId' => $gameId],
            );
            if ($credential === false) {
                return new JsonResponse(['error' => 'This account has not joined the requested game.'], Response::HTTP_FORBIDDEN);
            }

            $playerId = (int) $credential['player_id'];
            $playerToken = $this->vault->decrypt((string) $credential['token_ciphertext']);
            $server = $request->server->all();
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$playerToken;
            $server['HTTP_X_STARS_PLAYER_ID'] = (string) $playerId;
            $server['CONTENT_TYPE'] = $request->headers->get('Content-Type', 'application/json');
            unset(
                $server['HTTP_X_STARS_CSRF'],
                $server['HTTP_COOKIE'],
                $server['REQUEST_URI'],
                $server['PATH_INFO'],
                $server['QUERY_STRING'],
            );

            $subRequest = Request::create(
                sprintf('/stars/api/games/%d/turns/%d%s', $gameId, $turnNumber, $suffix),
                $request->getMethod(),
                $request->query->all(),
                [],
                [],
                $server,
                $request->getContent(),
            );
            $subRequest->attributes->set('_stars_account_proxy', true);

            return $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
        } catch (AccountAccessException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], $exception->statusCode);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'error' => 'The account game request failed.',
                'detail' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
