<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430112837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE lesson SET slug = 'decouverte-de-l-instrument' WHERE id = 1");
        $this->addSql("UPDATE lesson SET slug = 'les-accords-et-les-gammes' WHERE id = 2");
        $this->addSql("UPDATE lesson SET slug = 'decouverte-du-piano' WHERE id = 3");
        $this->addSql("UPDATE lesson SET slug = 'les-accords-et-gammes-au-piano' WHERE id = 4");
        $this->addSql("UPDATE lesson SET slug = 'les-langages-html-et-css' WHERE id = 5");
        $this->addSql("UPDATE lesson SET slug = 'dynamiser-votre-site-avec-javascript' WHERE id = 6");
        $this->addSql("UPDATE lesson SET slug = 'les-outils-du-jardinier' WHERE id = 7");
        $this->addSql("UPDATE lesson SET slug = 'jardiner-avec-la-lune' WHERE id = 8");
        $this->addSql("UPDATE lesson SET slug = 'les-modes-de-cuisson' WHERE id = 9");
        $this->addSql("UPDATE lesson SET slug = 'les-saveurs' WHERE id = 10");
        $this->addSql("UPDATE lesson SET slug = 'mettre-en-oeuvre-le-style-dans-l-assiette' WHERE id = 11");
        $this->addSql("UPDATE lesson SET slug = 'harmoniser-un-repas-a-quatre-plats' WHERE id = 12");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
