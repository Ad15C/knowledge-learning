<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421144205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restaure le champ slug sur theme et remplit les données existantes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE theme ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE theme SET slug = LOWER(CONCAT('theme-', id)) WHERE slug IS NULL");
        $this->addSql('ALTER TABLE theme MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9775E708989D9B62 ON theme (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9775E708989D9B62 ON theme');
        $this->addSql('ALTER TABLE theme DROP slug');
    }
}