<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210921115804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jwt_refresh_token ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE jwt_refresh_token ADD CONSTRAINT FK_9F3D9535A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F3D9535A76ED395 ON jwt_refresh_token (user_id)');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649F765F60E');
        $this->addSql('DROP INDEX UNIQ_8D93D649F765F60E ON user');
        $this->addSql('ALTER TABLE user DROP refresh_token_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jwt_refresh_token DROP FOREIGN KEY FK_9F3D9535A76ED395');
        $this->addSql('DROP INDEX UNIQ_9F3D9535A76ED395 ON jwt_refresh_token');
        $this->addSql('ALTER TABLE jwt_refresh_token DROP user_id');
        $this->addSql('ALTER TABLE user ADD refresh_token_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649F765F60E FOREIGN KEY (refresh_token_id) REFERENCES jwt_refresh_token (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F765F60E ON user (refresh_token_id)');
    }
}
