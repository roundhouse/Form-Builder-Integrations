<?php

namespace roundhouse\formbuilderintegrations\migrations;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

class Install extends Migration
{
    // Public Properties
    // =========================================================================

    public $driver;

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;

        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        // Refresh the db schema caches
        Craft::$app->db->schema->refresh();

        // $this->insertDefaultData();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Create tables
     */
    protected function createTables()
    {
//        $this->createTable('{{%formbuilder_integrations_plans}}', [
//            'id' => $this->primaryKey(),
//            'dateCreated' => $this->dateTime()->notNull(),
//            'dateUpdated' => $this->dateTime()->notNull(),
//            'uid' => $this->uid(),
//        ]);
//
//        $this->createTable('{{%formbuilder_integrations_subscriptions}}', [
//            'id' => $this->primaryKey(),
//            'dateCreated' => $this->dateTime()->notNull(),
//            'dateUpdated' => $this->dateTime()->notNull(),
//            'uid' => $this->uid(),
//        ]);

        $this->createTable('{{%formbuilder_integrations_payments}}', [
            'id' => $this->primaryKey(),
            'integrationId' => $this->integer()->notNull(),
            'entryId' => $this->integer(),
            'amount' => $this->string(),
            'currency' => $this->string(),
            'last4' => $this->string(),
            'status' => $this->string(),
            'metadata' => $this->mediumText(),
            'errorCode' => $this->string(),
            'errorMessage' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Create indexes
     */
    protected function createIndexes()
    {
        $this->createIndex($this->db->getIndexName('{{%formbuilder_integrations_payments}}', 'integrationId', false), '{{%formbuilder_integrations_payments}}', 'integrationId', false);

        // Additional commands depending on the db driver
        switch ($this->driver) {
            case DbConfig::DRIVER_MYSQL:
                break;
            case DbConfig::DRIVER_PGSQL:
                break;
        }
    }

    /**
     * Add foreign keys
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey($this->db->getForeignKeyName('{{%formbuilder_integrations_payments}}', 'integrationId'), '{{%formbuilder_integrations_payments}}', 'integrationId', '{{%formbuilder_integrations}}', 'id', null, null);
    }

    /**
     * Remove tables
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%formbuilder_integrations_payments}}');
    }

    protected function insertDefaultData()
    {

    }
}