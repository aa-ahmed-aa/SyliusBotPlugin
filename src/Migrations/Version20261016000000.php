<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the 2.0 schema, or brings a 1.x schema up to it in place, keeping
 * connected pages and bot users.
 */
final class Version20261016000000 extends AbstractMigration
{
    private const BOT = 'sylius_bot_plugin_bot';

    private const SUBSCRIBER = 'sylius_bot_plugin_bot_subscriber';

    private const MESSAGE = 'sylius_bot_plugin_bot_message';

    public function getDescription(): string
    {
        return 'Sylius Bot plugin 2.0: Instagram accounts, conversation history, subscriber timestamps.';
    }

    public function preUp(Schema $schema): void
    {
        // 1.x allowed NULL here; 2.0 does not.
        if ($schema->hasTable(self::BOT) && $schema->getTable(self::BOT)->hasColumn('disabled')) {
            $this->connection->executeStatement(sprintf('UPDATE %s SET disabled = 0 WHERE disabled IS NULL', self::BOT));
        }
    }

    public function up(Schema $schema): void
    {
        $bot = $schema->hasTable(self::BOT) ? $schema->getTable(self::BOT) : $this->createIdTable($schema, self::BOT);

        $this->column($bot, 'page_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->column($bot, 'page_id', Types::STRING, ['length' => 191, 'notnull' => false]);
        $this->column($bot, 'page_access_token', Types::TEXT, ['notnull' => false]);
        $this->column($bot, 'page_image_url', Types::TEXT, ['notnull' => false]);
        $this->column($bot, 'channel_type', Types::STRING, ['length' => 32, 'notnull' => false]);
        $this->column($bot, 'facebook_page_id', Types::STRING, ['length' => 191, 'notnull' => false]);
        $this->column($bot, 'disabled', Types::BOOLEAN, ['notnull' => true, 'default' => 0]);
        $this->column($bot, 'persistent_menu', Types::TEXT, ['notnull' => false]);
        $this->column($bot, 'created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $this->column($bot, 'updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        foreach (['user_id', 'timezone'] as $unused) {
            if ($bot->hasColumn($unused)) {
                $bot->dropColumn($unused);
            }
        }
        if (!$bot->hasIndex('sylius_bot_plugin_bot_channel_page_idx')) {
            $bot->addUniqueIndex(['channel_type', 'page_id'], 'sylius_bot_plugin_bot_channel_page_idx');
        }

        $subscriber = $schema->hasTable(self::SUBSCRIBER) ? $schema->getTable(self::SUBSCRIBER) : $this->createIdTable($schema, self::SUBSCRIBER);

        $this->column($subscriber, 'bot_id', Types::INTEGER, ['notnull' => false]);
        $this->column($subscriber, 'customer_id', Types::INTEGER, ['notnull' => false]);
        $this->column($subscriber, 'bot_subscriber_id', Types::STRING, ['length' => 191, 'notnull' => true]);
        $this->column($subscriber, 'channel', Types::STRING, ['length' => 32, 'notnull' => true]);
        $this->column($subscriber, 'name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->column($subscriber, 'first_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->column($subscriber, 'last_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->column($subscriber, 'profile_picture', Types::TEXT, ['notnull' => false]);
        $this->column($subscriber, 'locale', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->column($subscriber, 'timezone', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->column($subscriber, 'gender', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->column($subscriber, 'created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $this->column($subscriber, 'updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);

        if (!$this->hasUniqueIndexOn($subscriber, ['bot_subscriber_id'])) {
            $subscriber->addUniqueIndex(['bot_subscriber_id']);
        }
        if (!$this->hasUniqueIndexOn($subscriber, ['customer_id'])) {
            $subscriber->addUniqueIndex(['customer_id']);
        }
        $this->foreignKey($subscriber, 'bot_id', self::BOT);
        // 1.x created this one without ON DELETE, which blocked deleting a customer.
        $this->foreignKey($subscriber, 'customer_id', 'sylius_customer');

        if (!$schema->hasTable(self::MESSAGE)) {
            $message = $this->createIdTable($schema, self::MESSAGE);
            $message->addColumn('subscriber_id', Types::INTEGER, ['notnull' => true]);
            $message->addColumn('direction', Types::STRING, ['length' => 3]);
            $message->addColumn('text', Types::TEXT);
            $message->addColumn('created_at', Types::DATETIME_MUTABLE);
            $message->addIndex(['subscriber_id', 'created_at'], 'sylius_bot_plugin_bot_message_subscriber_created_idx');
            $message->addForeignKeyConstraint(self::SUBSCRIBER, ['subscriber_id'], ['id'], ['onDelete' => 'CASCADE']);
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable(self::MESSAGE)) {
            $schema->dropTable(self::MESSAGE);
        }
    }

    private function createIdTable(Schema $schema, string $name): Table
    {
        $table = $schema->createTable($name);
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_unicode_ci');

        return $table;
    }

    /** @param array<string, mixed> $options */
    private function column(Table $table, string $name, string $type, array $options): void
    {
        if (!$table->hasColumn($name)) {
            $table->addColumn($name, $type, $options);

            return;
        }

        $column = $table->getColumn($name);
        $column->setType(Type::getType($type));
        $column->setNotnull((bool) ($options['notnull'] ?? true));
        $column->setLength($options['length'] ?? null);
        if (array_key_exists('default', $options)) {
            $column->setDefault($options['default']);
        }
    }

    /** @param string[] $columns */
    private function hasUniqueIndexOn(Table $table, array $columns): bool
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isUnique() && $index->getColumns() === $columns) {
                return true;
            }
        }

        return false;
    }

    /** Adds a SET NULL foreign key on $column, replacing one without that rule. */
    private function foreignKey(Table $table, string $column, string $foreignTable): void
    {
        foreach ($table->getForeignKeys() as $name => $foreignKey) {
            if ($foreignKey->getLocalColumns() !== [$column]) {
                continue;
            }

            if (strtoupper((string) $foreignKey->onDelete()) === 'SET NULL') {
                return;
            }

            $table->removeForeignKey($name);
        }

        $table->addForeignKeyConstraint($foreignTable, [$column], ['id'], ['onDelete' => 'SET NULL']);
    }
}
