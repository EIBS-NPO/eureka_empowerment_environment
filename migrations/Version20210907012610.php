<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210907012610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE following_project');
        $this->addSql('ALTER TABLE user ADD activation_token VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE following_project (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, follower_id INT NOT NULL, is_following TINYINT(1) NOT NULL, is_assigning TINYINT(1) NOT NULL, INDEX IDX_904BF0FCAC24F853 (follower_id), INDEX IDX_904BF0FC166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE following_project ADD CONSTRAINT FK_904BF0FC166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE following_project ADD CONSTRAINT FK_904BF0FCAC24F853 FOREIGN KEY (follower_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user DROP activation_token');
    }
}
