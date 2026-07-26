<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Entity;

use Bellcom\StarsTurnBundle\Enum\PlayerTurnStatus;
use Bellcom\StarsTurnBundle\Repository\PlayerTurnRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerTurnRepository::class)]
#[ORM\Table(name: 'stars_player_turn')]
#[ORM\UniqueConstraint(name: 'uniq_stars_player_turn', columns: ['turn_id', 'player_id'])]
class PlayerTurn
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

    #[ORM\Column(length: 32, enumType: PlayerTurnStatus::class)]
    private PlayerTurnStatus $status = PlayerTurnStatus::DRAFT;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $orders = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function __construct(Turn $turn, Player $player)
    {
        if ($turn->getGame() !== $player->getGame()) {
            throw new \InvalidArgumentException('Spiller og runde skal tilhøre samme spil.');
        }

        $this->turn = $turn;
        $this->player = $player;
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getStatus(): PlayerTurnStatus
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    /** @param array<string, mixed> $orders */
    public function saveDraft(array $orders): void
    {
        if ($this->status !== PlayerTurnStatus::DRAFT) {
            throw new \DomainException('En afleveret runde skal genåbnes før ændring.');
        }

        $this->orders = $orders;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param array<string, mixed> $orders */
    public function submit(array $orders): void
    {
        if ($this->status !== PlayerTurnStatus::DRAFT) {
            throw new \DomainException('Runden er allerede afleveret.');
        }

        $this->orders = $orders;
        $this->status = PlayerTurnStatus::SUBMITTED;
        $this->submittedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->submittedAt;
    }

    public function reopen(): void
    {
        if ($this->status !== PlayerTurnStatus::SUBMITTED) {
            throw new \DomainException('Kun en afleveret runde kan genåbnes.');
        }

        $this->status = PlayerTurnStatus::DRAFT;
        $this->submittedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
