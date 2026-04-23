<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421143134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des slugs pour cursus, lesson et theme avec remplissage des données existantes';
    }

    public function up(Schema $schema): void
    {
        // 1) Ajouter les colonnes en nullable d'abord
        $this->addSql('ALTER TABLE cursus ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE theme ADD slug VARCHAR(255) DEFAULT NULL');

        // 2) Remplir avec une valeur unique basée sur le nom/titre + id
        $this->addSql("
            UPDATE cursus
            SET slug = LOWER(
                CONCAT(
                    REPLACE(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', '-'), '''', ''), '\"', ''), '--', '-'),
                    '-',
                    id
                )
            )
            WHERE slug IS NULL
        ");

        $this->addSql("
            UPDATE lesson
            SET slug = LOWER(
                CONCAT(
                    REPLACE(REPLACE(REPLACE(REPLACE(TRIM(title), ' ', '-'), '''', ''), '\"', ''), '--', '-'),
                    '-',
                    id
                )
            )
            WHERE slug IS NULL
        ");

        $this->addSql("
            UPDATE theme
            SET slug = LOWER(
                CONCAT(
                    REPLACE(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', '-'), '''', ''), '\"', ''), '--', '-'),
                    '-',
                    id
                )
            )
            WHERE slug IS NULL
        ");

        // 3) Passer en NOT NULL
        $this->addSql('ALTER TABLE cursus MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE lesson MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE theme MODIFY slug VARCHAR(255) NOT NULL');

        // 4) Ajouter les index uniques
        $this->addSql('CREATE UNIQUE INDEX UNIQ_255A0C3989D9B62 ON cursus (slug)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F87474F3989D9B62 ON lesson (slug)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9775E708989D9B62 ON theme (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_F87474F3989D9B62 ON lesson');
        $this->addSql('ALTER TABLE lesson DROP slug');

        $this->addSql('DROP INDEX UNIQ_255A0C3989D9B62 ON cursus');
        $this->addSql('ALTER TABLE cursus DROP slug');

        $this->addSql('DROP INDEX UNIQ_9775E708989D9B62 ON theme');
        $this->addSql('ALTER TABLE theme DROP slug');
    }
}