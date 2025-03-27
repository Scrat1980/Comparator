<?php

namespace app\records;

use app\audit\AuditBehavior;
use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "mass_order_product".
 *
 * @property int $id
 * @property int $mass_order_discount_id
 * @property int $order_id
 * @property int $order_product_id
 * @property int $quantity
 * @property int $created_at
 *
 * @property MassOrderDiscount $massOrderDiscount
 * @property OrderProduct $orderProduct
 * @property Order $order
 */
class MassOrderProduct extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'mass_order_product';
    }

    /**
     * @inheritdoc
     * @return MassOrderProductQuery|\yii\db\ActiveQuery
     */
    public static function find()
    {
        return new MassOrderProductQuery(get_called_class());
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    self::EVENT_BEFORE_INSERT => 'created_at',
                ],
            ],
//            'audit' => AuditBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mass_order_discount_id', 'order_id', 'order_product_id'], 'required'],
            [['mass_order_discount_id', 'order_id', 'order_product_id', 'created_at'], 'integer'],
            [['quantity'], 'integer', 'min' => 1],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mass_order_discount_id' => 'Mass Order Discount ID',
            'order_id' => 'Order ID',
            'order_product_id' => 'Order Product ID',
            'quantity' => 'Quantity',
            'created_at' => 'Created At',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMassOrderDiscount()
    {
        return $this->hasOne(MassOrderDiscount::class, ['id' => 'mass_order_discount_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrderProduct()
    {
        return $this->hasOne(OrderProduct::class, ['id' => 'order_product_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    /**
     * @return float|int
     */
    public function getTotalPrice()
    {
        $totalPrice = $this->orderProduct->price_cost_usd * $this->quantity;

        return $totalPrice;
    }
}
