<?php

use yii\base\NotSupportedException;
use yii\db\Migration;

class m250326_152018_create_table_cache extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%cache}}', [
            'id' => $this->primaryKey(),
            'letter_text' => $this->getDb()->getSchema()
                ->createColumnSchemaBuilder('mediumtext'),
            'hash' => $this->string(256),
            'file_name' => $this->string(256),
            'comment' => $this->string(256),
        ]);
        $this->createTable('{{%cache_tags}}', [
            'id' => $this->primaryKey(),
            'cache_id' => $this->integer(),
            'tags_id' => $this->integer(),
        ]);
        $this->createTable('{{%tags}}', [
            'id' => $this->primaryKey(),
            'tag' => $this->integer(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%cache}}');
        $this->dropTable('{{%cache_tags}}');
        $this->dropTable('{{%tags}}');
    }
}
