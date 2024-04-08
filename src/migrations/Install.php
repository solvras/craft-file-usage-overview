<?php

namespace solvras\craftcraftfileusageoverview\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableName = '{{%file-usage-overview_redactor-relations}}';

        if (!$this->db->tableExists($tableName)) {
            $this->createTable($tableName, [
                'id' => $this->primaryKey(),
                'entryId' => $this->integer()->notNull(),
                'assetId' => $this->integer()->notNull(),
                'entrySiteId' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, $tableName, ['entryId']);
            $this->createIndex(null, $tableName, ['assetId']);
            $this->addForeignKey(null, $tableName, ['entryId'], '{{%entries}}', ['id'], 'CASCADE');
            $this->addForeignKey(null, $tableName, ['assetId'], '{{%assets}}', ['id'], 'CASCADE');
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%file-usage-overview_redactor-relations}}');
    }
}
