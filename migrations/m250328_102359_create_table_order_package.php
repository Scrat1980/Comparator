<?php

use yii\db\Migration;

class m250328_102359_create_table_order_package extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%order_package}}', [
            'id' => $this->primaryKey(),
            'order_id' => $this->integer()->null(),
            'external_order_id' => $this->string(255)->null(),
            'tracking_number' => $this->string(255)->null(),
            'sf_tracking_number' => $this->string(255)->null(),
            'sf_package_id' => $this->integer()->null(),
            'sf_recipient_id' => $this->integer()->null(),
            'sf_shipment_id' => $this->integer()->null(),
            'sf_address_id' => $this->integer()->null(),
            'sf_spp_id' => $this->integer()->null(),
            'sf_shipment_status' => $this->integer()->null(),
            'buyout_id' => $this->integer()->null(),
            'status' => $this->integer()->null(),
            'refund_payment_id' => $this->integer()->null(),
            'total_price_cost_usd' => $this->float(2)->null(),
            'total_price_customer' => $this->float(2)->null(),
            'total_price_customer_usd' => $this->float(2)->null(),
            'total_price_customer_real_usd' => $this->float(2)->null(),
            'total_price_buyout_usd' => $this->float(2)->null(),
            'real_usd_rate' => $this->float(2)->null(),
            'internal_usd_rate' => $this->float(2)->null(),
            'delivery_cost_buyout_usd' => $this->float(2)->null(),
            'total_cost_buyout_usd' => $this->float(2)->null(),
            'bankcard_number' => $this->integer()->null(),
            'dictionary_shopfans_addresses_id' => $this->integer()->null(),
            'startredeem_at' => $this->integer()->null(),
            'inbasket_at' => $this->integer()->null(),
            'delivery_at' => $this->integer()->null(),
            'redeemtrack_at' => $this->integer()->null(),
            'endredeem_at' => $this->integer()->null(),
            'created_at' => $this->integer()->null(),
        ]);

    }

    public function safeDown()
    {
        $this->dropTable('{{%order_package}}');
    }

}
