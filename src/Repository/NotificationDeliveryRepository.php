<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Repository;

use Bellcom\StarsTurnBundle\Entity\NotificationDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationDelivery> */
final class NotificationDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDelivery::class);
    }

    public function findByDedupKey(string $dedupKey): ?NotificationDelivery
    {
        return $this->findOneBy(['dedupKey' => $dedupKey]);
    }

    /** @return list<NotificationDelivery> */
    public function findPending(): array
    {
        return $this->findBy(['status' => \Bellcom\StarsTurnBundle\Enum\NotificationStatus::PENDING]);
    }
}
