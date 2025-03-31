<?php

use yii\db\Migration;

class m250331_101238_create_table_customer extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%customer}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(256)->null(),
            'first_name' => $this->string(256)->null(),
            'last_name' => $this->string(256)->null(),
            'middle_name' => $this->string(256)->null(),
            'phone' => $this->string(256)->null(),
            'password_hash' => $this->string(256)->null(),
            'api_key' => $this->string(256)->null(),
            'email_shopfans' => $this->string(256)->null(),
            'password_shopfans' => $this->string(256)->null(),
            'created_at' => $this->integer()->null(),
            'updated_at' => $this->integer()->null(),
            'locked_at' => $this->string(256)->null(),
            'gender' => $this->string(256)->null(),
            'birthday' => $this->date()->null(),
            'referral_code' => $this->string(256)->null(),
            'referrer_id' => $this->integer()->null(),
            'referrer_code' => $this->string(256)->null(),
        ]);

        $this->insert('{{%customer}}', [
            'id' => 1,
            'first_name' => 'Vasya',
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%customer}}');
    }
}
