<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Notification;

use Bellcom\StarsTurnBundle\Entity\NotificationDelivery;
use Bellcom\StarsTurnBundle\Enum\NotificationEventType;
use Symfony\Component\Mime\Email;

final readonly class TurnNotificationFactory
{
    public function __construct(
        private string $mailerFrom,
        private string $frontendBaseUrl,
    ) {
    }

    public function createEmail(NotificationDelivery $delivery): Email
    {
        $turn = $delivery->getTurn();
        $game = $turn->getGame();
        $player = $delivery->getPlayer();
        $url = sprintf(
            '%s/?game=%d&player=%d&turn=%d',
            $this->frontendBaseUrl,
            $game->getId(),
            $player->getId(),
            $delivery->getEventType() === NotificationEventType::TURN_PUBLISHED ? $turn->getNumber() + 1 : $turn->getNumber(),
        );

        return match ($delivery->getEventType()) {
            NotificationEventType::ALL_PLAYERS_SUBMITTED => (new Email())
                ->from($this->mailerFrom)
                ->to($player->getEmail())
                ->subject(sprintf('[%s] Alle har afleveret runde %d', $game->getName(), $turn->getNumber()))
                ->text(sprintf(
                    "Hej %s\n\nAlle aktive spillere har afleveret runde %d. Runden er sat i kø til generering.\n\nSpil: %s\n%s\n",
                    $player->getDisplayName(),
                    $turn->getNumber(),
                    $game->getName(),
                    $url,
                )),
            NotificationEventType::TURN_PUBLISHED => (new Email())
                ->from($this->mailerFrom)
                ->to($player->getEmail())
                ->subject(sprintf('[%s] Runde %d er klar', $game->getName(), $turn->getNumber() + 1))
                ->text(sprintf(
                    "Hej %s\n\nRunde %d er genereret, og runde %d er nu åben for ordrer.\n\nSpil: %s\n%s\n",
                    $player->getDisplayName(),
                    $turn->getNumber(),
                    $turn->getNumber() + 1,
                    $game->getName(),
                    $url,
                )),
        };
    }
}
