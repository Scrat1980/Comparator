<?php

use yii\db\Migration;

class m250327_142839_create_table_mass_order_discount extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%mass_order_discount}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->null(),
            'market_id' => $this->integer()->null(),
            'external_number' => $this->string(255)->null(),
            'discount' => $this->float(2)->null(),
            'account' => $this->string(255)->null(),
            'delivery_cost_usd' => $this->float(2)->null(),
            'created_at' => $this->integer()->null(),
        ]);

        $this->insert('{{%mass_order_discount}}', [
            'user_id' => 1,
            'market_id' => 32,
            'external_number' => 555,
            'discount' => 0,
            'created_at' => null,
        ]);

    }

    public function safeDown(): void
    {
        $this->dropTable('{{%mass_order_discount}}');
    }
}
