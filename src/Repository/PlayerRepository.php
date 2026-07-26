<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Repository;

use Bellcom\StarsTurnBundle\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Player> */
final class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    public function findForGameAndId(int $gameId, int $playerId): ?Player
    {
        return $this->createQueryBuilder('player')
            ->andWhere('player.id = :playerId')
            ->andWhere('IDENTITY(player.game) = :gameId')
            ->andWhere('player.active = true')
            ->setParameter('playerId', $playerId)
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
