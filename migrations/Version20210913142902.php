<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210913142902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP TABLE following');
        $this->addSql('ALTER TABLE following_project DROP FOREIGN KEY FK_904BF0FC166D1F9C');
        $this->addSql('DROP INDEX IDX_904BF0FC166D1F9C ON following_project');
        $this->addSql('ALTER TABLE following_project CHANGE project_id object_id INT NOT NULL');
        $this->addSql('ALTER TABLE following_project ADD CONSTRAINT FK_904BF0FC232D562B FOREIGN KEY (object_id) REFERENCES project (id)');
        $this->addSql('CREATE INDEX IDX_904BF0FC232D562B ON following_project (object_id)');
        $this->addSql('ALTER TABLE user ADD activation_token VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE following (id INT AUTO_INCREMENT NOT NULL, object_id INT NOT NULL, follower_id INT NOT NULL, is_following TINYINT(1) NOT NULL, is_assigning TINYINT(1) NOT NULL, INDEX IDX_71BF8DE3AC24F853 (follower_id), INDEX IDX_71BF8DE3232D562B (object_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE following ADD CONSTRAINT FK_71BF8DE3232D562B FOREIGN KEY (object_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE following ADD CONSTRAINT FK_71BF8DE3AC24F853 FOREIGN KEY (follower_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('ALTER TABLE following_project DROP FOREIGN KEY FK_904BF0FC232D562B');
        $this->addSql('DROP INDEX IDX_904BF0FC232D562B ON following_project');
        $this->addSql('ALTER TABLE following_project CHANGE object_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE following_project ADD CONSTRAINT FK_904BF0FC166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('CREATE INDEX IDX_904BF0FC166D1F9C ON following_project (project_id)');
        $this->addSql('ALTER TABLE user DROP activation_token');
    }
}
