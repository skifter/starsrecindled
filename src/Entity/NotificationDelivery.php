<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Entity;

use Bellcom\StarsTurnBundle\Enum\NotificationEventType;
use Bellcom\StarsTurnBundle\Enum\NotificationStatus;
use Bellcom\StarsTurnBundle\Repository\NotificationDeliveryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationDeliveryRepository::class)]
#[ORM\Table(name: 'stars_notification_delivery')]
#[ORM\UniqueConstraint(name: 'uniq_stars_notification_dedup', columns: ['dedup_key'])]
class NotificationDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Turn::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Turn $turn;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $player;

    #[ORM\Column(length: 64, enumType: NotificationEventType::class)]
    private NotificationEventType $eventType;

    #[ORM\Column(name: 'dedup_key', length: 190)]
    private string $dedupKey;

    #[ORM\Column(length: 32, enumType: NotificationStatus::class)]
    private NotificationStatus $status = NotificationStatus::PENDING;

    #[ORM\Column(type: Types::INTEGER)]
    private int $attempts = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct(Turn $turn, Player $player, NotificationEventType $eventType)
    {
        $turnId = $turn->getId();
        $playerId = $player->getId();
        if ($turnId === null || $playerId === null) {
            throw new \LogicException('Runde og spiller skal være gemt før notifikation planlægges.');
        }

        $this->turn = $turn;
        $this->player = $player;
        $this->eventType = $eventType;
        $this->dedupKey = sprintf(
            'game:%d:turn:%d:event:%s:player:%d',
            $turn->getGame()->getId(),
            $turn->getNumber(),
            $eventType->value,
            $playerId,
        );
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTurn(): Turn
    {
        return $this->turn;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getEventType(): NotificationEventType
    {
        return $this->eventType;
    }

    public function getDedupKey(): string
    {
        return $this->dedupKey;
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::SENT;
    }

    public function markSent(): void
    {
        $this->status = NotificationStatus::SENT;
        $this->sentAt = new \DateTimeImmutable();
        $this->lastError = null;
    }

    public function markFailedAttempt(\Throwable $exception): void
    {
        ++$this->attempts;
        $this->status = NotificationStatus::PENDING;
        $this->lastError = mb_substr($exception->getMessage(), 0, 65000);
    }
}
