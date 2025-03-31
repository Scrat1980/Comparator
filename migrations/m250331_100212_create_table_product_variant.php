<?php

use yii\db\Migration;

class m250331_100212_create_table_product_variant extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product_variant}}', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->null(),
            'remote_code' => $this->string(256)->null(),
            'upc' => $this->string(256)->null(),
            'remote_url' => $this->string(256)->null(),
            'image_id' => $this->integer()->null(),
            'description' => $this->string(256)->null(),
            'origin_color' => $this->string(256)->null(),
            'origin_size' => $this->string(256)->null(),
            'market_size_id' => $this->integer()->null(),
            'price' => $this->float()->null(),
            'price_full' => $this->integer()->null(),
            'discount' => $this->float()->null(),
            'stock_count' => $this->integer()->null(),
            'size_chart_name' => $this->string(256)->null(),
            'size_chart_id' => $this->integer()->null(),
            'created_at' => $this->integer()->null(),
            'losted_at' => $this->integer()->null(),
            'updated_at' => $this->integer()->null(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%product_variant}}');
    }
}
