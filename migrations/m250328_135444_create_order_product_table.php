<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%order_product}}`.
 */
class m250328_135444_create_order_product_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%order_product}}', [
            'id' => $this->primaryKey(),
            'name_for_declaration' => $this->string(256)->null(),
            'declaration_sfx_id' => $this->integer()->null(),
            'order_id' => $this->integer()->null(),
            'product_id' => $this->integer()->null(),
            'product_variant_id' => $this->integer()->null(),
            'product_market_id' => $this->integer()->null(),
            'status' => $this->integer()->null(),
            'quantity' => $this->integer()->null(),
            'price_cost_usd' => $this->float(2)->null(),
            'price_customer' => $this->float(2)->null(),
            'price_customer_usd' => $this->float(2)->null(),
            'price_customer_real_usd' => $this->float(2)->null(),
            'price_buyout_usd' => $this->float(2)->null(),
            'total_price_cost_usd' => $this->float(2)->null(),
            'total_price_customer' => $this->float(2)->null(),
            'total_price_customer_usd' => $this->float(2)->null(),
            'total_price_customer_real_usd' => $this->float(2)->null(),
            'total_price_buyout_usd' => $this->float(2)->null(),
            'real_usd_rate' => $this->float(2)->null(),
            'ernal_usd_rate' => $this->float(2)->null(),
            'promocode_id' => $this->integer()->null(),
            'promocode_personal_id' => $this->integer()->null(),
            'promocode_personal_name_code' => $this->string(255)->null(),
            'promocode_full_price_customer' => $this->float(2)->null(),
            'promocode_discount_customer' => $this->float(2)->null(),
            'startredeem_at' => $this->integer()->null(),
            'endredeem_at' => $this->integer()->null(),
            'currency_price' => $this->float(2)->null(),
            'currency_rate' => $this->float(2)->null(),
            'currency_code' => $this->string(255)->null(),
        ]);
        
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%order_product}}');
    }
}
