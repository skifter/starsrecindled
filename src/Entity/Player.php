<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Entity;

use Bellcom\StarsTurnBundle\Repository\PlayerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\Table(name: 'stars_player')]
#[ORM\UniqueConstraint(name: 'uniq_stars_player_game_email', columns: ['game_id', 'email'])]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column(length: 120)]
    private string $displayName;

    #[ORM\Column(length: 190)]
    private string $email;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Game $game, string $displayName, string $email, string $plainToken)
    {
        $displayName = trim($displayName);
        $email = mb_strtolower(trim($email));

        if ($displayName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Spillernavn og gyldig e-mail er påkrævet.');
        }
        if (strlen($plainToken) < 32) {
            throw new \InvalidArgumentException('Spillertoken skal have mindst 32 tegn.');
        }

        $this->game = $game;
        $this->displayName = $displayName;
        $this->email = $email;
        $this->tokenHash = hash('sha256', $plainToken);
        $this->createdAt = new \DateTimeImmutable();
        $game->addPlayer($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function tokenMatches(string $plainToken): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $plainToken));
    }
}
