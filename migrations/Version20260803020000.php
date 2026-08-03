<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move direct-client authentication to one account token shared across all joined games.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE stars_account
    ADD client_token_hash CHAR(64) DEFAULT NULL,
    ADD client_token_last_four VARCHAR(4) DEFAULT NULL,
    ADD client_token_created_at DATETIME DEFAULT NULL,
    ADD UNIQUE INDEX uniq_stars_account_client_token (client_token_hash)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE stars_account
    DROP INDEX uniq_stars_account_client_token,
    DROP client_token_hash,
    DROP client_token_last_four,
    DROP client_token_created_at
SQL);
    }
}
