<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds email/password accounts, encrypted game access links and account sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE stars_account (
    id BIGINT AUTO_INCREMENT NOT NULL,
    email VARCHAR(180) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX uniq_stars_account_email (email),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE stars_account_game_access (
    id BIGINT AUTO_INCREMENT NOT NULL,
    account_id BIGINT NOT NULL,
    game_id BIGINT NOT NULL,
    player_id BIGINT NOT NULL,
    token_ciphertext LONGTEXT NOT NULL,
    token_last_four VARCHAR(4) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_stars_account_access_account (account_id),
    INDEX idx_stars_account_access_game (game_id),
    UNIQUE INDEX uniq_stars_account_access_player (player_id),
    UNIQUE INDEX uniq_stars_account_access_link (account_id, player_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE stars_account_session (
    id BIGINT AUTO_INCREMENT NOT NULL,
    account_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_stars_account_session_account (account_id),
    INDEX idx_stars_account_session_expires (expires_at),
    UNIQUE INDEX uniq_stars_account_session_token (token_hash),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql('ALTER TABLE stars_account_game_access ADD CONSTRAINT fk_stars_account_access_account FOREIGN KEY (account_id) REFERENCES stars_account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stars_account_session ADD CONSTRAINT fk_stars_account_session_account FOREIGN KEY (account_id) REFERENCES stars_account (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stars_account_game_access DROP FOREIGN KEY fk_stars_account_access_account');
        $this->addSql('ALTER TABLE stars_account_session DROP FOREIGN KEY fk_stars_account_session_account');
        $this->addSql('DROP TABLE stars_account_session');
        $this->addSql('DROP TABLE stars_account_game_access');
        $this->addSql('DROP TABLE stars_account');
    }
}
