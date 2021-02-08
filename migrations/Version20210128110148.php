<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210128110148 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, creator_id INT NOT NULL, project_id INT DEFAULT NULL, organization_id INT DEFAULT NULL, is_public TINYINT(1) NOT NULL, title VARCHAR(50) NOT NULL, description VARCHAR(255) NOT NULL, post_date DATE NOT NULL, summary VARCHAR(255) NOT NULL, illustration VARCHAR(255) DEFAULT NULL, INDEX IDX_AC74095A61220EA6 (creator_id), INDEX IDX_AC74095A166D1F9C (project_id), INDEX IDX_AC74095A32C8A3DE (organization_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A61220EA6 FOREIGN KEY (creator_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE activity');
    }
}
