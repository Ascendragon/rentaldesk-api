<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031140429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE rental_order
        ALTER COLUMN created_at
              TYPE TIMESTAMP(0) WITH TIME ZONE
              USING created_at AT TIME ZONE 'UTC'
              ");

        $this->addSql("
        ALTER TABLE rental_order_item
        ALTER COLUMN starts_at
        TYPE TIMESTAMP(0) WITH TIME ZONE
        USING starts_at AT TIME ZONE 'UTC'
    ");

        $this->addSql("
        ALTER TABLE rental_order_item
        ALTER COLUMN ends_at
        TYPE TIMESTAMP(0) WITH TIME ZONE
        USING ends_at AT TIME ZONE 'UTC'
    ");

    }

    public function down(Schema $schema): void
    {
        $this->addSql("
        ALTER TABLE rental_order_item
        ALTER COLUMN ends_at
        TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        USING ends_at AT TIME ZONE 'UTC'
    ");

        $this->addSql("
        ALTER TABLE rental_order_item
        ALTER COLUMN starts_at
        TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        USING starts_at AT TIME ZONE 'UTC'
    ");

        $this->addSql("
        ALTER TABLE rental_order
        ALTER COLUMN created_at
        TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    ");

    }
}
