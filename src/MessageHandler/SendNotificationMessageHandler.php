<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\MessageHandler;

use Bellcom\StarsTurnBundle\Message\SendNotificationMessage;
use Bellcom\StarsTurnBundle\Notification\TurnNotificationFactory;
use Bellcom\StarsTurnBundle\Repository\NotificationDeliveryRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendNotificationMessageHandler
{
    public function __construct(
        private NotificationDeliveryRepository $repository,
        private TurnNotificationFactory $factory,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $this->entityManager->wrapInTransaction(function () use ($message): void {
            $delivery = $this->repository->find($message->deliveryId);
            if ($delivery === null) {
                return;
            }

            $this->entityManager->lock($delivery, LockMode::PESSIMISTIC_WRITE);
            if ($delivery->isSent()) {
                return;
            }

            // SMTP-kaldet sker under låsen i MVP'et. Det forhindrer to workers i at
            // sende samme levering samtidigt. Ved stor skala bør dette erstattes af
            // en egentlig outbox/provider-idempotency-strategi.
            $this->mailer->send($this->factory->createEmail($delivery));
            $delivery->markSent();
            $this->entityManager->flush();
        });
    }
}
