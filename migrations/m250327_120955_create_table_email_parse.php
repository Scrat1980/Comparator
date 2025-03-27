<?php

use yii\db\Migration;

class m250327_120955_create_table_email_parse extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('email_parse', [
            'id' => $this->primaryKey(),
            'hash' => $this->string()->null(),
            'tracking_number' => $this->string()->null(),
            'external_order_id' => $this->string()->null(),
            'market_id' => $this->integer()->null(),
            'eml' => $this->text()->null(),
            'result_data' => $this->text()->null(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->createIndex('hash', 'email_parse', 'hash', false);
    }

    public function safeDown(): void
    {
        $this->dropTable('email_parse');
    }
}
