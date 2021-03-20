<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210301171549 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, creator_id INT NOT NULL, project_id INT DEFAULT NULL, organization_id INT DEFAULT NULL, is_public TINYINT(1) NOT NULL, title VARCHAR(50) NOT NULL, post_date DATE NOT NULL, summary LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', picture_path VARCHAR(255) DEFAULT NULL, dtype VARCHAR(255) NOT NULL, INDEX IDX_AC74095A61220EA6 (creator_id), INDEX IDX_AC74095A166D1F9C (project_id), INDEX IDX_AC74095A32C8A3DE (organization_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activity_user (activity_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_8E570DDB81C06096 (activity_id), INDEX IDX_8E570DDBA76ED395 (user_id), PRIMARY KEY(activity_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activity_file (id INT NOT NULL, uniq_id VARCHAR(13) NOT NULL, file_type VARCHAR(50) NOT NULL, checksum VARCHAR(255) NOT NULL, size INT NOT NULL, filename VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, address VARCHAR(255) NOT NULL, complement VARCHAR(255) DEFAULT NULL, country VARCHAR(30) NOT NULL, zip_code VARCHAR(10) NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, owner_type VARCHAR(15) NOT NULL, city VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE events (id INT AUTO_INCREMENT NOT NULL, actor_id INT DEFAULT NULL, target INT NOT NULL, target_type VARCHAR(20) NOT NULL, description VARCHAR(255) NOT NULL, date DATETIME NOT NULL, INDEX IDX_5387574A10DAF24A (actor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE following_project (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, follower_id INT NOT NULL, is_following TINYINT(1) NOT NULL, is_assigning TINYINT(1) NOT NULL, INDEX IDX_904BF0FC166D1F9C (project_id), INDEX IDX_904BF0FCAC24F853 (follower_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE global_property_attribute (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, property_key VARCHAR(50) NOT NULL, property_value LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', scope VARCHAR(10) NOT NULL, description VARCHAR(255) NOT NULL, INDEX IDX_A3A9722EA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, referent_id INT NOT NULL, address_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, name VARCHAR(50) NOT NULL, email VARCHAR(50) NOT NULL, phone VARCHAR(13) DEFAULT NULL, picture_path VARCHAR(255) DEFAULT NULL, description LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', is_partner TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_C1EE637C5E237E06 (name), INDEX IDX_C1EE637C35E47E35 (referent_id), UNIQUE INDEX UNIQ_C1EE637CF5B7AF75 (address_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, creator_id INT NOT NULL, organization_id INT DEFAULT NULL, title VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, picture_path VARCHAR(255) DEFAULT NULL, description LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', INDEX IDX_2FB3D0EE61220EA6 (creator_id), INDEX IDX_2FB3D0EE32C8A3DE (organization_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, address_id INT DEFAULT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', firstname VARCHAR(50) NOT NULL, lastname VARCHAR(50) NOT NULL, email VARCHAR(50) NOT NULL, phone VARCHAR(13) DEFAULT NULL, mobile VARCHAR(13) DEFAULT NULL, password VARCHAR(255) NOT NULL, picture_path VARCHAR(255) DEFAULT NULL, is_disabled TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D649F5B7AF75 (address_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_organization (user_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_41221F7EA76ED395 (user_id), INDEX IDX_41221F7E32C8A3DE (organization_id), PRIMARY KEY(user_id, organization_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A61220EA6 FOREIGN KEY (creator_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDB81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity_file ADD CONSTRAINT FK_8F5BED82BF396750 FOREIGN KEY (id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A10DAF24A FOREIGN KEY (actor_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE following_project ADD CONSTRAINT FK_904BF0FC166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE following_project ADD CONSTRAINT FK_904BF0FCAC24F853 FOREIGN KEY (follower_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE global_property_attribute ADD CONSTRAINT FK_A3A9722EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C35E47E35 FOREIGN KEY (referent_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE61220EA6 FOREIGN KEY (creator_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE user_organization ADD CONSTRAINT FK_41221F7EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_organization ADD CONSTRAINT FK_41221F7E32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_user DROP FOREIGN KEY FK_8E570DDB81C06096');
        $this->addSql('ALTER TABLE activity_file DROP FOREIGN KEY FK_8F5BED82BF396750');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CF5B7AF75');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649F5B7AF75');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A32C8A3DE');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE32C8A3DE');
        $this->addSql('ALTER TABLE user_organization DROP FOREIGN KEY FK_41221F7E32C8A3DE');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A166D1F9C');
        $this->addSql('ALTER TABLE following_project DROP FOREIGN KEY FK_904BF0FC166D1F9C');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A61220EA6');
        $this->addSql('ALTER TABLE activity_user DROP FOREIGN KEY FK_8E570DDBA76ED395');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574A10DAF24A');
        $this->addSql('ALTER TABLE following_project DROP FOREIGN KEY FK_904BF0FCAC24F853');
        $this->addSql('ALTER TABLE global_property_attribute DROP FOREIGN KEY FK_A3A9722EA76ED395');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C35E47E35');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE61220EA6');
        $this->addSql('ALTER TABLE user_organization DROP FOREIGN KEY FK_41221F7EA76ED395');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE activity_user');
        $this->addSql('DROP TABLE activity_file');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE following_project');
        $this->addSql('DROP TABLE global_property_attribute');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_organization');
    }
}
