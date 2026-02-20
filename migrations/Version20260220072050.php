<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220072050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson DROP user_has_completed');
        $this->addSql('ALTER TABLE purchase ADD paid_at DATETIME DEFAULT NULL, ADD stripe_session_id VARCHAR(255) DEFAULT NULL, ADD order_number VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6117D13B551F0F81 ON purchase (order_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_6117D13B551F0F81 ON purchase');
        $this->addSql('ALTER TABLE purchase DROP paid_at, DROP stripe_session_id, DROP order_number');
        $this->addSql('ALTER TABLE lesson ADD user_has_completed TINYINT(1) NOT NULL');
    }
}
