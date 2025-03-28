<?php

use yii\db\Migration;

class m250328_111037_create_table_order_package_product extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%order_package_product}}', [
            'id' => $this->primaryKey(),
            'order_package_id' => $this->integer()->null(),
            'order_product_id' => $this->integer()->null(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%order_package_product}}');
    }
}
