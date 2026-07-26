<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Security;

use Bellcom\StarsTurnBundle\Entity\Player;
use Bellcom\StarsTurnBundle\Repository\PlayerRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class PlayerTokenAuthenticator
{
    public function __construct(private PlayerRepository $playerRepository)
    {
    }

    public function authenticate(int $gameId, Request $request): Player
    {
        $playerId = filter_var(
            $request->headers->get('X-Stars-Player-Id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $authorization = (string) $request->headers->get('Authorization', '');

        if ($playerId === false || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new AccessDeniedHttpException('Spiller-id og Bearer-token er påkrævet.');
        }

        $player = $this->playerRepository->findForGameAndId($gameId, (int) $playerId);
        if ($player === null || !$player->tokenMatches(trim($matches[1]))) {
            throw new AccessDeniedHttpException('Ugyldige spilleroplysninger.');
        }

        return $player;
    }
}
