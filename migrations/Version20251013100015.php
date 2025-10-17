<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013100015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE feeds (
                id INT AUTO_INCREMENT NOT NULL,
                title VARCHAR(500) NOT NULL,
                url TEXT NOT NULL,
                source VARCHAR(50) NOT NULL,
                updated_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                image_url VARCHAR(1000) DEFAULT NULL,
                INDEX idx_source_created (source, created_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE feeds');
    }
}
