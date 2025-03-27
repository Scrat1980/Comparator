<?php

namespace app\models;

use app\records\MassOrderDiscount;
use app\records\MassOrderProduct;
use app\records\Order;
use app\records\OrderPackage;
use app\records\OrderProduct;
use app\records\User;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class MassOrderProductEditor extends Model
{
    public $mass_order_discount_id;

    /**
     * @return array
     */
    public function rules()
    {
        return [
            ['mass_order_discount_id', 'required']
        ];
    }

    public function returnToOrder()
    {
        $massOrderDiscount = MassOrderDiscount::findOne($this->mass_order_discount_id);

        $orderProducts = OrderProduct::find()
            ->innerJoinWith('orderPackage')
            ->where(['op.external_order_id' => $massOrderDiscount->external_number])
            ->andWhere(['order_product.status' => [OrderProduct::STATUS_IN_BASKET, OrderProduct::STATUS_REDEEMED]])
            ->andWhere(['NOT IN','op.status',[OrderPackage::STATUS_ERROR_NOT_REDEEMED, OrderPackage::STATUS_ERROR_NOT_AVAILABLE,
                OrderPackage::STATUS_REFUND_MONEY, OrderPackage::STATUS_MONEY_RETURNED]])
            ->all();

        $this->updateOrderProducts($orderProducts);
    }
    public function returnToRedeemOrder()
    {
        $massOrderDiscount = MassOrderDiscount::findOne($this->mass_order_discount_id);

        $orderProducts = OrderProduct::find()
            ->innerJoinWith('orderPackage')
            ->where(['op.external_order_id' => $massOrderDiscount->external_number])
            ->andWhere(['order_product.status' => [OrderProduct::STATUS_IN_BASKET, OrderProduct::STATUS_REDEEMED]])
            ->andWhere(['NOT IN','op.status',[OrderPackage::STATUS_REDEEMED_TRACK_NUMBER, OrderPackage::STATUS_ERROR_NOT_REDEEMED, OrderPackage::STATUS_ERROR_NOT_AVAILABLE,
                OrderPackage::STATUS_REFUND_MONEY, OrderPackage::STATUS_MONEY_RETURNED]])
            ->all();

        $this->updateOrderProducts($orderProducts);
    }

    public function returnToSeparatedOrder()
    {
        $massOrderDiscount = MassOrderDiscount::findOne($this->mass_order_discount_id);
        $moProduct = MassOrderProduct::find()->where(['mass_order_discount_id' => $this->mass_order_discount_id])->asArray()->all();
        $orderId = array_unique(ArrayHelper::getColumn($moProduct,'order_id'));

        $orderProducts = OrderProduct::find()
            ->innerJoinWith('orderPackage')
            ->where(['op.external_order_id' => $massOrderDiscount->external_number])
            ->andWhere(['order_product.status' => [OrderProduct::STATUS_IN_BASKET, OrderProduct::STATUS_REDEEMED]])
            ->andWhere(['NOT IN','op.status',[OrderPackage::STATUS_ERROR_NOT_REDEEMED, OrderPackage::STATUS_ERROR_NOT_AVAILABLE,
                OrderPackage::STATUS_REFUND_MONEY, OrderPackage::STATUS_MONEY_RETURNED]])
            ->andWhere(['op.order_id' => $orderId])
            ->all();

        $this->updateOrderProducts($orderProducts);
    }

    /**
     * @param $orderProducts
     * @return void
     */
    private function updateOrderProducts($orderProducts)
    {
        $orderPackages = [];
        $orders = [];

        $user = User::findOne(\Yii::$app->user->id);

        /* @var OrderProduct $orderProduct */
        foreach ($orderProducts as $orderProduct){
            $orderProduct->status = OrderProduct::STATUS_NEW;
            $orderProduct->save();
            /* @var OrderPackage $orderPackage */
            $orderPackage = $orderProduct->orderPackage;
            $order = $orderProduct->order;
            if(array_search($orderPackage->id, $orderPackages) === false){
                $orderPackage->status = OrderPackage::STATUS_NEW;
                $orderPackage->external_order_id = null;
                $orderPackage->tracking_number = null;
                $orderPackage->endredeem_at = null;
                $orderPackage->redeemtrack_at = null;
                $orderPackage->save();
                $orderPackages[] = $orderPackage->id;
                $orderPackage->addComment('Пользователь %s (id:%s) вернул товары на выкуп через mass-order (id:%s)',
                    $user->username, $user->id, $this->mass_order_discount_id);
            }
            if(array_search($order->id, $orders) === false){
                $order->status = Order::STATUS_STARTED_PROCESSING;
                $order->endredeem_at = null;
                $order->redeemtrack_at = null;
                $order->save();
                $orders[] = $order->id;
            }
        }
    }
}