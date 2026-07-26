<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Repository;

use Bellcom\StarsTurnBundle\Entity\Turn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Turn> */
final class TurnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Turn::class);
    }

    public function findForGameAndNumber(int $gameId, int $turnNumber): ?Turn
    {
        return $this->createQueryBuilder('turn')
            ->andWhere('IDENTITY(turn.game) = :gameId')
            ->andWhere('turn.number = :turnNumber')
            ->setParameter('gameId', $gameId)
            ->setParameter('turnNumber', $turnNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Turn> */
    public function findQueued(): array
    {
        return $this->findBy(['status' => \Bellcom\StarsTurnBundle\Enum\TurnStatus::QUEUED]);
    }
}
