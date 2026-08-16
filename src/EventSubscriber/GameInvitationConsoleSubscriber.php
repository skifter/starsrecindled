<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\EventSubscriber;

use Bellcom\StarsTurnBundle\Service\GameInvitationService;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class GameInvitationConsoleSubscriber implements EventSubscriberInterface
{
    public function __construct(private GameInvitationService $invitations)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getCommand()?->getName() !== 'stars:game:create' || $event->getExitCode() !== 0) {
            return;
        }

        $result = $this->invitations->reconcile();
        $event->getOutput()->writeln(sprintf(
            'Invitations: created=%d emailed=%d failed=%d',
            $result['created'],
            $result['emailed'],
            $result['failed'],
        ));
    }
}
