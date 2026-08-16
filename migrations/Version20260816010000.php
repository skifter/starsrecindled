<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds persistent email game invitations that can be accepted by link or from the account lobby.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE stars_game_invitation (
    id BIGINT AUTO_INCREMENT NOT NULL,
    game_id BIGINT NOT NULL,
    player_id BIGINT NOT NULL,
    email VARCHAR(180) NOT NULL,
    link_token_hash CHAR(64) NOT NULL,
    link_token_ciphertext LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    emailed_at DATETIME DEFAULT NULL,
    accepted_at DATETIME DEFAULT NULL,
    accepted_account_id BIGINT DEFAULT NULL,
    last_error LONGTEXT DEFAULT NULL,
    INDEX idx_stars_game_invitation_email (email),
    INDEX idx_stars_game_invitation_game (game_id),
    INDEX idx_stars_game_invitation_accepted (accepted_at),
    UNIQUE INDEX uniq_stars_game_invitation_player (player_id),
    UNIQUE INDEX uniq_stars_game_invitation_token (link_token_hash),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stars_game_invitation');
    }
}
