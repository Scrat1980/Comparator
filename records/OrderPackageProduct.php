<?php

namespace app\records;

use yii\db\ActiveRecord;

/**
 * Order Package Product Record
 *
 * @property int $order_package_id
 * @property int $order_product_id
 */
class OrderPackageProduct extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'order_package_product';
    }

    /**
     * @inheritdoc
     * @return OrderPackageProductQuery
     */
    public static function find()
    {
        return new OrderPackageProductQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['order_package_id', 'order_product_id'], 'required'],
            [['order_package_id', 'order_product_id'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'order_package_id' => 'Order Package ID',
            'order_product_id' => 'Order Product ID',
        ];
    }
}
