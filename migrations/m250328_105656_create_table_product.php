<?php

use yii\db\Migration;

class m250328_105656_create_table_product extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product}}', [
            'id' => $this->primaryKey(),
            'market_id' => $this->integer()->null(),
            'remote_code' => $this->string(255)->null(),
            'origin_name' => $this->string(255)->null(),
            'origin_description' => $this->string(255)->null(),
            'name' => $this->string(255)->null(),
            'description' => $this->string(255)->null(),
            'description_updated_at' => $this->integer()->null(),
            '_variant_id' => $this->integer()->null(),
            '_category_id' => $this->integer()->null(),
            'market_brand_id' => $this->integer()->null(),
            'brand_id' => $this->integer()->null(),
            'gender_code' => $this->string(255)->null(),
            'first_parsing_id' => $this->integer()->null(),
            'last_parsing_id' => $this->integer()->null(),
            'is_lost' => $this->tinyInteger()->null(),
            'is_block' => $this->tinyInteger()->null(),
            'is_custom_category' => $this->tinyInteger()->null(),
            'is_custom_gender' => $this->tinyInteger()->null(),
            'is_custom_facet' => $this->tinyInteger()->null(),
            'disabled' => $this->tinyInteger()->null(),
            'created_at' => $this->integer()->null(),
            'losted_at' => $this->integer()->null(),
            'custom_category_at' => $this->integer()->null(),
            'custom_gender_at' => $this->integer()->null(),
            'blocked_at' => $this->integer()->null(),
            'updated_at' => $this->integer()->null(),
            'translated_at' => $this->integer()->null(),
            'approved_at' => $this->integer()->null(),
            'approved_by' => $this->integer()->null(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%product}}');
    }
}
