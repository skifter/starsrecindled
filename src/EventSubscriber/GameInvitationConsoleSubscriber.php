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

        // STARS_AI_PLAYERS_DEV5: AI test games are self-contained and must not
        // generate invitation e-mails for synthetic AI addresses.
        if ((int) $event->getInput()->getOption('ai') > 0) {
            $event->getOutput()->writeln('AI test game: invitation reconciliation skipped.');
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
