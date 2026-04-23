<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nettoie les anciens slugs avec apostrophes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE cursus SET slug = REPLACE(slug, '’', '-')");
        $this->addSql("UPDATE cursus SET slug = REPLACE(slug, '''', '-')");

        $this->addSql("UPDATE theme SET slug = REPLACE(slug, '’', '-')");
        $this->addSql("UPDATE theme SET slug = REPLACE(slug, '''', '-')");

        $this->addSql("UPDATE lesson SET slug = REPLACE(slug, '’', '-')");
        $this->addSql("UPDATE lesson SET slug = REPLACE(slug, '''', '-')");
    }

    public function down(Schema $schema): void
    {
    }
}