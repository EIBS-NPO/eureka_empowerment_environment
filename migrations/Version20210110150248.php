<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210110150248 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organization ADD referent_id INT NOT NULL');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C35E47E35 FOREIGN KEY (referent_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_C1EE637C35E47E35 ON organization (referent_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C35E47E35');
        $this->addSql('DROP INDEX IDX_C1EE637C35E47E35 ON organization');
        $this->addSql('ALTER TABLE organization DROP referent_id');
    }
}
