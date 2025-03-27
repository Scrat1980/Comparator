<?php

use yii\db\Migration;

class m250327_143056_create_table_mass_order_product extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%mass_order_product}}', [
            'id' => $this->primaryKey(),
            'mass_order_discount_id' => $this->integer()->null(),
            'order_id' => $this->integer()->null(),
            'quantity' => $this->integer()->null(),
            'order_product_id' => $this->integer()->null(),
            'created_at' => $this->integer()->null(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%mass_order_product}}');
    }
}
