<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Notification;

use Bellcom\StarsTurnBundle\Entity\NotificationDelivery;
use Bellcom\StarsTurnBundle\Entity\Turn;
use Bellcom\StarsTurnBundle\Enum\NotificationEventType;
use Bellcom\StarsTurnBundle\Message\SendNotificationMessage;
use Bellcom\StarsTurnBundle\Repository\NotificationDeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class TurnNotificationPlanner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationDeliveryRepository $repository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function plan(Turn $turn, NotificationEventType $eventType): void
    {
        $newDeliveries = [];

        foreach ($turn->getGame()->getActivePlayers() as $player) {
            $candidate = new NotificationDelivery($turn, $player, $eventType);
            $existing = $this->repository->findByDedupKey($candidate->getDedupKey());
            if ($existing !== null) {
                continue;
            }

            $this->entityManager->persist($candidate);
            $newDeliveries[] = $candidate;
        }

        $this->entityManager->flush();

        foreach ($newDeliveries as $delivery) {
            $deliveryId = $delivery->getId();
            if ($deliveryId !== null) {
                $this->messageBus->dispatch(new SendNotificationMessage($deliveryId));
            }
        }
    }
}
