<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Entity;

use Bellcom\StarsTurnBundle\Enum\GameStatus;
use Bellcom\StarsTurnBundle\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'stars_game')]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $name;

    #[ORM\Column(length: 32, enumType: GameStatus::class)]
    private GameStatus $status = GameStatus::ACTIVE;

    #[ORM\Column(type: Types::INTEGER)]
    private int $currentTurnNumber = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Player> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Player::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $players;

    /** @var Collection<int, Turn> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Turn::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $turns;

    public function __construct(string $name)
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Spilnavnet må ikke være tomt.');
        }

        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->players = new ArrayCollection();
        $this->turns = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function getCurrentTurnNumber(): int
    {
        return $this->currentTurnNumber;
    }

    public function advanceToTurn(int $turnNumber): void
    {
        if ($turnNumber !== $this->currentTurnNumber + 1) {
            throw new \DomainException('Et spil kan kun flyttes én runde frem ad gangen.');
        }

        $this->currentTurnNumber = $turnNumber;
    }

    public function addPlayer(Player $player): void
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
        }
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    /** @return list<Player> */
    public function getActivePlayers(): array
    {
        return array_values(array_filter(
            $this->players->toArray(),
            static fn (Player $player): bool => $player->isActive(),
        ));
    }

    public function addTurn(Turn $turn): void
    {
        if (!$this->turns->contains($turn)) {
            $this->turns->add($turn);
        }
    }
}
