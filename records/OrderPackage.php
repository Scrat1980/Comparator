<?php

namespace app\records;

use app\helpers\EventProxy;
use app\payment\FailedRefundingEvent;
use app\payment\inner\Gateway as InnerGateway;
use app\payment\SuccessfulRefundingEvent;
use app\shopfans\OrderPackageStatusDelivery;
use app\telegram\Events;
use app\telegram\TelegramQueueJob;
use Shopfans\Api\UserApi;
use Shopfans\Api\UserApi as ShopfansApi;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Order Package Record
 *
 * @property int $id
 * @property int $order_id
 * @property string $external_order_id
 * @property string $tracking_number
 * @property string $sf_tracking_number
 * @property int $sf_package_id
 * @property int $sf_recipient_id
 * @property int $sf_shipment_id
 * @property int $sf_address_id
 * @property int $sf_spp_id
 * @property int $sf_shipment_status
 * @property int $buyout_id
 * @property int $status
 * @property null|int $refund_payment_id
 * @property double $total_price_cost_usd
 * @property double $total_price_customer
 * @property double $total_price_customer_usd
 * @property double $total_price_customer_real_usd
 * @property double $total_price_buyout_usd
 * @property double $delivery_cost_buyout_usd
 * @property double $total_cost_buyout_usd
 * @property double $real_usd_rate
 * @property double $internal_usd_rate
 * @property int $bankcard_number
 * @property int $dictionary_shopfans_addresses_id
 * @property int $startredeem_at
 * @property int $inbasket_at
 * @property int $delivery_at
 * @property int $endredeem_at
 * @property int $redeemtrack_at
 * @property int $created_at
 *
 * @property int $checked
 *
 * Relations:
 * @property OrderProduct[] $packageProducts
 * @property Order $order
 * @property DictionaryShopfansAddresses $dictionaryShopfansAddresse
 * @property null|OrderComment $comment
 * @property null|Payment $refundPayment
 * @property null|RefundedOrderPackage $refundedOrderPackage
 */
class OrderPackage extends ActiveRecord
{
    //Новый
    const STATUS_NEW = 1;
    //Начата обработка
    const STATUS_STARTED_PROCESSING = 2;
    //В корзине
    const STATUS_IN_BASKET = 3;
    //Адрес доставки введен
    const STATUS_DELIVERY = 4;
    //Выкуплено
    const STATUS_REDEEMED = 5;
    //Выкуплено с трекномером
    const STATUS_REDEEMED_TRACK_NUMBER = 8;

    //Ошибка, не выкуплено
    const STATUS_ERROR_NOT_REDEEMED = 6;
    //Ошибка (нет в наличии)
    const STATUS_ERROR_NOT_AVAILABLE = 7;
    //Вернуть деньги клиенту
    const STATUS_REFUND_MONEY = 9;
    //Деньги клиетну возвращены
    const STATUS_MONEY_RETURNED = 10;

    //статусы заказов при возврате товаров из России
    //Деньги возвращены клиенту полностью (за package)
    const STATUS_REFUNDED_RO = 205;
    //Деньги возвращены клиенту частично (за часть package)
    const STATUS_REFUNDED_PARTIAL_RO = 206;

    //Признак для повторного выкупа package
    const SIGN_AGAIN_REDEEM = 777;

    public $checked = 0;

    /**
     * @event OrderPackageShipmentEvent an event that is triggered when shipment sent from warehouse to Moscow
     */
    const EVENT_SHIPMENT_SHIPPED = 'order-shipment-shipped';

    /**
     * @event OrderPackageShipmentEvent an event that is triggered once shipment arrived to customs at Moscow
     */
    const EVENT_SHIPMENT_ARRIVED_CUSTOMS = 'order-shipment-arrived-customs';

    /**
     * @event OrderPackageShipmentEvent an event that is triggered when shipment delivered to pickup point
     */
    const EVENT_SHIPMENT_DELIVERED = 'order-shipment-delivered';

    /**
     * @event OrderPackageCanceledEvent an event triggered when order package can't be redeemed
     */
    const EVENT_CANCELED = 'order-package-canceled';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'order_package';
    }

    /**
     * @inheritdoc
     */
    public function formName()
    {
        return '';
    }

    /**
     * @inheritdoc
     * @return OrderPackageQuery
     */
    public static function find()
    {
        return new OrderPackageQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
//            'timestamp' => [
//                'class' => TimestampBehavior::class,
//                'attributes' => [
//                    self::EVENT_BEFORE_INSERT => 'created_at',
//                ],
//            ],
//            'events' => [
//                'class' => EventProxy::class,
//                'map' => [
//                    static::EVENT_SHIPMENT_SHIPPED,
//                    static::EVENT_SHIPMENT_ARRIVED_CUSTOMS,
//                    static::EVENT_SHIPMENT_DELIVERED,
//                    static::EVENT_CANCELED,
//                ],
//            ],
//            'audit' => AuditBehavior::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['order_id'], 'required'],
            ['tracking_number', 'trim'],
            [['order_id', 'sf_package_id', 'sf_recipient_id', 'sf_shipment_id', 'sf_address_id', 'buyout_id', 'status', 'dictionary_shopfans_addresses_id', 'startredeem_at', 'inbasket_at', 'delivery_at', 'redeemtrack_at', 'endredeem_at', 'created_at', 'sf_spp_id', 'sf_shipment_status'], 'integer'],
            [['total_price_cost_usd', 'total_price_customer', 'total_price_customer_usd', 'total_price_buyout_usd', 'delivery_cost_buyout_usd', 'total_cost_buyout_usd', 'total_price_customer_real_usd', 'real_usd_rate', 'internal_usd_rate', 'bankcard_number' ], 'number'],
            [['external_order_id', 'sf_tracking_number', 'tracking_number'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'order_id' => 'Order ID',
            'external_order_id' => 'External Order ID',
            'tracking_number' => 'Tracking Number',
            'sf_tracking_number' => 'SF Tracking Number',
            'sf_package_id' => 'Sf Package ID',
            'sf_recipient_id' => 'Sf Recipient ID',
            'sf_shipment_id' => 'Sf Shipment ID',
            'sf_address_id' => 'Sf Address ID',
            'sf_spp_id' => 'Sf Spp ID',
            'sf_shipment_status' => 'Sf Shipment Status',
            'buyout_id' => 'Buyout ID',
            'status' => 'Status',
            'total_price_cost_usd' => 'Total Price Cost Usd',
            'total_price_customer' => 'Total Price Customer',
            'total_price_customer_usd' => 'Total Price Customer Usd',
            'total_price_buyout_usd' => 'Total Price Buyout Usd',
            'delivery_cost_buyout_usd' => 'Delivery Cost Buyout Usd',
            'total_cost_buyout_usd' => 'Total Cost Buyout Usd',
            'dictionary_shopfans_addresses_id' => 'Dictionary Shopfans Addresses ID',
            'startredeem_at' => 'Startredeem At',
            'inbasket_at' => 'Inbasket At',
            'delivery_at' => 'Delivery At',
            'endredeem_at' => 'Endredeem At',
            'redeemtrack_at' => 'Redeemtrack At',
            'created_at' => 'Created At',
        ];
    }

    /**
     * @return OrderProductQuery|\yii\db\ActiveQuery
     */
    public function getPackageProducts()
    {
        return $this->hasMany(OrderProduct::class, ['id' => 'order_product_id'])
            ->viaTable('order_package_product', ['order_package_id' => 'id']);
    }

    /**
     * @return OrderQuery|\yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDictionaryShopfansAddresse()
    {
        return $this->hasOne(DictionaryShopfansAddresses::class, ['id' => 'dictionary_shopfans_addresses_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getComment()
    {
        return $this->hasMany(OrderComment::class, ['entity_id' => 'id'])->where(['entity_type' => OrderComment::TYPE_PACKAGE]);
    }

    /**
     * @return PaymentQuery|\yii\db\ActiveQuery
     */
    public function getRefundPayment()
    {
        return $this->hasOne(Payment::class, ['id' => 'refund_payment_id']);
    }

    /**
     * @return PaymentQuery|\yii\db\ActiveQuery
     */
    public function getRefundedOrderPackage()
    {
        return $this->hasOne(RefundedOrderPackage::class, ['order_package_id' => 'id']);
    }


    /**
     * @inheritdoc
     */
    public function fields()
    {
        return [
            'id',
            'order_id',
            'external_order_id',
            'sf_tracking_number' => function () {
                return $this->sf_tracking_number ? preg_replace('/^P0+/', '', $this->sf_tracking_number) : '';
            },
            'sf_package_id',
            'sf_recipient_id',
            'sf_shipment_id',
            'sf_address_id',
            'total_price_customer',
            'total_price_customer_rub' => function () {
                return $this->total_price_customer;
            },
            'status',
//            'timeLine',
            'created_at',

            'packageProducts',
        ];
    }

    /**
     * @return array|int
     * TODO Разобраться для чего array|int
     */
    public function getCheckStatus()
    {
        //Если package в статусе Не выкуплен или нет в наличии
//        if (in_array($this->status, [OrderPackage::STATUS_ERROR_NOT_REDEEMED, OrderPackage::STATUS_ERROR_NOT_AVAILABLE])) {
//            return [];
//        }

        if ($this->status == self::STATUS_REFUND_MONEY) {
            return self::STATUS_MONEY_RETURNED;
        }

        //Если package в статусе В корзине
        if ($this->status == OrderPackage::STATUS_IN_BASKET) {
            return OrderPackage::STATUS_DELIVERY;
        }

        //Если package в статусе Адрес доставки введен
        if ($this->status == OrderPackage::STATUS_DELIVERY) {
            return OrderPackage::STATUS_REDEEMED;
        }

        if (in_array($this->order->status, [Order::STATUS_NEW, Order::STATUS_PAYMENT_FAIL, Order::STATUS_MONEY_RETURNED]) !== false ||
            $this->status == self::STATUS_MONEY_RETURNED) {
            return Order::FLAG_NO_ACTION;
        }

        if (in_array($this->status, [self::STATUS_ERROR_NOT_REDEEMED, self::STATUS_ERROR_NOT_AVAILABLE, self::STATUS_REDEEMED, self::STATUS_REDEEMED_TRACK_NUMBER])) {
            if (($this->refund_payment_id !== null && $this->refundPayment->status_code === Payment::STATUS_REFUNDED_PARTIAL) ||
                ($this->order->refund_payment_id !== null)) {
                return [];
            }
            return self::SIGN_AGAIN_REDEEM;
        }

        return [];
    }

    /**
     * @return bool
     */
    public function getValidateForRedeem()
    {
        if (!$this->external_order_id) {
            return false;
        }
        if(!$this->order->customer->isSp()) {
            foreach ($this->packageProducts as $packageProduct) {
                if (empty($packageProduct->declaration_sfx_id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array
     * TODO В модели не должно быть презентационной логики
     */
    public function classAlert()
    {
        return [
            Order::STATUS_NEW => 'warning',
            Order::STATUS_STARTED_PROCESSING => 'success',
            Order::STATUS_IN_BASKET => 'success',
            Order::STATUS_DELIVERY => 'info указан',
            Order::STATUS_REDEEMED => 'info',
            Order::STATUS_REDEEMED_TRACK_NUMBER => 'info',
            Order::STATUS_ERROR_NOT_REDEEMED => 'danger',
            Order::STATUS_ERROR_NOT_AVAILABLE => 'danger',
            Order::STATUS_REFUND_MONEY => 'danger',
            Order::STATUS_MONEY_RETURNED => 'success',
            Order::STATUS_REFUNDED_RO => 'success',
            Order::STATUS_REFUNDED_PARTIAL_RO => 'success',
        ];
    }

    public function classAlertMassOrderHistory()
    {
        $classAlert = $this->classAlert();
        $classAlert[Order::STATUS_REDEEMED] = 'warning';

        return $classAlert;
    }

    /**
     * @return array
     */
    public function textStatus()
    {
        return [
            Order::STATUS_NEW => 'Новая',
            Order::STATUS_STARTED_PROCESSING => 'Начали обработку',
            Order::STATUS_IN_BASKET => 'В корзине',
            Order::STATUS_DELIVERY => 'Адрес указан',
            Order::STATUS_REDEEMED => 'Выкуплено',
            Order::STATUS_REDEEMED_TRACK_NUMBER => 'Выкуплено с треком',
            Order::STATUS_ERROR_NOT_REDEEMED => 'Ошибка (не смогли выкупить)',
            Order::STATUS_ERROR_NOT_AVAILABLE => 'Ошибка (нет в наличии)',
            Order::STATUS_REFUND_MONEY => 'Инициирован возврат денег',
            Order::STATUS_MONEY_RETURNED => 'Деньги возвращены',
            Order::STATUS_REFUNDED_RO => 'Деньги возвращены(refunded)',
            Order::STATUS_REFUNDED_PARTIAL_RO => 'Деньги возвращены частично(refunded)',
        ];
    }

    /**
     * @return string
     */
    public function getClassAlert()
    {
        return $this->classAlert()[$this->status];
    }


    /**
     * @return string
     */
    public function getClassAlertMassOrderHistory()
    {
        return $this->classAlertMassOrderHistory()[$this->status];
    }

    /**
     * @return string
     */
    public function getTextStatus()
    {
        return $this->textStatus()[$this->status];
    }

    /**
     * Возвращает статус доставки
     * @return mixed
     */
    public function getStatusDelivery()
    {
        return Yii::$app->cache->getOrSet('package_status_delivery_' . $this->id, function () {
            $orderPackageStatusDelivery = new OrderPackageStatusDelivery($this);
            return $orderPackageStatusDelivery->getStatusDelivery();
        }, getenv('TIME_CACHE_TIMELINE'));
    }

    /**
     * Метод нужен для админки чтобы не вызывалась ошибка и показать заказ
     * @return array|int[]|mixed|string
     */
    public function getStatusDeliveryWithoutError()
    {
        try {
            return $this->getStatusDelivery();
        } catch (\Exception $exception) {
            return ['description' => $exception->getMessage()];
        }
    }


    /**
     * TimeLine
     * @return mixed
     */
    public function getTimeline()
    {
        return Yii::$app->cache->getOrSet('package_timeline_' . $this->id, function () {
            $orderPackageStatusDelivery = new OrderPackageStatusDelivery($this);
            return $orderPackageStatusDelivery->getTimeLine();
        }, getenv('TIME_CACHE_TIMELINE'));
    }

    /**
     * Возвращает true если package можно перевести в статус ошибки,
     * тогда и только тогда, если все статусы товаров package с ошибкой
     * Возвращает false, если у package не все товары в статусе ошибка или таких вообще нет
     * @return bool
     */
    public function checkErrorStatus()
    {

        $packageProduct = $this->getPackageProducts()->all();

        if ($packageProduct == null) {
            return false;
        }

        $countErrorProduct = 0;

        foreach ($packageProduct as $product) {
            if (in_array($product->status, [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE])) {
                $countErrorProduct++;
            }
        }

        if ($countErrorProduct == 0 || count($packageProduct) > $countErrorProduct) {
            return false;
        }

        return true;
    }

    public function updateStatusByProductStatus()
    {
        $statusData = [];
        $status = false;
        $flagAllRedeemed = true;
        $statusError = false;

        $errorStatus = [
            self::STATUS_ERROR_NOT_REDEEMED,
            self::STATUS_ERROR_NOT_AVAILABLE
        ];

        if ($this->status !== self::STATUS_REFUNDED_RO && $this->status !== self::STATUS_REFUNDED_PARTIAL_RO) {
            if ($this->refund_payment_id !== null) {
                $statusData = [self::STATUS_REFUND_MONEY => true];
                $status = self::STATUS_REFUND_MONEY;
                if ($this->refundPayment->status_code == Payment::STATUS_REFUNDED_PARTIAL) {
                    $statusData = [self::STATUS_MONEY_RETURNED => true];
                    $status = self::STATUS_MONEY_RETURNED;
                }
            } else {
                foreach ($this->getPackageProducts()->all() as $packageProduct) {
                    $statusData[$packageProduct->status] = true;
                    if (in_array($packageProduct->status, $errorStatus)) {
                        $statusError = $packageProduct->status;
                    } else {
                        $status = $packageProduct->status;
                    }
                }

                if(count($statusData) == 1 && $status == self::STATUS_NEW
                    && ($this->tracking_number !== null && $this->tracking_number !== '') === false) {
                    $status = self::STATUS_NEW;
                    $statusData = [self::STATUS_NEW => true];
                    $flagAllRedeemed = false;
                }

                if (count($statusData) == 1 && $status == self::STATUS_IN_BASKET) {
                    $this->dictionaryShopfansAddresse->dictionary_shopfans_addresses = 0;
                    $this->dictionaryShopfansAddresse->save();
                    $status = self::STATUS_DELIVERY;
                    $statusData = [self::STATUS_DELIVERY => true];
                }

                if(count($statusData) == 1 && $statusError){
                    $flagAllRedeemed = false;
                }

                //если в массиве два и больше статусов и хотя бы один из статусов ошибки есть в этом массиве,
                //то удаляем его и отправляем дальше на проверку.
                if (count($statusData) > 1) {
                    foreach ($statusData as $key => $item) {
                        if ($key != OrderProduct::STATUS_REDEEMED) {
                            $flagAllRedeemed = false;
                        }
                    }
                }

                //если все товары выкуплены
                if (count($statusData) >= 1 && $flagAllRedeemed) {
                    if ($this->external_order_id !== null) {
                        $statusData = [self::STATUS_REDEEMED => true];
                        $status = self::STATUS_REDEEMED;
                        if ($this->tracking_number !== null && $this->tracking_number !== '') {
                            $statusData = [self::STATUS_REDEEMED_TRACK_NUMBER => true];
                            $status = self::STATUS_REDEEMED_TRACK_NUMBER;
                        }
                    }
                }

                if ($this->refund_payment_id !== null) {
                    $statusData = [self::STATUS_REFUND_MONEY => true];
                    $status = self::STATUS_REFUND_MONEY;
                    if ($this->refundPayment->status_code == Payment::STATUS_REFUNDED_PARTIAL) {
                        $statusData = [self::STATUS_MONEY_RETURNED => true];
                        $status = self::STATUS_MONEY_RETURNED;
                    }
                }
            }

            if (count($statusData) == 1) {
                if ($status == false) {
                    $status = $statusError;
                }

                $endStatus = [
                    self::STATUS_REDEEMED,
                    self::STATUS_REDEEMED_TRACK_NUMBER,
                    self::STATUS_ERROR_NOT_REDEEMED,
                    self::STATUS_ERROR_NOT_AVAILABLE,
                ];

//                end status
//                self::STATUS_REFUND_MONEY,
//                self::STATUS_MONEY_RETURNED

                $allStatus = array_merge($endStatus, [
                    self::STATUS_IN_BASKET,
                    self::STATUS_DELIVERY,
                    self::STATUS_REFUND_MONEY,
                    self::STATUS_MONEY_RETURNED,
                    self::STATUS_NEW
                ]);

                if (in_array($status, $endStatus)) {
                    $this->endredeem_at = time();
                    $this->save(false);
                }

                if (in_array($status, $allStatus)) {
                    $this->status = $status;
                    $this->save(false);
                }
            }
        }
    }

    /**
     * @param $totalPrice
     * @param bool $full
     */
    public function refundMoney($totalPrice, $full = true)
    {
        if ($totalPrice <= 0) {
            $totalPrice = $this->total_price_customer;
        }
        $totalRefundPrice = -100 * $totalPrice;

        if(Yii::$app instanceof yii\console\Application){
            $user = User::findOne(User::BOT_ACCOUNT);
        } else {
            $user = User::findOne(Yii::$app->user->id);
            if(empty($user)){
                $user = User::findOne(User::BOT_ACCOUNT);
            }
        }

        // if source payment exists
        if ($payment = $this->order->payment)
        {
            // reuse uid and gateway of source payment
            $uid = $payment->uid . '-' . substr(time(), -5);
            $gatewayCode = $payment->gateway_code;
            $gatewayName = "через шлюз: ({$payment->gateway_code})";

        }
        else
        {
            // compose uid by order id and use first available gateway
            $uid = $this->order_id . '-' . substr(time(), -5);
            $gateways = Yii::$app->payment->gateways;
            $gatewayCode = key($gateways);
            $gatewayName = "через шлюз: ({$payment->gateway_code})";
        }

//        раскоментить если надо будет отключить возврат на личный счёт
//        if(!empty($this->order->second_payment_id) || $this->order->customer->isAllowInnerPayment()){
//            // добавляем возвраты на внутренний счёт только если есть платёж с внутреннего счёта
//            // или пользователь в белом списке
//            $gatewayCode = 'inner';
//        }
        $gatewayCode = 'inner'; // всё возвращаем на личный счёт
        $gatewayName = "на личный счёт (inner)";

        // ищем похожие по сумме возвраты за последние 10 минут
        $enableSlaves = Yii::$app->getDb()->enableSlaves;
        Yii::$app->getDb()->enableSlaves = false;
        $sameRefunds = Payment::find()
            ->where('created_at > :date
                AND amount=:amount
                AND status_code IN (:new_refund, :refunded, :partial_refunded)',
                [
                    ':date' => time() - 10 * 60,
                    ':amount' => $totalRefundPrice,
                    ':new_refund' => Payment::STATUS_NEW_REFUND,
                    ':refunded' => Payment::STATUS_REFUNDED,
                    ':partial_refunded' => Payment::STATUS_REFUNDED_PARTIAL,
                ])
            ->all();

        Yii::$app->getDb()->enableSlaves = $enableSlaves;
        // проверяем, есть ли среди найденных возвратов, идентичный новому
        foreach ($sameRefunds as $refundPayment) {
            if (@$refundPayment->tx_data['order_package_id'] == $this->id
                && @$refundPayment->tx_data['order_id'] == $this->order_id) {
                Yii::$app->session->setFlash('warning', $message = sprintf(
                    'Reject refund money request for order package %s, ' .
                    'because the same one has been found, see payment %s',
                    $this->id, $refundPayment->id));

                Yii::error($message = sprintf(
                    'Reject refund money request for order package %s, ' .
                        'because the same one has been found, see payment %s',
                    $this->id, $refundPayment->id));
                TelegramQueueJob::push($message, '!!!ALARM Duplicated Refunding',
                        Events::getInnerPaymentWithdrawLogsChatId());
                return false;
            }
        }

        $refundPayment = new Payment([
            'gateway_code' => $gatewayCode,
            'uid' => $uid,
            'amount' => $totalRefundPrice,
            'tx_data' => [
                'order_id'         => $this->order_id,
                'order_package_id' => $this->id,
                'payment_id'       => $this->order->payment_id,
                'user_id'          => $user->id,
                'description'      => 'Возврат денежных средств клиенту за часть заказа' . $this->order_id .
                    ' package ' . $this->id . ' администратором: ' . $user->username . '(' . $user->id . ')',
            ],
            'status_code' => Payment::STATUS_NEW_REFUND
        ]);

        if ($refundPayment->save()) {
            if ($full) { // обновление статусов order_package если возврат ручной
                foreach ($this->getPackageProducts()->all() as $product) {
                    $product->status = OrderProduct::STATUS_ERROR_NOT_REDEEMED;
                    $product->save();
                }
            }
            $this->refund_payment_id = $refundPayment->id;
            $this->save();

            $this->order->link('orderPayments', $refundPayment);

            $this->addComment('Пользователь %s (id:%s) инициировал возврата платежа(%s) на сумму %s руб. %s',
                $user->username, $user->id, $refundPayment->id, $totalPrice, $gatewayName);

            $this->updateStatusByProductStatus();
            $this->order->updateStatusByPackageStatus();

            if ($this->isAllowedAutoRefund($refundPayment->amount, true))
            {
                $gateway = Yii::$app->payment->getGateway($gatewayCode);
                if ($gateway && $gateway->cancelPayment($refundPayment))
                {
                    $this->successReturnMoney(true);

                    Yii::$app->trigger(SuccessfulRefundingEvent::class, new SuccessfulRefundingEvent([
                        'order_id'         => $this->order_id,
                        'order_package_id' => $this->id,
                        'amount'           => round($refundPayment->amount / 100, 2),
                        'payment_id'       => $refundPayment->id,
                    ]));
                }
                else
                {
                    $this->addComment($message =
                        'Не удалось выполнить автоматический возврат средств из-за отказа платёжного шлюза.');

                    Yii::$app->trigger(FailedRefundingEvent::class, new FailedRefundingEvent([
                        'order_id'         => $this->order_id,
                        'order_package_id' => $this->id,
                        'message'          => $message,
                        'amount'           => round($refundPayment->amount / 100, 2),
                        'payment_id'       => $refundPayment->id,
                    ]));
                }
            }
            $this->order->checkPartialShippingRefund();
        }
    }

    /**
     * Returns TRUE if order package can be automatically refunded, FALSE otherwise
     *
     * @param int $amount amount to refund, the same as in payment.amount
     * @param bool $commentDecision whether failed decision must be commented in order_comment
     *
     * @return bool
     */
    public function isAllowedAutoRefund($amount = 0, $commentDecision = false)
    {
        //11138 - сверить разницу между функцией на продакш и в основной ветке внутреннего счета
        $allowed = true;
        $decision = '';
        // don't let refund money if order package is not in valid status
        $allowedStatus = [static::STATUS_NEW, static::STATUS_STARTED_PROCESSING, static::STATUS_IN_BASKET,
            static::STATUS_DELIVERY, static::STATUS_REDEEMED, static::STATUS_REDEEMED_TRACK_NUMBER,
            static::STATUS_REFUND_MONEY];

        if ($amount > 0
            && Setting::enabled('AUTO_REFUNDING_THRESHOLD')
            && ($amountThreshold = intval(trim(Setting::value('AUTO_REFUNDING_THRESHOLD', '0')))) >= 0
            && $amount > $amountThreshold * 100)
        {
            $allowed = false;
            $decision = sprintf(
                'Сумма к возврату %s в заказе %s превышает максимально допустимую сумму возврата %s.',
                round($amount / 100, 2), $this->id, $amountThreshold);
        }
        else if (!in_array($this->status, $allowedStatus))
        {
            $allowed = false;
            $decision = sprintf('Статус части заказа (%s) не позволяет выполнить автоматический возврат (%s)',
                $this->id, $this->status);
        }
        // don't let refund money if order doesn't paid
        else if (!$this->order->isPaid())
        {
            $allowed = false;
            $decision = 'Автоматический возврат невозможен, пока заказ не оплачен';
        }
        else if ((-1 * $amount) > $this->order->getOrderRemainSum())
        {
            $allowed = false;
            $decision = sprintf('Автоматический возврат суммы %s руб. невозможен, так как сумма возврата больше, чем сумма доступная для возврата (%s руб.)',
                $amount / 100, $this->order->getOrderRemainSum() / 100);
        }
        // don't let refund money if there is no shipment in Shopfans
        else if (!$this->sf_shipment_id)
        {
            $allowed = false;
            $decision = sprintf(
                'Автоматически возврат невозможен из-за отсутствия посылки в шопфансе для части заказа (%s)',
                $this->id);
        }
        // don't let refund money for sent shipments
        else
        {
            $shipmentStatus = $this->order->customer->shopfans->getShipmentStatusCode($this->sf_shipment_id);
            if (!$shipmentStatus || $shipmentStatus >= ShopfansApi::STATUS_SHIPPED)
            {
                $allowed = false;
                $decision = sprintf(
                    'Статус посылки (%s) не позволяет выполнить автоматический возврат для части заказа (%s)',
                    $shipmentStatus, $this->id);
            };
        }

        if (!$allowed && $commentDecision && $decision)
        {
            $this->addComment($decision);

            Yii::$app->trigger(FailedRefundingEvent::class, new FailedRefundingEvent([
                'order_id'         => $this->order_id,
                'order_package_id' => $this->id,
                'message'          => $decision,
                'amount'           => round($amount / 100, 2),
            ]));
        }

        return $allowed;
    }

    public function successReturnMoney($automated = false)
    {
        if (Yii::$app instanceof yii\console\Application) {
            $user = User::findOne(User::BOT_ACCOUNT);
        } else {
            $user = User::findOne(Yii::$app->user->id);
            if(empty($user)){
                $user = User::findOne(User::BOT_ACCOUNT);
            }
        }
        $refundPayment = $this->refundPayment;
        if ($refundPayment->gateway_code === 'inner') {
            InnerGateway::fixReturnMoneyForPackage($this);
        }

        $refundPayment->status_code = Payment::STATUS_REFUNDED_PARTIAL;
        $refundPayment->finished_at = time();

        if ($refundPayment->save()) {
            $this->addComment($automated
                ? 'Возврат за часть заказ был выполнен в автоматическом режиме'
                : sprintf('Пользователь %s (id:%s) подтвердил успешность возврата платежа(%s) за часть заказа',
                    $user->username, $user->id, $refundPayment->id));
            $this->updateStatusByProductStatus();
            $this->order->updateStatusByPackageStatus();
        }
    }

    /**
     * Обновляем данные в shopfans, указываем номер заказ в магазине
     *
     * @param $externalOrderId
     * @throws \yii\base\InvalidConfigException
     */
    public function updateShopfansPackage($externalOrderId, $update = true)
    {
        if ($update) {
            $this->external_order_id = $externalOrderId;
            $this->save();
        }

        /** @var ShopfansApi $shopfans */
        $shopfans = $this->order->customer->shopfans;

        $packageSf = $shopfans->getPackageById($this->sf_package_id);
        if ($packageSf) {
            $shopfans->updatePackage(
                $this->sf_package_id,
                $packageSf['store'],
                $externalOrderId,
                $packageSf['tracking_number'],
                $packageSf['name'],
                $packageSf['weight'],
                $packageSf['notes']
            );
        }
    }

    /**
     * Tracks changes of status attribute to catch the moment order package is canceled and trigger event.
     *
     * @param bool  $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

//        if (isset($changedAttributes['status'])) {
//            switch ($this->status) {
//                case static::STATUS_ERROR_NOT_REDEEMED:
//                case static::STATUS_ERROR_NOT_AVAILABLE:
//                    $this->refundMoney(-1, false);
//                    break;
//            }
//        }

        if (isset($changedAttributes['status']) && in_array($this->status, static::getStatusesOfCancel()))
        {
            $this->trigger(static::EVENT_CANCELED, new OrderPackageCanceledEvent([
                'oldStatus' => $changedAttributes['status'],
            ]));
        }

        if (isset($changedAttributes['status']) && $this->status == self::STATUS_REDEEMED_TRACK_NUMBER && empty($this->redeemtrack_at))
        {
            $this->redeemtrack_at = time();
            $this->save(false);
        }

        if (isset($changedAttributes['status']) && $this->status == static::STATUS_DELIVERY) {

        }
    }

    /**
     * Returns list of statuses that assumes redeem is canceled.
     *
     * @return int[]
     */
    public static function getStatusesOfCancel()
    {
        return [static::STATUS_ERROR_NOT_REDEEMED, static::STATUS_ERROR_NOT_AVAILABLE,
            static::STATUS_REFUND_MONEY, static::STATUS_MONEY_RETURNED];
    }

    /**
     * Очистить трек-номер в пекадже, чтобы бот или
     * администратор смогли внести правильный трек-номер
     * @throws \yii\db\Exception
     */
    public function clearTrackingNumber()
    {
        if (Yii::$app->user->can(\app\Access::ORDER_CORRECT)) {
            $this->tracking_number = '';
            $this->save();

            $user = User::findOne(Yii::$app->user->id);
            $this->addComment('Пользователь %s (id:%s) удалил трек-номер', $user->username, $user->id);

            $this->updateStatusByProductStatus();
            $this->order->updateStatusByPackageStatus();
        }
    }

    /**
     * Adds comments about this order_package to order_comment.
     *
     * Method applies sprintf() function and could be used in same way to embed data into comments.
     *
     * @param string $text text with comments
     *
     * @return bool TRUE on success, FALSE otherwise
     */
    public function addComment($text)
    {
        if (func_num_args() > 1) $text = sprintf(...func_get_args());

        if(Yii::$app instanceof yii\console\Application){
            $user = User::findOne(User::BOT_ACCOUNT);
        } else {
            $user = User::findOne(Yii::$app->user->id);
        }

        if (!$user) {
            $user = User::findOne(User::BOT_ACCOUNT);
        }

        $comment = new OrderComment();
        $comment->user_id = $user->id;
        $comment->entity_id = $this->id;
        $comment->entity_type = OrderComment::TYPE_PACKAGE;
        $comment->text = $text;

        return $comment->save();
    }

    /**
     * @param $modId
     * @return OrderPackageQuery
     */
    public static function getPackageByMod($modId)
    {
        $orderPackageQuery = new self();
        $orderPackages = $orderPackageQuery->find()
            ->joinWith('packageProducts')
            ->innerJoin(['mop' => MassOrderProduct::tableName()], '{{mop}}.[[order_product_id]] = {{order_product}}.[[id]]')
            ->where(['mop.mass_order_discount_id' => $modId]);

        return $orderPackages;
    }

    /**
     * выборку делаем теперь по аккаунту привязанному к масс ордеру
     *
     * @param $marketId
     * @param $userId
     * @return OrderPackageQuery
     */
    public static function getPackageWithoutTrackNumber($marketId, $userId = null)
    {
        $limitOrderId = 400000;
        if (Setting::enabled(Setting::ORDER_ID_LIMIT_BY_MARKET)) {
            $limits = Setting::value(Setting::ORDER_ID_LIMIT_BY_MARKET);
            if (isset($limits[$marketId])) {
                $limitOrderId = $limits[$marketId];
            } else if ($limits['all']) {
                $limitOrderId = $limits['all'];
            }
        }

        $orderPackage =  new self();
        $query = $orderPackage->find()
            ->joinWith('packageProducts')
            ->where(['order_product.product_market_id' => $marketId])
            ->andWhere(['op.status' => self::STATUS_REDEEMED])
            ->andWhere(['>=', 'op.order_id', $limitOrderId])
            ->orderBy('op.id ASC');

        if($userId){
            $query->innerJoin(['modis' => MassOrderDiscount::tableName()], '{{modis}}.[[external_number]] = {{op}}.[[external_order_id]]')
                ->andWhere(['modis.account' => $userId]);
        }

        return $query;
    }

    /**
     * Использовать, если надо сделать package
     * доступным для выкупа и заменить или заново создать
     * данные в shopfans
     * @return void
     */
    public function linkShopfans()
    {
        $api = $this->order->customer->shopfans;
        $shopfansService = Yii::$app->shopfans;

        $throwExceptions = $api->throwExceptions;
        $api->throwExceptions = false;

        $oldSfPackageId = $this->sf_package_id;
        $this->sf_package_id = null;
        $this->sf_shipment_id = null;
        $this->sf_tracking_number = null;

        $packageProducts = $this->packageProducts;
        $package = $shopfansService->createPackage($this->order->customer, $this);
        if ($package && isset($package['id'])) {
            $this->sf_package_id = $package['id'];
            try {
                $text = "Добавлен sf_package_id={$package['id']} при создании пекеджа в шопфансе";
                if(!empty($oldSfPackageId)){
                    $text .= " (заменён с {$oldSfPackageId})";
                }
                $this->addComment($text);
                $text .= " (OrderPackage.php:1076)";
                TelegramQueueJob::push($text, 'Change sf_package_id', Events::getSfPackageIdChangeChatId());
                Yii::info($text, 'requests-shopfans');
            } catch (\Exception $e) {
            }
            $shopfansService->replaceAllDeclarationForPackage($this->order->customer, $this);
        } else {
            try {
                $text = "Добавлен sf_package_id=null при создании пекеджа в шопфансе";
                if(!empty($oldSfPackageId)){
                    $text .= " (заменён с {$oldSfPackageId})";
                }
                $this->addComment($text);
                $text .= " (OrderPackage.php:1070)";
                TelegramQueueJob::push($text, 'Change sf_package_id', Events::getSfPackageIdChangeChatId());
                Yii::info($text, 'requests-shopfans');
            } catch (\Exception $e) {
            }
        }
        if (!$this->sf_shipment_id && $this->sf_package_id) {
            $shipment = $shopfansService->createShipment($this->order->customer, $this);
            if ($shipment && isset($shipment['id'])) {
                $this->sf_shipment_id = $shipment['id'];
                $this->sf_tracking_number = $shipment['tracking_number'];
            }
        }

        if ($this->getDirtyAttributes() && !$this->save(false)) {
            Yii::error(sprintf(
                'Can not update order package with %s due to error: %s',
                http_build_query($this->getDirtyAttributes()),
                print_r($this->getErrors(), true)),
                \Shopfans\Api\UserApi::class);
        }

        $api->throwExceptions = $throwExceptions;
    }

    /**
     * @param $unique
     * @return bool|int|string|null
     */
    public function getTotalQuantityPackageProduct($unique = false)
    {
        if ($unique) {
            return $this->getPackageProducts()->count();
        }

        $totalQuantity = 0;

        /** @var OrderProduct $orderProduct */
        foreach ($this->packageProducts as $orderProduct) {
            $totalQuantity += $orderProduct->quantity;
        }

        return $totalQuantity;
    }

    public function isNeedSplitPackage($productIds, $productIdsWithCount){

        if(count($productIds) != $this->getPackageProducts()->count()){
            // не совпадает количество товаров - надо делить
            return true;
        }
        foreach ($this->packageProducts as $orderProduct) {
            if(isset($productIdsWithCount[$orderProduct->id])){
                if($orderProduct->quantity != $productIdsWithCount[$orderProduct->id]){
                    return true;
                }
            }
        }
        return false;
    }

    public function getMarketId()
    {
        return $this->getPackageProducts()->one()->product_market_id;
    }
}
