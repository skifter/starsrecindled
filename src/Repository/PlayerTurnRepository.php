<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Repository;

use Bellcom\StarsTurnBundle\Entity\Turn;

use Bellcom\StarsTurnBundle\Entity\PlayerTurn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PlayerTurn> */
final class PlayerTurnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayerTurn::class);
    }

    public function findForTurnAndPlayer(Turn $turn, Player $player): ?PlayerTurn
    {
        return $this->findOneBy(['turn' => $turn, 'player' => $player]);
    }

    /** @return list<PlayerTurn> */
    public function findForTurn(Turn $turn): array
    {
        return $this->findBy(['turn' => $turn], ['id' => 'ASC']);
    }
}
