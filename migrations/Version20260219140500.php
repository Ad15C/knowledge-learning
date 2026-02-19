<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219140500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase_item ADD lesson_id INT DEFAULT NULL, DROP quantity');
        $this->addSql('ALTER TABLE purchase_item ADD CONSTRAINT FK_6FA8ED7DCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');
        $this->addSql('CREATE INDEX IDX_6FA8ED7DCDF80196 ON purchase_item (lesson_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase_item DROP FOREIGN KEY FK_6FA8ED7DCDF80196');
        $this->addSql('DROP INDEX IDX_6FA8ED7DCDF80196 ON purchase_item');
        $this->addSql('ALTER TABLE purchase_item ADD quantity INT NOT NULL, DROP lesson_id');
    }
}
