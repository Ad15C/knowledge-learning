<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216143642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lesson_validation (id INT AUTO_INCREMENT NOT NULL, validated_at DATETIME NOT NULL, completed TINYINT(1) NOT NULL, user_id INT NOT NULL, lesson_id INT NOT NULL, purchase_item_id INT DEFAULT NULL, INDEX IDX_1BA512BFA76ED395 (user_id), INDEX IDX_1BA512BFCDF80196 (lesson_id), INDEX IDX_1BA512BF9B59827 (purchase_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE purchase (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, total DOUBLE PRECISION NOT NULL, status VARCHAR(50) NOT NULL, user_id INT NOT NULL, INDEX IDX_6117D13BA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE purchase_item (id INT AUTO_INCREMENT NOT NULL, price DOUBLE PRECISION NOT NULL, quantity INT NOT NULL, purchase_id INT NOT NULL, cursus_id INT NOT NULL, INDEX IDX_6FA8ED7D558FBEB9 (purchase_id), INDEX IDX_6FA8ED7D40AEF4B9 (cursus_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lesson_validation ADD CONSTRAINT FK_1BA512BFA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE lesson_validation ADD CONSTRAINT FK_1BA512BFCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');
        $this->addSql('ALTER TABLE lesson_validation ADD CONSTRAINT FK_1BA512BF9B59827 FOREIGN KEY (purchase_item_id) REFERENCES purchase_item (id)');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_6117D13BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE purchase_item ADD CONSTRAINT FK_6FA8ED7D558FBEB9 FOREIGN KEY (purchase_id) REFERENCES purchase (id)');
        $this->addSql('ALTER TABLE purchase_item ADD CONSTRAINT FK_6FA8ED7D40AEF4B9 FOREIGN KEY (cursus_id) REFERENCES cursus (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson_validation DROP FOREIGN KEY FK_1BA512BFA76ED395');
        $this->addSql('ALTER TABLE lesson_validation DROP FOREIGN KEY FK_1BA512BFCDF80196');
        $this->addSql('ALTER TABLE lesson_validation DROP FOREIGN KEY FK_1BA512BF9B59827');
        $this->addSql('ALTER TABLE purchase DROP FOREIGN KEY FK_6117D13BA76ED395');
        $this->addSql('ALTER TABLE purchase_item DROP FOREIGN KEY FK_6FA8ED7D558FBEB9');
        $this->addSql('ALTER TABLE purchase_item DROP FOREIGN KEY FK_6FA8ED7D40AEF4B9');
        $this->addSql('DROP TABLE lesson_validation');
        $this->addSql('DROP TABLE purchase');
        $this->addSql('DROP TABLE purchase_item');
    }
}
