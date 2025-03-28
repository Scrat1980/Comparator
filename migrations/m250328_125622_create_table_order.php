<?php

use yii\db\Migration;

class m250328_125622_create_table_order extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%order}}', [
            'id' => $this->primaryKey(),
            'external_number' => $this->string(255)->null(),
            'responsible_user_id' => $this->integer()->null(),
            'sf_recipient_id' => $this->integer()->null(),
            'customer_id' => $this->integer()->null(),
            'status' => $this->integer()->null(),
            'payment_id' => $this->integer()->null(),
            'second_payment_id' => $this->integer()->null(),
            'refund_payment_id' => $this->integer()->null(),
            'total_price_cost_usd' => $this->float(2)->null(),
            'total_price_customer' => $this->float(2)->null(),
            'total_price_customer_usd' => $this->float(2)->null(),
            'total_price_customer_real_usd' => $this->float(2)->null(),
            'total_price_buyout_usd' => $this->float(2)->null(),
            'real_usd_rate' => $this->float(2)->null(),
            'internal_usd_rate' => $this->float(2)->null(),
            'total_delivery_cost_buyout_usd' => $this->float(2)->null(),
            'total_cost_buyout_usd' => $this->float(2)->null(),
            'ga' => $this->string(255)->null(),
            'mobile' => $this->integer()->null(),
            'startredeem_at' => $this->integer()->null(),
            'inbasket_at' => $this->integer()->null(),
            'delivery_at' => $this->integer()->null(),
            'redeemtrack_at' => $this->integer()->null(),
            'endredeem_at' => $this->integer()->null(),
            'created_at' => $this->integer()->null(),
            'delivery_cost_customer' => $this->float(2)->null(),
            'delivery_cost_customer_usd' => $this->float(2)->null(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%order}}');
    }

}
