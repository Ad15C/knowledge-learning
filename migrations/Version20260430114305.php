<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430114305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
         $this->addSql("UPDATE cursus SET slug = 'cursus-d-initiation-à-la-guitare' WHERE id = 1");;
         $this->addSql("UPDATE cursus SET slug = 'cursus-d-initiation-au-piano' WHERE id = 2");
         $this->addSql("UPDATE cursus SET slug = 'cursus-d-initiation-au-developpement-web' WHERE id = 3");
         $this->addSql("UPDATE cursus SET slug = 'cursus-d-initiation-au-jardinage' WHERE id = 4");
         $this->addSql("UPDATE cursus SET slug = 'cursus-d-initiation-a-la-cuisine' WHERE id = 5");
         $this->addSql("UPDATE cursus SET slug = 'cursus-d-initiation-a-l-art-du-dressage-culinaire' WHERE id = 6");
         $this->addSql("UPDATE theme SET slug = 'musique' WHERE id = 1");
         $this->addSql("UPDATE theme SET slug = 'informatique' WHERE id = 2");
         $this->addSql("UPDATE theme SET slug = 'jardinage' WHERE id = 3");
         $this->addSql("UPDATE theme SET slug = 'cuisine' WHERE id = 4");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
