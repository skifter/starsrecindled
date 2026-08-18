<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add human/AI controller metadata to Stars players';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE stars_player ADD controller_type VARCHAR(16) DEFAULT 'human' NOT NULL, ADD ai_level VARCHAR(16) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stars_player DROP controller_type, DROP ai_level');
    }
}
