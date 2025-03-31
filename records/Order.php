<?php

namespace app\records;

use Yii;
use yii\base\InvalidConfigException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Order Record
 *
 * @property int $id
 * @property null|string $external_number
 * @property null|int $responsible_user_id
 * @property null|int $sf_recipient_id
 * @property null|int $customer_id
 * @property null|int $status
 * @property null|int $payment_id
 * @property null|int $second_payment_id
 * @property null|int $refund_payment_id
 * @property null|float $total_price_cost_usd
 * @property null|float $total_price_customer
 * @property null|float $total_price_customer_usd
 * @property null|float $total_price_customer_real_usd
 * @property null|float $total_price_buyout_usd
 * @property null|float $total_delivery_cost_buyout_usd
 * @property null|float $total_cost_buyout_usd
 * @property null|float $real_usd_rate
 * @property null|float $internal_usd_rate
 * @property null|string $ga
 * @property null|int $mobile
 * @property null|int $startredeem_at
 * @property null|int $inbasket_at
 * @property null|int $delivery_at
 * @property null|int $redeemtrack_at
 * @property null|int $endredeem_at
 * @property int $created_at
 * @property null|float $delivery_cost_customer
 * @property null|float $delivery_cost_customer_usd
 *
 * @property int $checked
 * @property string $textStatus
 *
 * @property OrderProduct[] $orderProducts
 * @property OrderPackage[] $orderPackages
 * @property Product[] $product
 * @property Customer $customer
 * @property RefundedOrder $refundedOrder
 * @property Payment $payment
 * @property Payment $secondPayment
 * @property Payment $refundPayment
 * @property null|User $responsibleUser
 * @property null|OrderComment $comment
 * @property Payment[] $orderPayments
 */
class Order extends ActiveRecord
{
//    use AuditActiveRecordTrait;

    //Новый
    const STATUS_NEW = 1;
    //Не оплачен - ошибка
    const STATUS_PAYMENT_FAIL = 100;
    //Оплачен
    const STATUS_PAYMENT_SUCCESS = 101;
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
    const STATUS_REFUND_SHIPPING = 91;
    //Деньги клиенту возвращены
    const STATUS_MONEY_RETURNED = 10;

    //статусы заказов при возврате товаров из России
    //Деньги возвращены клиенту полностью (за заказ)
    const STATUS_REFUNDED_RO = 205;
    //Деньги возвращены клиенту частично (за часть заказа)
    const STATUS_REFUNDED_PARTIAL_RO = 206;


    const FLAG_SKIP = 'skip';
    const FLAG_NEXT_ORDER = 'next_order';
    const FLAG_NO_ACTION = 'no_action';

    const MOBILE_IOS = 1;
    const MOBILE_ANDROID = 2;

    /**
     * @event Event an event that is triggered once order got status STATUS_REDEEMED
     */
    const EVENT_REDEEMED = 'order-redeemed';

    /**
     * @event Event an event that is triggered once order got status STATUS_REDEEMED_TRACK_NUMBER
     * that means order packages has been sent to shopfans warehouse
     */
    const EVENT_AWAITING_ARRIVING = 'order-awaiting-arriving';

    const EVENT_NEW_ORDER = 'order_new_log';
    const EVENT_ORDER_PAYMENT_SUCCESS_GA = 'order_payment_success_ga';
    const EVENT_ORDER_PAYMENT_SUCCESS = 'order_payment_success_log';
    const EVENT_ORDER_PAYMENT_FAILED = 'order_payment_failed_log';

    /* Признак. Возврат платежа сразу отметить успешным */
    public $checked = 1;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'order';
    }

    /**
     * @inheritdoc
     * @return OrderQuery
     */
    public static function find()
    {
        return new OrderQuery(get_called_class());
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
     */
    public function behaviors()
    {
        return [
//            'timestamp' => [
//                'class' => TimestampBehavior::class,
//                'createdAtAttribute' => 'created_at',
//                'updatedAtAttribute' => false,
//            ],
//            'events' => [
//                'class' => EventProxy::class,
//                'map' => [
//                    static::EVENT_REDEEMED,
//                    static::EVENT_AWAITING_ARRIVING,
//                    self::EVENT_NEW_ORDER,
//                    self::EVENT_ORDER_PAYMENT_SUCCESS,
//                    self::EVENT_ORDER_PAYMENT_FAILED,
//                    self::EVENT_ORDER_PAYMENT_SUCCESS_GA,
//                ],
//            ],
//            'audit' => [
//                'class' => AuditBehavior::class,
//                'operations' => [
//                    static::EVENT_AFTER_INSERT  => 'Created',
//                    static::EVENT_AFTER_UPDATE => 'Updated',
//                    static::EVENT_REDEEMED => 'Redeemed',
//                    static::EVENT_AWAITING_ARRIVING => 'Awaiting Arriving',
//                    static::EVENT_ORDER_PAYMENT_SUCCESS => 'Paid',
//                    static::EVENT_ORDER_PAYMENT_FAILED => 'Payment Failed',
//                    static::EVENT_AFTER_DELETE => 'Deleted',
//                ],
//            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['responsible_user_id', 'sf_recipient_id', 'customer_id', 'status', 'startredeem_at', 'inbasket_at', 'delivery_at', 'redeemtrack_at', 'endredeem_at', 'created_at'], 'integer'],
            [['total_price_cost_usd', 'total_price_customer', 'total_price_customer_usd', 'total_price_buyout_usd', 'total_delivery_cost_buyout_usd', 'total_cost_buyout_usd'], 'number'],
            [['external_number', 'ga'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'external_number' => 'External Number',
            'responsible_user_id' => 'Responsible User',
            'sf_recipient_id' => 'SF Recipient ID',
            'customer_id' => 'Customer ID',
            'status' => 'Status',
            'total_price_cost_usd' => 'Total Price Cost Usd',
            'total_price_customer' => 'Total Price Customer',
            'total_price_customer_usd' => 'Total Price Customer Usd',
            'total_price_buyout_usd' => 'Total Price Buyout Usd',
            'total_delivery_cost_buyout_usd' => 'Total Delivery Cost Buyout Usd',
            'total_cost_buyout_usd' => 'Total Cost Buyout Usd',
            'ga' => 'GA',
            'startredeem_at' => 'Startredeem At',
            'inbasket_at' => 'Inbasket At',
            'delivery_at' => 'Delivery At',
            'endredeem_at' => 'Endredeem At',
            'redeemtrack_at' => 'Redeemtrack At',
            'created_at' => 'Created At',
            'delivery_cost_customer' => 'Delivery Cost Customer',
            'delivery_cost_customer_usd' => 'Delivery Cost Customer USD',
        ];
    }

    /**
     * @return OrderProductQuery|ActiveQuery
     */
    public function getOrderProducts()
    {
        return $this->hasMany(OrderProduct::class, ['order_id' => 'id']);
    }

    /**
     * @return ProductQuery|ActiveQuery
     */
    public function getProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'product_id'])
            ->viaTable('order_product', ['order_id' => 'id']);
    }

    /**
     * @return CustomerQuery|ActiveQuery
     */
    public function getCustomer()
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getOrderPackages()
    {
        return $this->hasMany(OrderPackage::class, ['order_id' => 'id']);
    }

    /**
     * @return UserQuery|ActiveQuery
     */
    public function getResponsibleUser()
    {
        return $this->hasOne(User::class, ['id' => 'responsible_user_id']);
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        return [
            'id',
            'customer_id',
            'total_price_customer',
            'total_price_customer_rub' => function() {
                return $this->total_price_customer;
            },
            'delivery_cost_customer',
            'delivery_cost_customer_rub' => function() {
                return $this->delivery_cost_customer;
            },
            'sf_recipient_id',
            'status',
            'created_at',
            'orderPackages',
            'payment',
            'secondPayment',
            'refundedOrder',
            'is_cancel_order' => function() {
                return OrderHelper::checkCancelOrder($this->id);
            },
        ];
    }

    /**
     * @return bool|int|string
     * TODO Статус не должен быть мешаниной типов
     */
    public function getCheckStatus()
    {
        $flagAllPackageNew = true;
        $flagAllPackageRedeemed = true;
        $flagAllPackageError = true;
        foreach ($this->orderPackages as $orderPackage) {
            if (!in_array($orderPackage->status, [OrderPackage::STATUS_ERROR_NOT_REDEEMED, OrderPackage::STATUS_ERROR_NOT_AVAILABLE])) {
                $flagAllPackageError = false;
            }
            if ($orderPackage->status != OrderPackage::STATUS_NEW) {
                $flagAllPackageNew = false;
            }
            if (!in_array($orderPackage->status, [OrderPackage::STATUS_REDEEMED, OrderPackage::STATUS_REDEEMED_TRACK_NUMBER, OrderPackage::STATUS_ERROR_NOT_REDEEMED, OrderPackage::STATUS_ERROR_NOT_AVAILABLE])) {
                $flagAllPackageRedeemed = false;
            }
        }

        if ($flagAllPackageNew) {
            return Order::FLAG_SKIP;
        }

        if ($flagAllPackageRedeemed && $this->status == Order::STATUS_IN_BASKET) {
            return Order::STATUS_REDEEMED;
        }

        if ($flagAllPackageRedeemed || $flagAllPackageError) {
            return Order::FLAG_NEXT_ORDER;
        }

        if ($flagAllPackageRedeemed) {
            return Order::STATUS_REDEEMED;
        }

        if (in_array($this->status, [Order::STATUS_NEW, Order::STATUS_PAYMENT_FAIL, Order::STATUS_MONEY_RETURNED]) !== false || !$this->checkRefundPaymentFromPackages()) {
            return self::FLAG_NO_ACTION;
        }

        if ($this->status == self::STATUS_REFUND_MONEY) {
            return Order::STATUS_MONEY_RETURNED;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getSkipByOrderProduct()
    {
        $cntAll = OrderProduct::find()
            ->byOrderId($this->id)
            ->count();

        $cntNotStatus = OrderProduct::find()
            ->byOrderId($this->id)
            ->byStatus(OrderProduct::STATUS_NEW)
            ->count();

        if ($cntAll == $cntNotStatus) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public static function getListStatus()
    {
        return [
            self::STATUS_NEW,
            self::STATUS_PAYMENT_FAIL,
            self::STATUS_PAYMENT_SUCCESS,
            self::STATUS_STARTED_PROCESSING,
            self::STATUS_IN_BASKET,
            self::STATUS_DELIVERY,
            self::STATUS_REDEEMED,
            self::STATUS_REDEEMED_TRACK_NUMBER,
            self::STATUS_ERROR_NOT_REDEEMED,
            self::STATUS_ERROR_NOT_AVAILABLE,
            self::STATUS_REFUND_SHIPPING,
        ];
    }

    /**
     * TODO В модели не должно быть презентационной логики
     * @return array
     */
    public function classAlert()
    {
        return [
            Order::STATUS_NEW => 'warning',
            Order::STATUS_PAYMENT_FAIL => 'danger',
            Order::STATUS_PAYMENT_SUCCESS => 'success',
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
            Order::STATUS_REFUND_SHIPPING => 'danger',
        ];
    }

    /**
     * @return array
     */
    public function textStatus()
    {
        return [
            Order::STATUS_NEW => 'Новая',
            Order::STATUS_PAYMENT_FAIL => 'Ошибка оплаты',
            Order::STATUS_PAYMENT_SUCCESS => 'Новый оплачен',
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
            Order::STATUS_REFUND_SHIPPING => 'Инициирован возврат денег за доставку',
        ];
    }

    /**
     * Task 9290
     *
     * @return array
     */
    public function textStatusForFilter()
    {
        return [
            Order::STATUS_PAYMENT_SUCCESS => 'Новый оплачен',
            Order::STATUS_REDEEMED => 'Выкуплено',
            Order::STATUS_REDEEMED_TRACK_NUMBER => 'Выкуплено с треком',
            Order::STATUS_REFUND_MONEY => 'Инициирован возврат',
            Order::STATUS_MONEY_RETURNED => 'Деньги возвращены',
        ];
    }

    public function getClassAlert()
    {
        return $this->classAlert()[$this->status];
    }

    public function getTextStatus()
    {
        return $this->textStatus()[$this->status];
    }

    /**
     * TODO обернуть транзакцией
     */
    public function updateAllTotalPrice()
    {
        $orderTotalPriceCost = 0;
        $orderTotalPriceCustomer = 0;
        $orderTotalPriceCustomerUsd = 0;
        $orderTotalPriceCustomerRealUsd = 0;
        $orderTotalDeliveryCostBuyout = 0;
        $orderTotalPriceBuyout = 0;

        $countProducts = 0;
        $flagGiftCardOnly = true;
        foreach ($this->orderProducts as $orderProduct) {
            $countProducts += $orderProduct->quantity;
            if ($orderProduct->product_market_id != 28) {
                $flagGiftCardOnly = false;
            }
            $orderTotalPriceCost += $orderProduct->total_price_cost_usd;
            $orderTotalPriceCustomer += $orderProduct->total_price_customer;
            $orderTotalPriceCustomerUsd += $orderProduct->total_price_customer_usd;
            $orderTotalPriceCustomerRealUsd += $orderProduct->total_price_customer_real_usd;
            $orderTotalPriceBuyout += $orderProduct->total_price_buyout_usd;
        }
        $this->total_price_cost_usd = $orderTotalPriceCost;
        $this->total_price_customer = $orderTotalPriceCustomer;
        $this->total_price_customer_usd = $orderTotalPriceCustomerUsd;
        $this->total_price_customer_real_usd = $orderTotalPriceCustomerRealUsd;
        $this->total_price_buyout_usd = $orderTotalPriceBuyout;

        $isHomeAddress = false;

        foreach ($this->orderPackages as $orderPackage) {

            // check if there at least one address is a home address and not a pickup point
            if ($isHomeAddress === false)
            {
                $address = $orderPackage && $orderPackage->sf_address_id
                    ? Yii::$app->cache->getOrSet(
                        'sf_address_' . $orderPackage->sf_address_id,
                        function () use ($orderPackage)
                        {
                            return @$orderPackage->order->customer
                                ->shopfans->getAddressByID($orderPackage->sf_address_id);
                        }
                        , 3600)
                    : false;

                if ($address && isset($address['user_address_type_id'])
                    && intval($address['user_address_type_id']) < 3)
                {
                    $isHomeAddress = true;
                }
            }

            $packageTotalPriceCost = 0;
            $packageTotalPriceCustomer = 0;
            $packageTotalPriceCustomerUsd = 0;
            $packageTotalPriceCustomerRealUsd = 0;
            $packageTotalPriceBuyout = 0;
            foreach ($orderPackage->packageProducts as $packageProduct) {
                $packageTotalPriceCost += $packageProduct->total_price_cost_usd;
                $packageTotalPriceCustomer += $packageProduct->total_price_customer;
                $packageTotalPriceCustomerUsd += $packageProduct->total_price_customer_usd;
                $packageTotalPriceCustomerRealUsd += $packageProduct->total_price_customer_real_usd;
                $packageTotalPriceBuyout += $packageProduct->total_price_buyout_usd;
            }
            $orderPackage->total_price_cost_usd = $packageTotalPriceCost;
            $orderPackage->total_price_customer = $packageTotalPriceCustomer;
            $orderPackage->total_price_customer_usd = $packageTotalPriceCustomerUsd;
            $orderPackage->total_price_customer_real_usd = $packageTotalPriceCustomerRealUsd;
            $orderPackage->total_price_buyout_usd = $packageTotalPriceBuyout;
            $orderPackage->total_cost_buyout_usd = $orderPackage->total_price_buyout_usd + $orderPackage->delivery_cost_buyout_usd;
            $orderPackage->save(false);
            $orderTotalDeliveryCostBuyout += $orderPackage->delivery_cost_buyout_usd;
        }

        // пока что убираем весь пересчет по доставке, оставляю как записалось при создании заказа,
        // чтобы ничего не потерять в случае необходимости рассчитать возврат

        // in case of home address add fixed delivery cost to the total price
//        if ($isHomeAddress === true)
//        {
//            $currencyRate = Yii::$app->currencyRate->getLatestPair();
//
//            $this->delivery_cost_customer = 4000;
//            $this->delivery_cost_customer_usd = $currencyRate && $currencyRate->rate > 0
//                ? round($this->delivery_cost_customer / $currencyRate->rate, 2)
//                : 54.32;
//
//            $this->total_price_customer += $this->delivery_cost_customer;
//            $this->total_price_customer_usd += $this->delivery_cost_customer_usd;
//        }
//        else {
//            $this->delivery_cost_customer = 0;
//            $this->delivery_cost_customer_usd = 0;
//        }

//        $deliveryHelper = DeliveryHelper::ensure($this->customer_id, $flagGiftCardOnly);
//        $delivery = $deliveryHelper->getDelivery($this->total_price_customer, $countProducts);
//        if ($delivery !== null) {
//            $deliveryUsd = $deliveryHelper->getDeliveryUsd($this->total_price_customer_usd, $countProducts);
//            $this->delivery_cost_customer += $delivery;
//            $this->delivery_cost_customer_usd += $deliveryUsd;
//            $this->total_price_customer += $delivery;
//            $this->total_price_customer_usd += $deliveryUsd;
//        }

        $this->total_price_customer += $this->delivery_cost_customer;
        $this->total_price_customer_usd += $this->delivery_cost_customer_usd;
        $this->total_price_customer_real_usd += $this->delivery_cost_customer / $this->real_usd_rate;
        $this->total_delivery_cost_buyout_usd = $orderTotalDeliveryCostBuyout;
        $this->total_cost_buyout_usd = $this->total_price_buyout_usd + $this->total_delivery_cost_buyout_usd;
        $this->save(false);
    }

    /**
     * Если все статусы у package одинаковые то этот статус присваивается order
     */
    public function updateStatusByPackageStatus()
    {
        $statusData = [];
        $status = false;
        $statusStartedProcessing = false;
        $statusError = false;
        $statusRedeem = true;
        $statusRefunded = false;
        $errorStatusData = [];
        $errorStatus = [
            OrderPackage::STATUS_ERROR_NOT_REDEEMED,
            OrderPackage::STATUS_ERROR_NOT_AVAILABLE,
            OrderPackage::STATUS_REFUND_MONEY,
            OrderPackage::STATUS_MONEY_RETURNED
        ];
        /* @var OrderPackage $orderPackage */
        foreach ($this->getOrderPackages()->all() as $orderPackage) {
            $statusData[$orderPackage->status] = true;
            if ($orderPackage->status == OrderPackage::STATUS_REFUNDED_PARTIAL_RO) {
                $statusData = [OrderPackage::STATUS_REFUNDED_PARTIAL_RO => true];
                $status = $orderPackage->status;
                break;
            } elseif (in_array($orderPackage->status, $errorStatus)) {
                $statusError = $orderPackage->status;
            } elseif ($orderPackage->status == OrderPackage::STATUS_NEW) {
                $statusStartedProcessing = true;
            } else {
                $status = $orderPackage->status;
            }
        }

        //если в массиве два и больше статусов и хотя бы один из статусов ошибки есть в этом массиве,
        //то удаляем его и отправляем дальше на проверку.
        if (count($statusData) > 1) {
            foreach ($statusData as $key => $item) {
                if (array_search($key, $errorStatus) !== false) {
                    $errorStatusData[$key] = true;
                    unset($statusData[$key]);
                } elseif (in_array($key, [OrderPackage::STATUS_REFUNDED_RO, OrderPackage::STATUS_REFUNDED_PARTIAL_RO])){
                    $statusRefunded = true;
                    $statusRedeem = false;
                } elseif (!in_array($key, [OrderPackage::STATUS_REDEEMED_TRACK_NUMBER, OrderPackage::STATUS_REDEEMED])) {
                    $statusRedeem = false;
                }
            }
        }

        if (empty($statusData)) {
            list($statusData, $status) = $this->getStatusData($errorStatusData);
        }

        //если все package выкуплены
        if (count($statusData) > 1 && $statusRedeem) {
            $statusData = [self::STATUS_REDEEMED => true];
            $status = self::STATUS_REDEEMED;
        }

        //если есть полный возврат за package, но другие package в другом статусе
        if (count($statusData) > 1 && $statusRefunded) {
            $statusData = [self::STATUS_REFUNDED_PARTIAL_RO => true];
            $status = OrderPackage::STATUS_REFUNDED_PARTIAL_RO;
        }

        if($statusStartedProcessing && array_search($this->status, [self::STATUS_NEW, self::STATUS_PAYMENT_FAIL]) === false ){
            $status = self::STATUS_STARTED_PROCESSING;
            $statusData = [self::STATUS_STARTED_PROCESSING => true];
        }

        if (count($statusData) == 1) {
            if ($status == false) {
                $status = $statusError;
            }
            $endStatus = [
                OrderPackage::STATUS_REDEEMED_TRACK_NUMBER,
                OrderPackage::STATUS_REDEEMED,
                OrderPackage::STATUS_ERROR_NOT_REDEEMED,
                OrderPackage::STATUS_ERROR_NOT_AVAILABLE,
            ];

//            end status
//            OrderPackage::STATUS_REFUND_MONEY,
//            OrderPackage::STATUS_MONEY_RETURNED,

            $allStatus = [OrderPackage::STATUS_DELIVERY,
                OrderPackage::STATUS_IN_BASKET,
                OrderPackage::STATUS_MONEY_RETURNED,
                OrderPackage::STATUS_REFUND_MONEY,
                OrderPackage::STATUS_REFUNDED_RO,
                OrderPackage::STATUS_REFUNDED_PARTIAL_RO,
                Order::STATUS_STARTED_PROCESSING
            ];
            $allStatus = array_merge($allStatus, $endStatus);

            if (in_array($status, $endStatus)) {
                if ($status == OrderPackage::STATUS_REDEEMED_TRACK_NUMBER) {
                    $this->redeemtrack_at = time();
                } else {
                    $this->endredeem_at = time();
//                    $this->save(false);
                }
            }

            if (in_array($status, $allStatus)) {
                $this->status = $status;
                $this->save(false);
            }
        }
    }

    /**
     * @param $errorStatusData
     * @return array
     */
    public function getStatusData($errorStatusData)
    {
        if (count($errorStatusData) === 1) {
            return [$errorStatusData,  key($errorStatusData)];
        }

        $moneyStatuses = [
            OrderPackage::STATUS_REFUND_MONEY,
            OrderPackage::STATUS_MONEY_RETURNED,
        ];

        if (count($errorStatusData) > 1) {
            foreach ($errorStatusData as $key => $item) {
                if (in_array($key, $moneyStatuses)) {
                    $errorStatus[$key] = true;
                    unset($errorStatusData[$key]);
                }
            }
            if (empty($errorStatusData)) {
                // значит были только статусы STATUS_REFUND_MONEY STATUS_MONEY_RETURNED - заказу присваиваем STATUS_REFUND_MONEY
                return [[OrderPackage::STATUS_REFUND_MONEY => true], OrderPackage::STATUS_REFUND_MONEY];
            }
        }

        // значит остались статусы STATUS_ERROR_NOT_REDEEMED или STATUS_ERROR_NOT_AVAILABLE
        return [[OrderPackage::STATUS_ERROR_NOT_AVAILABLE => true], OrderPackage::STATUS_ERROR_NOT_AVAILABLE];
    }

    /**
     * @return string[]
     */
    public function getTrackNumbers()
    {
        $items = [];
        foreach ($this->orderPackages as $package) {
            $textImg = '<img src="https://www.google.com/s2/favicons?domain=';
            if (!empty($package->tracking_number)) {
                try {
                    $market = Yii::$app->markets->one($package->getPackageProducts()->one()->product_market_id);
                    $items[] = $textImg . $market->getHomeUrl() . '">' . ' - ' . $package->tracking_number;
                } catch (\Exception $e) {
                    $items[] = $package->tracking_number;
                }
            } else {
                try {
                    $market = Yii::$app->markets->one($package->getPackageProducts()->one()->product_market_id);
                    $items[] = $textImg . $market->getHomeUrl() . '">' . ' - empty';
                } catch (\Exception $e) {
                    $items[] = 'empty track number';
                }
            }
        }
        return $items;
    }

    /**
     * @return ActiveQuery
     */
    public function getRefundedOrder()
    {
        return $this->hasOne(RefundedOrder::class, ['order_id' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getComment()
    {
        return $this->hasMany(OrderComment::class, ['entity_id' => 'id'])->where(['entity_type' => OrderComment::TYPE_ORDER]);
    }

    /**
     * Возвращает статус для заказа на основании самого минимального из все package
     *
     * @return array|mixed
     */
    public function getStatusDelivery()
    {
        $orderShipmentStatus = ['status' => 10000];
        foreach ($this->orderPackages as $package) {
            $statusDelivery = $package->getStatusDelivery();
            if ((int)$orderShipmentStatus['status'] > (int)$statusDelivery['status']) {
                $orderShipmentStatus = $statusDelivery;
            }
        }

        return $orderShipmentStatus;
    }

    /**
     * @return PaymentQuery|ActiveQuery
     */
    public function getPayment()
    {
        return $this->hasOne(Payment::class, ['id' => 'payment_id']);
    }

    /**
     * @return PaymentQuery|ActiveQuery
     */
    public function getSecondPayment()
    {
        return $this->hasOne(Payment::class, ['id' => 'second_payment_id']);
    }

    /**
     * @return PaymentQuery|ActiveQuery
     */
    public function getRefundPayment()
    {
        return $this->hasOne(Payment::class, ['id' => 'refund_payment_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getOrderPayments()
    {
        return $this->hasMany(Payment::class, ['id' => 'payment_id'])
            ->viaTable('order_payment', ['order_id' => 'id']);
    }

    /**
     * @return PaymentQuery
     * @throws InvalidConfigException
     */
    public function getOrderWithdrawPayments(): PaymentQuery
    {
        /** @var PaymentQuery $orderWithdrawPayments */
        $orderWithdrawPayments = $this->hasMany(
            Payment::class, ['id' => 'payment_id']
        )
            ->viaTable('order_payment', ['order_id' => 'id'])
            ->innerJoin('transaction', 'transaction.original_payment_id = payment.id')
            ->select([
                'payment.amount',
                'payment.status_code',
                'payment.gateway_code',
                'id' => 'payment.id',
                'SUM(transaction.amount) AS sum_transaction_amount'
            ])
            ->where(['>', 'payment.amount', 0])
            ->groupBy('id')
            ->having('sum_transaction_amount > 0')
        ;

        return $orderWithdrawPayments;
    }

    /**
     * @return $this
     */
    public function setStatusSuccess()
    {
        $this->status = self::STATUS_PAYMENT_SUCCESS;

        if($this->customer && $this->customer->referrer_id){
            // добавляем задачу на начисление бонусов
            OrderAccrueBonuses::create($this);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setStatusFail()
    {
        $this->status = self::STATUS_PAYMENT_FAIL;

        return $this;
    }

    public function afterSave($insert, $changedAttributes)
    {
        // if payment has been changed
        if (!$insert && !empty($changedAttributes) && isset($changedAttributes['payment_id'])
            && is_object($oldPayment = Payment::findOne(['id' => $changedAttributes['payment_id']]))) {
            /** @var Payment $oldPayment */

            // log information because we don't have any other way to keep this relation in history
            Yii::info(sprintf('In order %s payment %s has been changed to %s',
                $this->id, $changedAttributes['payment_id'], $this->payment_id ?: 'undefined payment'));

            // change status of old payment to Payment::STATUS_REPLACED
            $oldPayment->status_code = Payment::STATUS_REPLACED;
            $tx_data = $oldPayment->tx_data;
            $tx_data['replaced_with_payment_id'] = $this->payment_id;
            $oldPayment->tx_data = $tx_data;
            $oldPayment->save();
        }

        // if second payment has been changed
        if (!$insert && !empty($changedAttributes) && isset($changedAttributes['second_payment_id'])
            && is_object($oldSecondPayment = Payment::findOne(['id' => $changedAttributes['second_payment_id']]))) {
            /** @var Payment $oldSecondPayment */

            // log information because we don't have any other way to keep this relation in history
            Yii::info(sprintf('In order %s payment %s has been changed to %s',
                $this->id, $changedAttributes['second_payment_id'], $this->second_payment_id ?: 'undefined payment'));

            // change status of old payment to Payment::STATUS_REPLACED
            $oldSecondPayment->status_code = Payment::STATUS_REPLACED;
            $tx_data = $oldSecondPayment->tx_data;
            $tx_data['replaced_with_payment_id'] = $this->payment_id;
            $oldSecondPayment->tx_data = $tx_data;
            $oldSecondPayment->save();
        }

        if (isset($changedAttributes['status'])) {
            switch ($this->status) {
                case static::STATUS_REDEEMED:
                    $this->trigger(self::EVENT_REDEEMED);
                    $this->updateAllTotalPrice();
                    $this->checkRefundMoneyForPackage();
                    break;
                case static::STATUS_REDEEMED_TRACK_NUMBER:
                    $this->trigger(self::EVENT_AWAITING_ARRIVING);
                    $this->updateAllTotalPrice();
                    $this->checkRefundMoneyForPackage();
                    break;
                case static::STATUS_PAYMENT_SUCCESS:
                    if ($changedAttributes['status'] == self::STATUS_NEW ||
                        $changedAttributes['status'] == self::STATUS_PAYMENT_FAIL) {
                        $this->trigger(self::EVENT_ORDER_PAYMENT_SUCCESS);
                        $this->trigger(self::EVENT_ORDER_PAYMENT_SUCCESS_GA);
                    }
                    PromocodeHelper::setUsedPersonalCodeFromOrder($this);
                    $this->linkShopfans();
                    break;
                case static::STATUS_PAYMENT_FAIL:
                    $this->trigger(self::EVENT_ORDER_PAYMENT_FAILED);
                    break;
                case static::STATUS_ERROR_NOT_REDEEMED:
                case static::STATUS_ERROR_NOT_AVAILABLE:
                    $this->updateAllTotalPrice();
                    $this->checkRefundMoneyForPackage();
                    break;
                case static::STATUS_MONEY_RETURNED:
                    $this->checkShippingRefund();
                    break;
            }
            $this->auditLog('Status changed to ' . $this->textStatus);
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Link Shopfans objects with Ecommerce objects once order paid
     *
     * It creates package and shipment in Shopfans for each package in order.
     * It fill declaration sfx for each created package in Shopfans with products from order's package.
     */
//    protected function linkShopfans()
    public function linkShopfans()
    {
        try {
            $api = $this->customer->shopfans;
            $shopfansService = Yii::$app->shopfans;
        } catch (UserApiException $e) {
            Yii::error('Can not link with Shopfans due to error: ' . $e->getMessage());
            return ;
        }

        // disable exceptions if any errors during api requests
        $throwExceptions = $api->throwExceptions;
        $api->throwExceptions = false;

        //Если получатель есть в ШФ то добавим его в заказ
        //https://prod-redmine.shopfans.com/issues/9371
        if (empty($this->sf_recipient_id)) {
            $recipient = $shopfansService->getRecipientFromOrder($this->customer);
            if ($recipient && is_array($recipient) && isset($recipient[0]) && isset($recipient[0]['id'])) {
                $this->sf_recipient_id = $recipient[0]['id'];
                $this->save();
            }
        }

        foreach ($this->orderPackages as $orderPackage) {
            if (!$orderPackage->sf_package_id) {
                $packageProducts = $orderPackage->packageProducts;
                $package = $shopfansService->createPackage($this->customer, $orderPackage);
                if (!$package || !isset($package['id'])) {
                    //Ошибка пишется в логи внутри метода shopfansService->createPackage
                    continue;
                }

                $orderPackage->sf_package_id = $package['id'];
                try {
                    $text = "Добавлен sf_package_id={$package['id']} при создании package в shopfans";
                    $orderPackage->addComment($text);
                    $text .= " (Order.php:999)";
                    TelegramQueueJob::push($text, 'Change sf_package_id', Events::getSfPackageIdChangeChatId());
                    Yii::info($text, 'requests-shopfans');
                } catch (\Exception $e) {
                }
                $declarations = $shopfansService->replaceAllDeclarationForPackage($this->customer, $orderPackage);
                if(empty($declarations)){
                    // попробовать ещё раз
                    $message = "Создание деклараций для package {$orderPackage->id} завершилось с ошибкой. Повторная попытка. \n";
                    $message .= "orderId: {$orderPackage->order_id}";
                    TelegramQueueJob::push($message, 'Declarations in linkShopfans empty', Events::getSplitDisChatId());

                    $declarations = $shopfansService->replaceAllDeclarationForPackage($this->customer, $orderPackage);
                    if(empty($declarations)){
                        $message = "Повторное создание деклараций для package {$orderPackage->id} завершилось с ошибкой. \n";
                        $message .= "orderId: {$orderPackage->order_id}";
                        TelegramQueueJob::push($message, 'Declarations in linkShopfans empty', Events::getSplitDisChatId());

                        Yii::error(sprintf(
                            'Can not replace all declarations for order package with id=%i', $orderPackage->id),
                            \Shopfans\Api\UserApi::class);
                    }
                }
            }

            if (!$orderPackage->sf_shipment_id && $orderPackage->sf_package_id) {
                $shipment = $shopfansService->createShipment($this->customer, $orderPackage);
                if ($shipment && isset($shipment['id'])) {
                    $orderPackage->sf_shipment_id = $shipment['id'];
                    $orderPackage->sf_tracking_number = $shipment['tracking_number'];
                } else {
                    $message = "Создание shipment для package {$orderPackage->id} завершилось с ошибкой. \n";
                    $message .= "orderId: {$orderPackage->order_id}";
                    TelegramQueueJob::push($message, 'Error create shipment', Events::getSplitDisChatId());

                    // попробовать ещё раз создать shipment
                    $shipment = $shopfansService->createShipment($this->customer, $orderPackage);
                    if ($shipment && isset($shipment['id'])) {
                        $orderPackage->sf_shipment_id = $shipment['id'];
                        $orderPackage->sf_tracking_number = $shipment['tracking_number'];
                    } else {
                        $message = "Повторное создание shipment для package {$orderPackage->id} завершилось с ошибкой. \n";
                        $message .= "orderId: {$orderPackage->order_id}";
                        TelegramQueueJob::push($message, 'Error create shipment', Events::getSplitDisChatId());

                        Yii::error(sprintf(
                            'Can not create shipment for order package with id=%i', $orderPackage->id),
                            \Shopfans\Api\UserApi::class);
                    }
                }
            }

            if ($orderPackage->getDirtyAttributes() && !$orderPackage->save(false)) {
                Yii::error(sprintf(
                    'Can not update order package with %s due to error: %s',
                    http_build_query($orderPackage->getDirtyAttributes()),
                    print_r($orderPackage->getErrors(), true)),
                    \Shopfans\Api\UserApi::class);
            }
        }

        $api->throwExceptions = $throwExceptions;
    }

    /**
     * @param $totalPrice
     * @param bool $full
     */
    public function refundMoney($totalPrice, $full = true)
    {
        //возврат за заказ переиспользуем для возврата за доставку,
        //блокировка функции, переиспользовать, если потребуется вручную инициировать возврат за доставку
        return;

        if ($totalPrice <= 0) {
            $totalPrice = $this->total_price_customer;
        }
        $totalRefundPrice = -100 * $totalPrice;
        $user = User::findOne(Yii::$app->user->id);

        if (!$this->checkRefundPaymentFromPackages()) {
            return;
        }

        // ищем похожие по сумме возвраты за последние 10 минут
        $enableSlaves = Yii::$app->getDb()->enableSlaves;
        Yii::$app->getDb()->enableSlaves = false;
        $sameRefunds = Payment::find()
            ->where('created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                AND amount=:amount
                AND status_code IN (:new_refund, :refunded, :partial_refunded)',
                [':amount' => $totalRefundPrice,
                 ':new_refund' => Payment::STATUS_NEW_REFUND,
                 ':refunded' => Payment::STATUS_REFUNDED,
                 ':partial_refunded' => Payment::STATUS_REFUNDED_PARTIAL,
                ])
            ->all();
        Yii::$app->getDb()->enableSlaves = $enableSlaves;
        // проверяем, есть ли среди найденных возвратов, идентичный новому
        foreach ($sameRefunds as $refundPayment) {
            if (empty(@$refundPayment->tx_data['order_package_id'])
                && @$refundPayment->tx_data['order_id'] == $this->id) {
                Yii::error($message = sprintf(
                    'Reject refund money request for order %s, ' .
                    'because the same one has been found, see payment %s',
                    $this->id, $refundPayment->id));
                TelegramQueueJob::push($message, '!!!ALARM Duplicated Refunding',
                        Events::getInnerPaymentWithdrawLogsChatId());
                return false;
            }
        }

        // if source payment exists
        if ($payment = $this->payment)
        {
            // reuse uid and gateway of source payment
            $uid = $payment->uid . '-' . substr(time(), -5);
            $gatewayCode = $payment->gateway_code;
        }
        else
        {
            // compose uid by order id and use first available gateway
            $uid = $this->id . '-' . substr(time(), -5);
            $gateways = Yii::$app->payment->gateways;
            $gatewayCode = key($gateways);
        }

        $refundPayment = new Payment([
            'gateway_code' => $gatewayCode,
            'uid' => $uid,
            'amount' => $totalRefundPrice,
            'tx_data' => [
                'order_id'         => $this->id,
                'order_package_id' => null,
                'payment_id'       => $this->payment_id,
                'user_id'          => $user->id,
                'description'      => 'Возврат денежных средств клиенту полностью за заказ' . $this->id .
                    ' администратором: ' . $user->username . '(' . $user->id . ')',
            ],
            'status_code' => Payment::STATUS_NEW_REFUND
        ]);

        if ($refundPayment->save()) {
            if ($full) {
                foreach ($this->orderProducts as $product) {
                    $product->status = OrderProduct::STATUS_ERROR_NOT_REDEEMED;
                    $product->save();
                }
            }
            foreach ($this->orderPackages as $package) {
                $package->status = OrderPackage::STATUS_REFUND_MONEY;
                $package->save();
            }
            $this->refund_payment_id = $refundPayment->id;
            $this->save();

            $this->link('orderPayments', $refundPayment);

            $this->addComment(
                'Пользователь %s (id:%s) инициировал возврата платежа(%s) на сумму %s руб. за весь заказ',
                $user->username, $user->id, $refundPayment->id, $totalPrice);

            $this->updateStatusByPackageStatus();

            if ($this->isAllowedAutoRefund($refundPayment->amount, true))
            {
                $refundPayment->save(false);
                $gateway = Yii::$app->payment->getGateway($gatewayCode);
                if ($gateway && $gateway->cancelPayment($refundPayment))
                {
                    $this->successReturnMoney(true);

                    Yii::$app->trigger(SuccessfulRefundingEvent::class, new SuccessfulRefundingEvent([
                        'order_id'         => $this->id,
                        'order_package_id' => null,
                        'amount'           => round($refundPayment->amount / 100, 2),
                        'payment_id'       => $refundPayment->id,
                    ]));
                }
                else
                {
                    $this->addComment($message =
                        'Не удалось выполнить автоматический возврат средств из-за отказа платёжного шлюза.');

                    Yii::$app->trigger(FailedRefundingEvent::class, new FailedRefundingEvent([
                        'order_id'         => $this->id,
                        'order_package_id' => null,
                        'message'          => $message,
                        'amount'           => round($refundPayment->amount / 100, 2),
                        'payment_id'       => $refundPayment->id,
                    ]));
                }
            }
        }
    }

    /**
     * @param $totalPrice
     */
    public function partialRefundMoney($totalPrice)
    {
        // if source payment exists
        if ($payment = $this->payment) {
            // reuse uid and gateway of source payment
            $uid = $payment->uid . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
            $gatewayCode = $payment->gateway_code;
            $gatewayName = "на карту ({$payment->gateway_code})";
        } else {
            // compose uid by order id and use first available gateway
            $uid = $this->id . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
            $gateways = Yii::$app->payment->gateways;
            $gatewayCode = key($gateways);
        }
//        раскоментить если надо будет отключить возврат на личный счёт
//        if (!empty($this->second_payment_id) || $this->customer->isAllowInnerPayment()) {
//            $gatewayCode = 'inner';
//        }
        $gatewayCode = 'inner'; // всё возвращаем на личный счёт
        $gatewayName = "На личный счёт (inner)";

        $totalRefundPrice = -100 * $totalPrice;
        $user = User::findOne(Yii::$app->user->id);
        $orderRemainSum = $this->getOrderRemainSum();
        TelegramQueueJob::push(sprintf('Пользователь %s (id:%s) инициировал возврат части платежа за заказ %s на сумму %s руб %s. Остаток для возвратов (%s). https://app.usmall.ru/order/view/%s?type=only',
            $user->username, $user->id, $this->id, $totalPrice, $gatewayName, $orderRemainSum / 100, $this->id), "Запущен частичный возврат", Events::getInnerPaymentWithdrawLogsChatId());

        if ($orderRemainSum < $totalPrice) {
            $this->addComment(
                sprintf('Пользователь %s (id:%s) инициировал возврат части платежа на сумму %s руб. Сумма возврата больше, чем остаток для возвратов (%s)',
                    $user->username, $user->id, $totalPrice, $orderRemainSum / 100));
            TelegramQueueJob::push(sprintf(' Ошибка возврата части платежа за заказ %s на сумму %s руб. Сумма возврата больше, чем остаток для возвратов (%s). https://app.usmall.ru/order/view/%s?type=only',
                $this->id, $totalPrice, $orderRemainSum / 100, $this->id), "Ошибка частичного возврата за заказ", Events::getInnerPaymentWithdrawLogsChatId());

            return false;
        }

        $refundPayment = new Payment([
            'gateway_code' => $gatewayCode,
            'uid' => $uid,
            'amount' => $totalRefundPrice,
            'tx_data' => [
                'order_id' => $this->id,
                'order_package_id' => null,
                'payment_id' => $this->payment_id,
                'user_id' => $user->id,
                'description' => 'Возврат денежных средств клиенту частично за заказ' . $this->id .
                    ' администратором: ' . $user->username . '(' . $user->id . ') на сумму: ' . $totalPrice,
            ],
            'status_code' => Payment::STATUS_NEW_REFUND_FLEX
        ]);

        if ($refundPayment->save()) {
            $this->link('orderPayments', $refundPayment);

            $this->addComment(
                sprintf('Пользователь %s (id:%s) инициировал возврат части платежа(%s) на сумму %s руб %s.',
                $user->username, $user->id, $refundPayment->id, $totalRefundPrice / 100, $gatewayName));
            $gateway = Yii::$app->payment->getGateway($gatewayCode);
            $refundPayment->save(false);
            if ($gateway && $gateway->cancelPayment($refundPayment)) {
                $refundPayment->finishedPartialOrderRefunded();
                return true;
            } else {
                $this->addComment('Не удалось выполнить автоматический возврат средств из-за отказа платёжного шлюза.');
            }
        }
        return false;

    }

    /**
     * @param $totalPrice
     */
    public function partialRefundMoneyToOriginalGateway($totalPrice)
    {
        $totalRefundPrice = -100 * $totalPrice;
        $user = User::findOne(Yii::$app->user->id);
        $orderRemainSum = $this->getOrderRemainSum();
        $totalRemainSum = $this->getOrderRemainSumForMainPayment(); // остаток, доступный для возврата на карту
        $gatewayName = " на карту ";
        if(isset($this->payment)){
            $gatewayName .= "({$this->payment->gateway_code})";
        }
        TelegramQueueJob::push(sprintf('Пользователь %s (id:%s) инициировал возврат на карту части платежа за заказ %s на сумму %s руб %s. Остаток для возвратов (%s). https://app.usmall.ru/order/view/%s?type=only',
            $user->username, $user->id, $this->id, $totalPrice, $gatewayName, $totalRemainSum / 100, $this->id), "Запущен частичный возврат", Events::getInnerPaymentWithdrawLogsChatId());

        if(empty($this->payment)){
            TelegramQueueJob::push(sprintf('Ошибка частичного ручного возврата на карту: не найден основной платёж. Пользователь %s (id:%s) инициировал возврат на карту части платежа за заказ %s на сумму %s руб. Остаток для возвратов (%s). https://app.usmall.ru/order/view/%s?type=only',
                $user->username, $user->id, $this->id, $totalPrice, $totalRemainSum / 100, $this->id), "Запущен частичный возврат", Events::getInnerPaymentWithdrawLogsChatId());
            Yii::$app->session->setFlash('warning', sprintf(
                'Ошибка частичного ручного возврата на карту: не найден основной платёж  (order id:%s)' .
                $this->id));
            return false;
        }

        if($this->payment->gateway_code === 'inner'){
            TelegramQueueJob::push(sprintf('Ошибка частичного ручного возврата на карту: невозможно вернуть на личный счёт через этот метод. Пользователь %s (id:%s) инициировал возврат на карту части платежа за заказ %s на сумму %s руб. Остаток для возвратов (%s). https://app.usmall.ru/order/view/%s?type=only',
                $user->username, $user->id, $this->id, $totalPrice, $totalRemainSum / 100, $this->id), "Запущен частичный возврат", Events::getInnerPaymentWithdrawLogsChatId());
            Yii::$app->session->setFlash('warning', sprintf(
                'Ошибка частичного ручного возврата на карту: невозможно вернуть на личный счёт через этот метод  (order id:%s)' .
                $this->id));
            return false;
        }

        if($this->payment->status_code !== Payment::STATUS_SUCCESS){
            TelegramQueueJob::push(sprintf('Ошибка частичного ручного возврата на карту: неверный статус платежа (%s). Пользователь %s (id:%s) инициировал возврат на карту части платежа за заказ %s на сумму %s руб. Остаток для возвратов (%s). https://app.usmall.ru/order/view/%s?type=only',
                $this->payment->status_code, $user->username, $user->id, $this->id, $totalPrice, $totalRemainSum / 100, $this->id), "Запущен частичный возврат", Events::getInnerPaymentWithdrawLogsChatId());
            Yii::$app->session->setFlash('warning', sprintf(
                'Ошибка частичного ручного возврата на карту: неверный статус платежа (%s).  (order id:%s)' .
                $this->payment->status_code, $this->id));
            return false;
        }

        if ($totalRemainSum < abs($totalRefundPrice)) {
            $this->addComment(
                sprintf('Пользователь %s (id:%s) инициировал возврат на карту части платежа на сумму %s руб. Сумма возврата больше, чем остаток для возвратов (%s)',
                    $user->username, $user->id, $totalPrice, $totalRemainSum / 100));
            TelegramQueueJob::push(sprintf(' Ошибка возврата на карту части платежа за заказ %s на сумму %s руб. Сумма возврата больше, чем остаток для возвратов (%s). https://app.usmall.ru/order/view/%s?type=only',
                $this->id, $totalPrice, $orderRemainSum / 100, $this->id), "Ошибка частичного возврата на карту за заказ", Events::getInnerPaymentWithdrawLogsChatId());
            Yii::$app->session->setFlash('danger', sprintf('Сумма возврата больше, чем остаток для возвратов (%s).  (order id: %s)',
                $totalRemainSum / 100, $this->id));

            return false;
        }

        // if source payment exists
        if ($payment = $this->payment) {
            // reuse uid and gateway of source payment
            $uid = $payment->uid . '-' . Payment::STATUS_NEW_REFUND_TO_CARD_PARTIAL . '-' . substr(time(), -5);
            $gatewayCode = $payment->gateway_code;
        } else {
            // compose uid by order id and use first available gateway
            $uid = $this->id . '-' . Payment::STATUS_NEW_REFUND_TO_CARD_PARTIAL . '-'  . substr(time(), -5);
            $gateways = Yii::$app->payment->gateways;
            $gatewayCode = key($gateways);
        }

        $refundPayment = new Payment([
            'gateway_code' => $gatewayCode,
            'uid' => $uid,
            'amount' => $totalRefundPrice,
            'tx_data' => [
                'order_id' => $this->id,
                'order_package_id' => null,
                'payment_id' => $this->payment_id,
                'user_id' => $user->id,
                'description' => 'Возврат денежных средств на карту клиенту частично за заказ' . $this->id .
                    ' администратором: ' . $user->username . '(' . $user->id . ') на сумму: ' . $totalPrice,
            ],
            'status_code' => Payment::STATUS_NEW_REFUND_TO_CARD_PARTIAL
        ]);

        if ($refundPayment->save()) {
            $this->link('orderPayments', $refundPayment);

            $this->addComment(
                sprintf('Пользователь %s (id:%s) инициировал возврат %s части платежа(%s) на сумму %s руб.',
                    $user->username, $user->id, $gatewayName, $refundPayment->id, $totalRefundPrice / 100));
            $gateway = Yii::$app->payment->getGateway($gatewayCode);
            $refundPayment->save(false);
            if ($gateway && $gateway->cancelPayment($refundPayment)) {
                $refundPayment->finishedPartialOrderRefundedToCard();
                return true;
            } else {
                $this->addComment('Не удалось выполнить автоматический возврат средств из-за отказа платёжного шлюза.');
            }
        }
        return false;

    }

    /**
     * Остаток денег в заказе, которые ещё не вернули на карту
     * @return float|int
     */
    public function getOrderRemainSumForMainPayment()
    {
        $sum = 0;
        $order = Order::findOne($this->id); // надо загрузить заново, так как в текущем может не быть свежепривязанных платежей
        $mainGatewayCode = $order->payment->gateway_code;

        /** @var $payment Payment */
        foreach ($order->orderPayments as $payment) {
            if ($payment->gateway_code == $mainGatewayCode && $payment->status_code == Payment::STATUS_SUCCESS) {
                // оплата за заказ
                $sum += $payment->amount;
            }

            // возврат за заказ
            if ($payment->gateway_code == $mainGatewayCode && in_array($payment->status_code,
                    [
                        Payment::STATUS_REFUNDED,
                        Payment::STATUS_REFUNDED_PARTIAL,
                        Payment::STATUS_REFUNDED_FLEX_PARTIAL,
                        Payment::STATUS_REFUNDED_TO_CARD_PARTIAL,
                        Payment::STATUS_REFUND_SHIPPING,
                        Payment::STATUS_REFUNDED_SHIPPING_PARTIAL,
                        Payment::STATUS_REFUNDED_RO,
                    ])) {
                $sum += $payment->amount;
            }
        }

        $transactionMainPaymentRemainSum = TransactionEditor::getPaymentRefundedSum($order->payment->id); // сколько вернули с платежа на личный счёт

        return $sum - $transactionMainPaymentRemainSum;
    }


    /**
     * Остаток денег в заказе, которые ещё не вернули на карту
     * @return float|int
     */
    public function getOrderRefundedSumForMainPayment()
    {
        $sum = 0;
        $order = Order::findOne($this->id); // надо загрузить заново, так как в текущем может не быть свежепривязанных платежей
        $mainGatewayCode = $order->payment->gateway_code;

        /** @var $payment Payment */
        foreach ($order->orderPayments as $payment) {
            // возврат за заказ
            if ($payment->gateway_code == $mainGatewayCode && in_array($payment->status_code, [
                    Payment::STATUS_REFUNDED,
                    Payment::STATUS_REFUNDED_PARTIAL,
                    Payment::STATUS_REFUNDED_FLEX_PARTIAL,
                    Payment::STATUS_REFUNDED_TO_CARD_PARTIAL,
                    Payment::STATUS_REFUND_SHIPPING,
                    Payment::STATUS_REFUNDED_SHIPPING_PARTIAL,
                    Payment::STATUS_REFUNDED_RO,
                ])) {
                $sum += $payment->amount;
            }
        }

        return $sum;
    }


    /**
     * Остаток денег в заказе, которые можно вернуть (на основе платежей)
     * @return float|int
     */
    public function getOrderRemainSum()
    {
        $sum = 0;
        $countWithdrawPayments = 0;
        $countRefundPayments = 0;
        $suspiciousPayments = [];
        $withdrawPayments = [];
        $order = Order::findOne($this->id); // надо загрузить заново, так как в текущем может не быть свежепривязанных платежей

        /** @var $payment Payment */
        foreach ($order->orderPayments as $payment) {
            if ($payment->status_code == Payment::STATUS_SUCCESS) {
                // оплата за заказ
                $sum += $payment->amount;
                $withdrawPayments[] = $payment;
                $countWithdrawPayments++;
            }

            // возврат за заказ
            if (in_array($payment->status_code, [
                Payment::STATUS_REFUNDED,
                Payment::STATUS_REFUNDED_PARTIAL,
                Payment::STATUS_REFUNDED_FLEX_PARTIAL,
                Payment::STATUS_REFUNDED_TO_CARD_PARTIAL,
                Payment::STATUS_REFUND_SHIPPING,
                Payment::STATUS_REFUNDED_SHIPPING_PARTIAL,
                Payment::STATUS_REFUNDED_RO,
            ])) {
                if($payment->gateway_code === 'inner'){
                    $paymentSum = TransactionEditor::getPaymentSum($payment->id);
                    $sum -= abs($paymentSum);
                } else {
                    $sum += $payment->amount;
                }
                $countRefundPayments++;
            }

            // подозрительные статусы
            if (!in_array($payment->status_code, [
                Payment::STATUS_REFUNDED,
                Payment::STATUS_REFUNDED_PARTIAL,
                Payment::STATUS_REFUNDED_FLEX_PARTIAL,
                Payment::STATUS_REFUNDED_TO_CARD_PARTIAL,
                Payment::STATUS_SUCCESS,
                Payment::STATUS_REFUND_SHIPPING,
                Payment::STATUS_REFUNDED_SHIPPING_PARTIAL,
            ])) {
                $suspiciousPayments[] = $payment;
            }
        }
        if(!empty($suspiciousPayments)){
            $message = "";
            /** @var $suspiciousPayment Payment */
            foreach($suspiciousPayments as $suspiciousPayment){
                $message .= sprintf('Подозрительный статус платежа: paymentId: %s (status:%s) amount: %s https://app.usmall.ru/order/view/%s?type=only',
                    $suspiciousPayment->id, $suspiciousPayment->status_code, $suspiciousPayment->amount / 100, $this->id) . "\n";
            }

            TelegramQueueJob::push($message, "Suspicious payments", Events::getInnerPaymentWithdrawLogsChatId());
        }

        if ($countWithdrawPayments > 2 && !empty($withdrawPayments)) {
            $message = "";
            /** @var $withdrawPayment Payment */
            foreach($withdrawPayments as $withdrawPayment){
                $message .= sprintf('paymentId: %s (status:%s) amount: %s ',
                        $withdrawPayment->id, $withdrawPayment->status_code, $withdrawPayment->amount / 100) . "\n";
            }

            $message .= sprintf('https://app.usmall.ru/order/view/%s?type=only', $this->id);
            TelegramQueueJob::push($message, "Обнаружено больше 2 платежей оплаты", Events::getInnerPaymentWithdrawLogsChatId());
        }

        return $sum;
    }

    /**
     * Returns TRUE if order has been paid and not refunded yet, FALSE otherwise.
     *
     * @return bool
     */
    public function isPaid()
    {
        if ($this->second_payment_id && !in_array($this->secondPayment->status_code, [Payment::STATUS_SUCCESS, Payment::STATUS_REFUNDED_PARTIAL])) {
            return false;
        }
        return $this->payment_id && in_array($this->payment->status_code, [Payment::STATUS_SUCCESS, Payment::STATUS_REFUNDED_PARTIAL]);
    }

    /**
     * Returns TRUE if order can be automatically refunded, FALSE otherwise
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
        // don't let refund money if order is not in valid status
        $allowedStatus = [
            static::STATUS_NEW, static::STATUS_PAYMENT_SUCCESS, static::STATUS_STARTED_PROCESSING,
            static::STATUS_IN_BASKET, static::STATUS_DELIVERY, static::STATUS_REDEEMED,
            static::STATUS_REDEEMED_TRACK_NUMBER, static::STATUS_REFUND_MONEY, static::STATUS_REFUND_SHIPPING];

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
            $decision = sprintf('Статус заказа не позволяет выполнить автоматический возврат (%s)',
                $this->status);
        }
        else if ((-1 * $amount) > $this->getOrderRemainSum())
        {
            $allowed = false;
            $decision = sprintf('Автоматический возврат суммы %s руб. невозможен, так как сумма возврата больше, чем сумма доступная для возврата (%s руб.)',
                $amount / 100, $this->getOrderRemainSum() / 100);

        } else {
            //если статус заказа соответствует промежуточному статусу для возврата доставки
            //просчитываем что общая сумма возвратов равно сумме за заказ
            if ($this->status == static::STATUS_REFUND_SHIPPING && $this->refundPayment) {

                $amountSuccess = $this->payment->amount + $this->refundPayment->amount;
                if(!empty($this->second_payment_id)){
                    $amountSuccess += $this->secondPayment->amount;
                }

                $updatedOrder = Order::findOne($this->id); // надо загрузить заново, так как в текущем статусы старые
                /** @var OrderPackage $orderPackage */
                foreach ($updatedOrder->orderPackages as $orderPackage) {
                    if ($orderPackage->status != OrderPackage::STATUS_MONEY_RETURNED){
                        $this->addComment("Автоматический возврат невозможен, так как не позволяет статус ({$orderPackage->status}) пекеджа. Id: {$orderPackage->id} ");
                        return false;
                    }
                    $amountSuccess += $orderPackage->refundPayment->amount;
                }
                $partiallyShippingReturned = $this->getPartialShipmentRefunded();
                $amountSuccess -= $partiallyShippingReturned;
                if ($amountSuccess !== 0) return false;

            } else {
                $updatedOrder = Order::findOne($this->id); // надо загрузить заново, так как в текущем статусы старые
                /** @var OrderPackage $orderPackage */
                foreach ($updatedOrder->orderPackages as $orderPackage) {
                    if (!$orderPackage->isAllowedAutoRefund(0, $commentDecision)){
                        $this->addComment("Автоматический возврат невозможен, так как не позволяет статус ({$orderPackage->status}) package. Id: {$orderPackage->id} ");
                        return false;
                    }
                }
            }
        }

        if (!$allowed && $commentDecision && $decision)
        {
            $this->addComment($decision);

            Yii::$app->trigger(FailedRefundingEvent::class, new FailedRefundingEvent([
                'order_id'         => $this->id,
                'order_package_id' => null,
                'message'          => $decision,
                'amount'           => round($amount / 100, 2),
            ]));
        }

        return $allowed;
    }

    // переиспользуем возврат в заказе, теперь возврат за доставку
    public function successReturnMoney($automated = false, $isPartial = false)
    {
        $user = User::findOne(Yii::$app->user->id);
        $refundPayment = $this->refundPayment;

        if ($refundPayment->gateway_code === 'inner') {
            InnerGateway::fixReturnMoneyForOrder($this);
        }
        $refundPayment->status_code = $isPartial ? Payment::STATUS_REFUNDED_SHIPPING_PARTIAL : Payment::STATUS_REFUND_SHIPPING;
        $refundPayment->finished_at = time();

        if ($refundPayment->save()) {

            $this->status = static::STATUS_MONEY_RETURNED;
            // 11376 время?
            $this->save();

            $this->addComment($automated
                ? 'Возврат за доставку был выполнен в автоматическом режиме'
                : sprintf('Пользователь %s (id:%s) подтвердил успешность возврата платежа(%s) за доставку',
                    $user->username, $user->id, $refundPayment->id));
        }
    }

    public function manualReturnShipment()
    {
        if(!$this->isShipmentMinimal()){
            // осталась не минимальная сумма за доставку.
            $this->addComment('Попытка возврата за доставку: сумма доставки не минимальная, автоматический возврат невозможен');
            return false;
        }
        $refundedShippingPartialSum = $this->getPartialShipmentRefunded();
        $deliveryRefund = ($this->delivery_cost_customer * 100) - $refundedShippingPartialSum;

        $orderRemainSum = $this->getOrderRemainSum();
        if ($deliveryRefund > $orderRemainSum){
            // осталась не минимальная сумма за доставку.
            $this->addComment(sprintf('Попытка возврата за доставку: в заказе меньше денег для возврата (%s ₽), чем остаток за доставку (%s ₽) ',
                $orderRemainSum / 100, $deliveryRefund / 100));
            return false;
        }

        // todo проверить общее количество возвратов
        $user = User::findOne(Yii::$app->user->id);

        // if source payment exists
        if ($payment = $this->payment) {
            // reuse uid and gateway of source payment
            $uid = $payment->uid . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
            $gatewayCode = $payment->gateway_code;
        } else {
            // compose uid by order id and use first available gateway
            $uid = $this->id . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
            $gateways = Yii::$app->payment->gateways;
            $gatewayCode = key($gateways);
        }
//        раскоментить если будет отключен личный счёт
//        if (!empty($this->second_payment_id) || $this->customer->isAllowInnerPayment()) {
//            $gatewayCode = 'inner';
//        }
        $gatewayCode = 'inner';

        $refundPayment = new Payment([
            'gateway_code' => $gatewayCode,
            'uid' => $uid,
            'amount' => -1 * $deliveryRefund,
            'tx_data' => [
                'order_id' => $this->id,
                'order_package_id' => null,
                'payment_id' => $this->payment_id,
                'user_id' => $user->id,
                'description' => 'Возврат денежных средств клиенту за доставку' . $this->id .
                    ' администратором: ' . $user->username . '(' . $user->id . ')',
            ],
            'status_code' => Payment::STATUS_NEW_REFUND_SHIPPING
        ]);

        if ($refundPayment->save()) {
            $this->link('orderPayments', $refundPayment);

            $this->addComment(
                'Пользователь %s (id:%s) инициировал возврат части платежа(%s) на сумму %s руб. за доставку',
                $user->username, $user->id, $refundPayment->id, $deliveryRefund / 100);

            $gateway = Yii::$app->payment->getGateway($gatewayCode);
            $refundPayment->save(false);
            if ($gateway && $gateway->cancelPayment($refundPayment)) {
                $refundPayment->finishedPartialShippingRefunded();
                return true;
            } else {
                $this->addComment('Не удалось выполнить автоматический возврат средств из-за отказа платёжного шлюза.');
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isShipmentMinimal()
    {
        $refundedShippingPartialSum = $this->getPartialShipmentRefunded();
        // проверить уже частично возвращённую сумму за доставку
        $shipmentRemain = $this->delivery_cost_customer - $refundedShippingPartialSum / 100; // rub
        $minShipmentCost = $this->getMinimalShipmentCost(); // rub
        if ($shipmentRemain == $minShipmentCost) {
            return true;
        }
        return false;
    }

    /**
     * @return int|null
     */
    public function getActualDeliveryCost()
    {
        $flagGiftCardOnly = true; // взято из orderEditor
        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct->product->market_id != 28) {
                $flagGiftCardOnly = false;
            }
        }

        $totalPrice = 0;
        $totalCount = 0;
        foreach ($this->orderPackages as $orderPackage) {
            if ($orderPackage->status != OrderPackage::STATUS_MONEY_RETURNED) {
                $totalPrice += $orderPackage->total_price_customer;
                foreach ($orderPackage->packageProducts as $packageProduct) {
                    $totalCount += $packageProduct->quantity;
                }
            }
        }

        $deliveryHelper = DeliveryHelper::ensure($this->customer->id, $flagGiftCardOnly);
        return $deliveryHelper->getDelivery($totalPrice, $totalCount);
    }

    /**
     * @return int
     */
    public function getMinimalShipmentCost()
    {
        $flagGiftCardOnly = true; // взято из orderEditor
        foreach ($this->orderProducts as $orderProduct) {
            if ($orderProduct->product->market_id != 28) {
                $flagGiftCardOnly = false;
            }
        }
        $deliveryHelper = DeliveryHelper::ensure($this->customer->id, $flagGiftCardOnly);
        $minDelivery = $deliveryHelper->getDelivery(1,1);
        return $minDelivery ?? 0;
    }
    /**
     * @return bool
     */
    public function checkRefundPaymentFromPackages()
    {
        foreach ($this->orderPackages as $package) {
            if ($package->refund_payment_id !== null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Проверяет статус package и если ошибка, переводит в статус "Инициирован возврат денег"
     */
    public function checkRefundMoneyForPackage()
    {
        /* @var OrderPackage $package */
        foreach ($this->getOrderPackages()->all() as $package) {
            if (in_array($package->status, [OrderPackage::STATUS_ERROR_NOT_REDEEMED, OrderPackage::STATUS_ERROR_NOT_AVAILABLE]) && $package->refund_payment_id == '') {
                $package->refundMoney(-1, false);
            }
        }
    }

    /**
     * Проверить есть ли в заказе товары
     * которые не выкупили и за которые вернули деньги
     *
     * @return bool
     */
    public function checkPartialRefund()
    {

        foreach ($this->orderPackages as $orderPackage) {
            if (in_array($orderPackage->status, [OrderPackage::STATUS_REFUND_MONEY, OrderPackage::STATUS_MONEY_RETURNED])) {
                return true;
            }
        }

        return false;
    }

    /**
     * ищем заказы,
     * для которых некорретный статус платежа в бд
     * для массвыкупа
     *
     * @param $marketId
     * @return array
     * @throws \yii\db\Exception
     */
    public static function getUnpaidOrder($marketId)
    {
        $unpaidOrder = Yii::$app->db->createCommand('SELECT o.id FROM `order` o
            inner join `order_product` opr on opr.order_id = o.id left join `payment` p on o.payment_id = p.id
            where opr.product_market_id = :marketId and p.status_code <> 2
            and o.status in (:paidStatus, :startStatus, :inBasketStatus, :deliveryStatus)',
            [
                'marketId' => $marketId,
                'paidStatus' => Order::STATUS_PAYMENT_SUCCESS,
                'startStatus' => Order::STATUS_STARTED_PROCESSING,
                'inBasketStatus' => Order::STATUS_IN_BASKET,
                'deliveryStatus' => Order::STATUS_DELIVERY,
            ])->queryAll();
        if (empty($unpaidOrder)) {
            return [];
        }

        $unpaidOrder = ArrayHelper::getColumn($unpaidOrder, 'id');

        return $unpaidOrder;
    }

    /**
     * Adds comments about this order to order_comment.
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
            if(empty($user)){
                $user = User::findOne(User::BOT_ACCOUNT);
            }
        }

        $comment = new OrderComment();
        $comment->user_id = $user->id;
        $comment->entity_id = $this->id;
        $comment->entity_type = OrderComment::TYPE_ORDER;
        $comment->text = $text;

        return $comment->save();
    }

    public function checkShippingRefund()
    {
        if (!$this->refund_payment_id && $this->delivery_cost_customer) {
            Yii::$app->getDb()->enableSlaves = false;
            $user = User::findOne(Yii::$app->user->id);
            $refundedShippingPartialSum = $this->getPartialShipmentRefunded();
            // проверить уже частично возвращённую сумму за доставку
            $totalRefundPrice = (-100 * $this->delivery_cost_customer) + $refundedShippingPartialSum;
            if ($totalRefundPrice < 0) {
                // осталась часть за доставку, которую нужно вернуть
                $isPartialShippingRefund = $refundedShippingPartialSum > 0;
                // ищем похожие по сумме возвраты за последние 10 минут
//            $enableSlaves = Yii::$app->getDb()->enableSlaves;
//            Yii::$app->getDb()->enableSlaves = false;
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

//            Yii::$app->getDb()->enableSlaves = $enableSlaves;
                // проверяем, есть ли среди найденных возвратов, идентичный новому
                foreach ($sameRefunds as $refundPayment) {
                    if (empty(@$refundPayment->tx_data['order_package_id'])
                        && @$refundPayment->tx_data['order_id'] == $this->id) {
                        Yii::$app->session->setFlash('warning', $message = sprintf(
                            'Reject refund money request for order order %s, ' .
                            'because the same one has been found, see payment %s',
                            $this->id, $refundPayment->id));

                        Yii::error($message = sprintf(
                            'Reject refund money request for order %s, ' .
                            'because the same one has been found, see payment %s',
                            $this->id, $refundPayment->id));
                        TelegramQueueJob::push($message, '!!!ALARM Duplicated Refunding',
                            Events::getInnerPaymentWithdrawLogsChatId());
                        return false;
                    }
                }

                // if source payment exists
                if ($payment = $this->payment) {
                    // reuse uid and gateway of source payment
                    $uid = $payment->uid . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
                    $gatewayCode = $payment->gateway_code;
                } else {
                    // compose uid by order id and use first available gateway
                    $uid = $this->id . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
                    $gateways = Yii::$app->payment->gateways;
                    $gatewayCode = key($gateways);
                }
//        раскоментить если надо будет отключить возврат на личный счёт
//            if (!empty($this->second_payment_id) || $this->customer->isAllowInnerPayment()) {
//                $gatewayCode = 'inner';
//            }
                $gatewayCode = 'inner'; // всё возвращаем на личный счёт

                $refundPayment = new Payment([
                    'gateway_code' => $gatewayCode,
                    'uid' => $uid,
                    'amount' => $totalRefundPrice,
                    'tx_data' => [
                        'order_id' => $this->id,
                        'order_package_id' => null,
                        'payment_id' => $this->payment_id,
                        'user_id' => $user->id,
                        'description' => 'Возврат денежных средств клиенту за доставку' . $this->id .
                            ' администратором: ' . $user->username . '(' . $user->id . ')',
                    ],
                    'status_code' => Payment::STATUS_NEW_REFUND_SHIPPING
                ]);

                if ($refundPayment->save()) {

                    $this->refund_payment_id = $refundPayment->id;
                    $this->status = static::STATUS_REFUND_SHIPPING;
                    $this->save();

                    $this->link('orderPayments', $refundPayment);

                    $this->addComment(
                        'Пользователь %s (id:%s) инициировал возврата платежа(%s) на сумму %s руб. за доставку',
                        $user->username, $user->id, $refundPayment->id, $this->delivery_cost_customer - $refundedShippingPartialSum / 100);

                    if ($this->isAllowedAutoRefund($refundPayment->amount, true)) {
                        $gateway = Yii::$app->payment->getGateway($gatewayCode);
                        $refundPayment->save(false);
                        if ($gateway && $gateway->cancelPayment($refundPayment)) {

                            $this->successReturnMoney(true, $isPartialShippingRefund);

                            Yii::$app->trigger(SuccessfulRefundingEvent::class, new SuccessfulRefundingEvent([
                                'order_id' => $this->id,
                                'order_package_id' => null,
                                'amount' => round($refundPayment->amount / 100, 2),
                                'payment_id' => $refundPayment->id,
                            ]));
                        } else {
                            $this->addComment($message =
                                'Не удалось выполнить автоматический возврат средств из-за отказа платёжного шлюза.');

                            Yii::$app->trigger(FailedRefundingEvent::class, new FailedRefundingEvent([
                                'order_id' => $this->id,
                                'order_package_id' => null,
                                'message' => $message,
                                'amount' => round($refundPayment->amount / 100, 2),
                                'payment_id' => $refundPayment->id,
                            ]));
                        }
                    } else {
                        $this->addComment('Не удалось выполнить автоматический возврат средств: не разрешён автоматический возврат');
                    }
                }
            }
        }
    }


    /**
     * Пересчёт доставки при отмене части заказа
     * @return false|void
     */
    public function checkPartialShippingRefund()
    {
        if (!$this->refund_payment_id && $this->delivery_cost_customer) {
            // если не было полного возврата доставки - пересчитываем сумму доставки
            $delivery = $this->getActualDeliveryCost();

            if ($delivery > 0 && $delivery != $this->delivery_cost_customer) {
                // надо пересчитать сумму за доставку
                // если сумма доставки равна нулю - вернули все пекеджи и остаток суммы вернётся автоматически
                $refundedShippingPartialSum = $this->getPartialShipmentRefunded() / 100;

                $deliveryDiff = $this->delivery_cost_customer - $delivery;
                $needToReturn = $deliveryDiff - $refundedShippingPartialSum;
                if ($needToReturn > 0) {
                    $this->addComment('Новая стоимость доставки: ' . (int)$delivery);

                    $user = User::findOne(Yii::$app->user->id);

                    if(empty($user)){
                        $user = User::findOne(User::BOT_ACCOUNT);
                    }
                    // if source payment exists
                    if ($payment = $this->payment) {
                        // reuse uid and gateway of source payment
                        $uid = $payment->uid . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
                        $gatewayCode = $payment->gateway_code;
                    } else {
                        // compose uid by order id and use first available gateway
                        $uid = $this->id . '-' . Order::STATUS_REFUND_SHIPPING . substr(time(), -5);
                        $gateways = Yii::$app->payment->gateways;
                        $gatewayCode = key($gateways);
                    }
//                      раскоментить если будет выключен личный счёт
//                    if (!empty($this->second_payment_id) || $this->customer->isAllowInnerPayment()) {
//                        $gatewayCode = 'inner';
//                    }
                    $gatewayCode = 'inner';

                    $refundPayment = new Payment([
                        'gateway_code' => $gatewayCode,
                        'uid' => $uid,
                        'amount' => -100 * $needToReturn,
                        'tx_data' => [
                            'order_id' => $this->id,
                            'order_package_id' => null,
                            'payment_id' => $this->payment_id,
                            'user_id' => $user->id,
                            'description' => 'Возврат денежных средств клиенту за доставку' . $this->id .
                                ' администратором: ' . $user->username . '(' . $user->id . ')',
                        ],
                        'status_code' => Payment::STATUS_NEW_REFUND_SHIPPING
                    ]);

                    if ($refundPayment->save()) {
                        $this->link('orderPayments', $refundPayment);

                        $this->addComment(
                            'Пользователь %s (id:%s) инициировал возврат части платежа(%s) на сумму %s руб. за доставку',
                            $user->username, $user->id, $refundPayment->id, $needToReturn);

                        $gateway = Yii::$app->payment->getGateway($gatewayCode);
                        $refundPayment->save(false);


                        $orderRemainSum = $this->getOrderRemainSum();
                        if (($needToReturn * 100) > $orderRemainSum) {
                            $this->addComment('Недостаточно денег в заказе для возврата части доставки: ' . (int)$delivery);
                        } else {
                            if ($gateway && $gateway->cancelPayment($refundPayment)) {
                                $refundPayment->finishedPartialShippingRefunded();
                            } else {
                                $this->addComment('Не удалось выполнить автоматический возврат средств из-за отказа платёжного шлюза.');
                            }
                        }
                    }
                }
            }
        }
    }

    public function getPartialShipmentRefunded(){

        $refundedShippingPartialSum = Payment::find()
            ->innerJoin(
                'order_payment',
                'payment.id = order_payment.payment_id'
            )
            ->where('order_payment.order_id=:order_id
                  AND payment.status_code = :partial_shipping_refunded',
                [
                    ':order_id' => $this->id,
                    ':partial_shipping_refunded' => Payment::STATUS_REFUNDED_SHIPPING_PARTIAL,
                ])
            ->sum('amount');

        return -1 * $refundedShippingPartialSum;
    }
    /**
     * Для api мобильного случайны ga для запросов в гугл
     * @return mixed
     */
    public static function getGaByLastOrder()
    {
        return Yii::$app->cache->getOrSet('ga_by_order', function () {
                $order = self::find()->andWhere('ga is not null')->orderBy('id DESC')->one();
                if (!empty($order)) {
                    $ga = str_replace('GA1.2.', '', $order->ga);
                    return $ga;
                }

                //Мой
                return '154006192.1581108458';
            }, 137);
    }

    /**
     * Find all personals promos
     * @return PromocodePersonal[]
     */
    public function searchForUniquePersonalPromos(): array
    {
        /** @var PromocodePersonal[] $personalPromos */
        $personalPromos = array_filter(
            ArrayHelper::getColumn(
                $this->getOrderProducts()->with(['personalPromocode'])->all(),
                'personalPromocode'
            )
        );
        //For scaling, there could be several promos
        $personalPromos = array_unique(array_map('serialize', $personalPromos));
        return array_map('unserialize', $personalPromos);
    }
}
