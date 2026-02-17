<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216143938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE certification (id INT AUTO_INCREMENT NOT NULL, issued_at DATETIME NOT NULL, certificate_code VARCHAR(255) NOT NULL, user_id INT NOT NULL, cursus_id INT NOT NULL, INDEX IDX_6C3C6D75A76ED395 (user_id), INDEX IDX_6C3C6D7540AEF4B9 (cursus_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, fullname VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, sent_at DATETIME NOT NULL, handled TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE certification ADD CONSTRAINT FK_6C3C6D75A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE certification ADD CONSTRAINT FK_6C3C6D7540AEF4B9 FOREIGN KEY (cursus_id) REFERENCES cursus (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE certification DROP FOREIGN KEY FK_6C3C6D75A76ED395');
        $this->addSql('ALTER TABLE certification DROP FOREIGN KEY FK_6C3C6D7540AEF4B9');
        $this->addSql('DROP TABLE certification');
        $this->addSql('DROP TABLE contact');
    }
}
