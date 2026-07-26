<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Entity;

use Bellcom\StarsTurnBundle\Domain\TurnGenerationResult;
use Bellcom\StarsTurnBundle\Enum\TurnStatus;
use Bellcom\StarsTurnBundle\Repository\TurnRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TurnRepository::class)]
#[ORM\Table(name: 'stars_turn')]
#[ORM\UniqueConstraint(name: 'uniq_stars_turn_game_number', columns: ['game_id', 'turn_number'])]
class Turn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'turns')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column(name: 'turn_number', type: Types::INTEGER)]
    private int $number;

    #[ORM\Column(length: 32, enumType: TurnStatus::class)]
    private TurnStatus $status = TurnStatus::OPEN;

    #[ORM\Column(length: 64)]
    private string $randomSeed;

    #[ORM\Column(length: 64)]
    private string $rulesVersion;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $initialState;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $resultState = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $playerReports = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $queuedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failureMessage = null;

    /** @param array<string, mixed> $initialState */
    public function __construct(
        Game $game,
        int $number,
        array $initialState,
        ?string $randomSeed = null,
        string $rulesVersion = 'demo-1',
    ) {
        if ($number < 1) {
            throw new \InvalidArgumentException('Rundenummer skal være mindst 1.');
        }

        $this->game = $game;
        $this->number = $number;
        $this->initialState = $initialState;
        $this->randomSeed = $randomSeed ?? bin2hex(random_bytes(32));
        $this->rulesVersion = $rulesVersion;
        $this->createdAt = new \DateTimeImmutable();
        $game->addTurn($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getStatus(): TurnStatus
    {
        return $this->status;
    }

    public function getRandomSeed(): string
    {
        return $this->randomSeed;
    }

    public function getRulesVersion(): string
    {
        return $this->rulesVersion;
    }

    /** @return array<string, mixed> */
    public function getInitialState(): array
    {
        return $this->initialState;
    }

    /** @return array<string, mixed>|null */
    public function getResultState(): ?array
    {
        return $this->resultState;
    }

    public function getQueuedAt(): ?\DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function queue(): void
    {
        if ($this->status !== TurnStatus::OPEN) {
            throw new \DomainException('Kun en åben runde kan sættes i kø.');
        }

        $this->status = TurnStatus::QUEUED;
        $this->queuedAt = new \DateTimeImmutable();
        $this->failureMessage = null;
    }

    public function beginGeneration(): void
    {
        if ($this->status !== TurnStatus::QUEUED) {
            throw new \DomainException('Kun en kølagt runde kan genereres.');
        }

        $this->status = TurnStatus::GENERATING;
    }

    public function publish(TurnGenerationResult $result): void
    {
        if ($this->status !== TurnStatus::GENERATING) {
            throw new \DomainException('Runden er ikke under generering.');
        }

        $this->resultState = $result->nextState;
        $this->playerReports = $result->playerReports;
        $this->status = TurnStatus::PUBLISHED;
        $this->publishedAt = new \DateTimeImmutable();
        $this->failureMessage = null;
    }

    public function resetForRetry(string $failureMessage): void
    {
        $this->status = TurnStatus::QUEUED;
        $this->failureMessage = mb_substr($failureMessage, 0, 65000);
    }
}
