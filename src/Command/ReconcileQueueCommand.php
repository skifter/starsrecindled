<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Command;

use Bellcom\StarsTurnBundle\Message\GenerateTurnMessage;
use Bellcom\StarsTurnBundle\Message\SendNotificationMessage;
use Bellcom\StarsTurnBundle\Repository\NotificationDeliveryRepository;
use Bellcom\StarsTurnBundle\Repository\TurnRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'stars:queue:reconcile', description: 'Genkøer kølagte runder og usendte notifikationer idempotent.')]
final class ReconcileQueueCommand extends Command
{
    public function __construct(
        private readonly TurnRepository $turnRepository,
        private readonly NotificationDeliveryRepository $notificationRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $turns = 0;
        foreach ($this->turnRepository->findQueued() as $turn) {
            if ($turn->getId() !== null) {
                $this->messageBus->dispatch(new GenerateTurnMessage($turn->getId()));
                ++$turns;
            }
        }

        $notifications = 0;
        foreach ($this->notificationRepository->findPending() as $delivery) {
            if ($delivery->getId() !== null) {
                $this->messageBus->dispatch(new SendNotificationMessage($delivery->getId()));
                ++$notifications;
            }
        }

        $io->success(sprintf('Genkøede %d runder og %d notifikationer.', $turns, $notifications));
        return Command::SUCCESS;
    }
}
