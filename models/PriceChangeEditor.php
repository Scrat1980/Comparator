<?php

namespace app\models;

use app\records\Customer;
use app\records\LogOrderCartHash;
use app\records\OrderPackage;
use app\records\OrderProduct;
use app\records\Payment;
use app\records\User;
use yii\base\Model;

class PriceChangeEditor extends Model
{
    const SCENARIO_SEARCH = 'search';
    const SCENARIO_PERCENT = 'percent';
    const SCENARIO_PERCENT_MOD = 'percent_mod';

    public $encodeData;
    public $marketId;
    public $percent;
    public $massOrderDiscountId;

    public $decodeData;

    /**
     * @return array
     */
    public function rules()
    {
        return [
            ['encodeData', 'required', 'on' => static::SCENARIO_SEARCH],
            ['massOrderDiscountId', 'required', 'on' => static::SCENARIO_PERCENT_MOD],
            ['marketId', 'required'],
            ['encodeData', 'decodeData', 'on' => static::SCENARIO_SEARCH],
            ['percent', 'required', 'on' => [static::SCENARIO_PERCENT, static::SCENARIO_PERCENT_MOD]],
            ['percent', 'integer', 'max' => 90, 'min' => 0, 'on' => [static::SCENARIO_PERCENT, static::SCENARIO_PERCENT_MOD]],
        ];
    }

    public function decodeData()
    {
        $this->decodeData = json_decode(base64_decode($this->encodeData), true);

    }

    public function priceChangeByDecodeData()
    {
        $this->addLogOrderCartHash();

        $orderProducts = OrderProduct::getBuybackProduct($this->marketId)
            ->innerJoin(['c' => Customer::tableName()], '{{c}}.[[id]] = {{order}}.[[customer_id]]')
            ->andWhere('c.email_shopfans NOT LIKE "%@example.com"')
            ->all();

        $foundProducts = [];
        $usedProductData = [];

        if ($orderProducts) {
            \Yii::$app->markets->one($this->marketId)->searchOrderProductToChangePrice($this->decodeData, $orderProducts, $foundProducts, $usedProductData);
            $this->createRefundPayments($foundProducts, $response);
        }

        $response['response']['code'] = 200;
        $response['response']['message'] = 'success';

        return $response;
    }

    public function priceChangeByPromocode()
    {
        $this->addLogOrderPercent();

        $orderProducts = OrderProduct::getBuybackProduct($this->marketId)
            ->innerJoin(['c' => Customer::tableName()], '{{c}}.[[id]] = {{order}}.[[customer_id]]')
            ->andWhere('c.email_shopfans NOT LIKE "%@example.com"')
            ->all();

        $foundProducts = [];

        if ($orderProducts) {
            \Yii::$app->markets->one($this->marketId)->changePriceOrderProduct($this->percent, $orderProducts, $foundProducts);
            $this->createRefundPayments($foundProducts, $response);
        }

        $response['response']['code'] = 200;
        $response['response']['message'] = 'success';

        return $response;
    }

    public function priceChangeByPromocodeMod()
    {
        $this->addLogOrderPercent();

        $orderProducts = OrderProduct::find()
            ->innerJoinWith('massOrderProduct')
            ->innerJoinWith('order')
            ->innerJoin(['c' => Customer::tableName()], '{{c}}.[[id]] = {{order}}.[[customer_id]]')
            ->andWhere('c.email_shopfans NOT LIKE "%@example.com"')
            ->andWhere(['mass_order_discount_id' => $this->massOrderDiscountId])
            ->all();

        $foundProducts = [];

        if ($orderProducts) {
            \Yii::$app->markets->one($this->marketId)->changePriceOrderProduct($this->percent, $orderProducts, $foundProducts);
            $this->createRefundPayments($foundProducts, $response);
        }

        $response['response']['code'] = 200;
        $response['response']['message'] = 'success';

        return $response;
    }

    private function createRefundPayments($foundProducts, &$response)
    {
        $user = User::findOne(\Yii::$app->user->id);
        $response['response']['create'] = 0;
        $response['response']['notCreate'] = 0;

        foreach ($foundProducts as $orderProducts) {
            $totalDiffAmountAll = 0;
            $package = false;
            /* @var OrderProduct $orderProduct */
            foreach ($orderProducts as $orderProduct) {
                $newPriceSP = ($orderProduct->getNewPriceSP());
                if ($newPriceSP !== []) {
                    if (!$package) $package = $orderProduct->orderPackage;
                    $totalDiffAmount = $orderProduct->total_price_customer - $newPriceSP['total_new_price'];
                    $totalDiffAmountAll += $totalDiffAmount;

                    $message = sprintf('Товар: %s, цена после скидки в usd: %s, наценка(процент) %s, 
                        новая стоимость товара %s формула расчета: (%s * (1 + %s / 100), округление до 4 знаков)
                        курс %s, новая стоимость в рублях за 1 ед. товара %s (округление до 2 знаков). 
                        Итоговая сумма в рублях %s',
                        $orderProduct->id, $orderProduct->price_buyout_usd, $newPriceSP['price_markup'],
                        $newPriceSP['new_price_usd'], $orderProduct->price_buyout_usd, $newPriceSP['price_markup'],
                        $newPriceSP['currency_rate'], $newPriceSP['new_price'],
                        $newPriceSP['total_new_price']
                    );

                    $package->addComment($message);
                }
            }
            if ($totalDiffAmountAll > 0) {
                if ($this->createPayment($totalDiffAmountAll, $package, $user)) {
                    $response['response']['create']++;
                } else {
                    $response['response']['notCreate']++;
                }
            }
        }
    }

    private function createPayment($totalDiffAmount, $package, $user, $message = '')
    {
        $totalRefundPrice = -100 * $totalDiffAmount;
        // if source payment exists
        if ($payment = $package->order->payment) {
            // reuse uid and gateway of source payment
            $uid = $payment->uid . '-' . substr(time(), -5);
            $gatewayCode = $payment->gateway_code;
        } else {
            // compose uid by order id and use first available gateway
            $uid = $package->order_id . '-' . substr(time(), -5);
            $gateways = \Yii::$app->payment->gateways;
            $gatewayCode = key($gateways);
        }

        $refundPayment = new Payment([
            'gateway_code' => $gatewayCode,
            'uid' => $uid,
            'amount' => $totalRefundPrice,
            'tx_data' => [
                'order_id' => $package->order_id,
                'order_package_id' => $package->id,
                'payment_id' => $package->order->payment_id,
                'user_id' => $user->id,
                'description' => 'Возврат денежных средств клиенту за разницу в стоимости товаров после применения промокда при выкупе: ' . $package->order_id .
                    ' package ' . $package->id . ' администратором: ' . $user->username . '(' . $user->id . ')',
            ],
            'status_code' => Payment::STATUS_NEW_REFUND
        ]);

        if ($refundPayment->save()) {
            $package->order->link('orderPayments', $refundPayment);

            $package->addComment('Пользователь %s (id:%s) инициировал возврат (payment id %s) на сумму %s руб.', $user->username, $user->id, $refundPayment->id, $totalDiffAmount);

            return true;
        } else {
            return false;
        }
    }

    public function addLogOrderCartHash()
    {
        $record = new LogOrderCartHash();
        $record->hash_order_data = $this->encodeData;
        $record->created_at = time();
        $record->save();
    }

    public function addLogOrderPercent()
    {
        $record = new LogOrderCartHash();
        $record->hash_order_data = $this->percent;
        $record->created_at = time();
        $record->save();
    }

    /**
     * @param OrderPackage $orderPackage
     */
    public function createPaymentByPackageProducts(OrderPackage $orderPackage)
    {
        $packageProducts = $orderPackage->getPackageProducts()->indexBy('id')->all();
        $response = [];
        if(count($packageProducts) > 0){
            $this->createRefundPayments([$orderPackage->id => $packageProducts], $response);
        }
    }
}