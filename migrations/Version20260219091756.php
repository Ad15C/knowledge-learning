<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219091756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lesson_validated (id INT AUTO_INCREMENT NOT NULL, validated_at DATETIME NOT NULL, completed TINYINT(1) NOT NULL, user_id INT NOT NULL, lesson_id INT NOT NULL, purchase_item_id INT DEFAULT NULL, INDEX IDX_90BA2299A76ED395 (user_id), INDEX IDX_90BA2299CDF80196 (lesson_id), INDEX IDX_90BA22999B59827 (purchase_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lesson_validated ADD CONSTRAINT FK_90BA2299A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE lesson_validated ADD CONSTRAINT FK_90BA2299CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');
        $this->addSql('ALTER TABLE lesson_validated ADD CONSTRAINT FK_90BA22999B59827 FOREIGN KEY (purchase_item_id) REFERENCES purchase_item (id)');
        $this->addSql('ALTER TABLE lesson_validation DROP FOREIGN KEY FK_1BA512BF9B59827');
        $this->addSql('ALTER TABLE lesson_validation DROP FOREIGN KEY FK_1BA512BFA76ED395');
        $this->addSql('ALTER TABLE lesson_validation DROP FOREIGN KEY FK_1BA512BFCDF80196');
        $this->addSql('DROP TABLE lesson_validation');
        $this->addSql('ALTER TABLE certification ADD type VARCHAR(20) NOT NULL, ADD theme_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE certification ADD CONSTRAINT FK_6C3C6D7559027487 FOREIGN KEY (theme_id) REFERENCES theme (id)');
        $this->addSql('CREATE INDEX IDX_6C3C6D7559027487 ON certification (theme_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lesson_validation (id INT AUTO_INCREMENT NOT NULL, validated_at DATETIME NOT NULL, completed TINYINT(1) NOT NULL, user_id INT NOT NULL, lesson_id INT NOT NULL, purchase_item_id INT DEFAULT NULL, INDEX IDX_1BA512BFCDF80196 (lesson_id), INDEX IDX_1BA512BFA76ED395 (user_id), INDEX IDX_1BA512BF9B59827 (purchase_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE lesson_validation ADD CONSTRAINT FK_1BA512BF9B59827 FOREIGN KEY (purchase_item_id) REFERENCES purchase_item (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lesson_validation ADD CONSTRAINT FK_1BA512BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lesson_validation ADD CONSTRAINT FK_1BA512BFCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lesson_validated DROP FOREIGN KEY FK_90BA2299A76ED395');
        $this->addSql('ALTER TABLE lesson_validated DROP FOREIGN KEY FK_90BA2299CDF80196');
        $this->addSql('ALTER TABLE lesson_validated DROP FOREIGN KEY FK_90BA22999B59827');
        $this->addSql('DROP TABLE lesson_validated');
        $this->addSql('ALTER TABLE certification DROP FOREIGN KEY FK_6C3C6D7559027487');
        $this->addSql('DROP INDEX IDX_6C3C6D7559027487 ON certification');
        $this->addSql('ALTER TABLE certification DROP type, DROP theme_id');
    }
}
