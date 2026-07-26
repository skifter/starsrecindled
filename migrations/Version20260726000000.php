<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the MariaDB schema for Stars Recindled.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE stars_game (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(190) NOT NULL,
            status VARCHAR(32) NOT NULL,
            current_turn_number INT NOT NULL,
            created_at DATETIME(6) NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE stars_player (
            id INT AUTO_INCREMENT NOT NULL,
            game_id INT NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            active TINYINT(1) NOT NULL,
            created_at DATETIME(6) NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_STARS_PLAYER_GAME (game_id),
            UNIQUE INDEX uniq_stars_player_game_email (game_id, email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE stars_turn (
            id INT AUTO_INCREMENT NOT NULL,
            game_id INT NOT NULL,
            turn_number INT NOT NULL,
            status VARCHAR(32) NOT NULL,
            random_seed VARCHAR(64) NOT NULL,
            rules_version VARCHAR(64) NOT NULL,
            initial_state JSON NOT NULL,
            result_state JSON DEFAULT NULL,
            player_reports JSON NOT NULL,
            created_at DATETIME(6) NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            queued_at DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            published_at DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            failure_message LONGTEXT DEFAULT NULL,
            INDEX IDX_STARS_TURN_GAME (game_id),
            UNIQUE INDEX uniq_stars_turn_game_number (game_id, turn_number),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE stars_player_turn (
            id INT AUTO_INCREMENT NOT NULL,
            turn_id INT NOT NULL,
            player_id INT NOT NULL,
            status VARCHAR(32) NOT NULL,
            orders JSON NOT NULL,
            updated_at DATETIME(6) NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            submitted_at DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            version INT NOT NULL,
            INDEX IDX_STARS_PLAYER_TURN_TURN (turn_id),
            INDEX IDX_STARS_PLAYER_TURN_PLAYER (player_id),
            UNIQUE INDEX uniq_stars_player_turn (turn_id, player_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE stars_notification_delivery (
            id INT AUTO_INCREMENT NOT NULL,
            turn_id INT NOT NULL,
            player_id INT NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            dedup_key VARCHAR(190) NOT NULL,
            status VARCHAR(32) NOT NULL,
            attempts INT NOT NULL,
            last_error LONGTEXT DEFAULT NULL,
            created_at DATETIME(6) NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            sent_at DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_STARS_NOTIFICATION_TURN (turn_id),
            INDEX IDX_STARS_NOTIFICATION_PLAYER (player_id),
            UNIQUE INDEX uniq_stars_notification_dedup (dedup_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE stars_player ADD CONSTRAINT FK_STARS_PLAYER_GAME FOREIGN KEY (game_id) REFERENCES stars_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stars_turn ADD CONSTRAINT FK_STARS_TURN_GAME FOREIGN KEY (game_id) REFERENCES stars_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stars_player_turn ADD CONSTRAINT FK_STARS_PLAYER_TURN_TURN FOREIGN KEY (turn_id) REFERENCES stars_turn (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stars_player_turn ADD CONSTRAINT FK_STARS_PLAYER_TURN_PLAYER FOREIGN KEY (player_id) REFERENCES stars_player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stars_notification_delivery ADD CONSTRAINT FK_STARS_NOTIFICATION_TURN FOREIGN KEY (turn_id) REFERENCES stars_turn (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stars_notification_delivery ADD CONSTRAINT FK_STARS_NOTIFICATION_PLAYER FOREIGN KEY (player_id) REFERENCES stars_player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stars_notification_delivery');
        $this->addSql('DROP TABLE stars_player_turn');
        $this->addSql('DROP TABLE stars_turn');
        $this->addSql('DROP TABLE stars_player');
        $this->addSql('DROP TABLE stars_game');
    }
}
