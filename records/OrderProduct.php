<?php

namespace app\records;

use app\audit\AuditBehavior;
use app\records\Image;
use app\records\ImageQuery;
use app\records\MassOrderProduct;
use app\records\Order;
use app\records\OrderPackage;
use app\records\OrderPackageProduct;
use app\records\OrderPackageProductQuery;
use app\records\OrderProductQuery;
use app\records\OrderProductVisaCard;
use app\records\OrderQuery;
use app\records\Payment;
use app\records\Product;
use app\records\ProductQuery;
use app\records\ProductVariant;
use app\records\ProductVariantQuery;
use app\records\Promocode;
use app\records\PromocodePersonal;
use app\records\RefundedOrderPackageProduct;
use app\telegram\Events;
use app\telegram\TelegramQueueJob;
use Shopfans\Api\UserApi as ShopfansApi;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Order Product Record
 *
 * @property int $id
 * @property string $name_for_declaration
 * @property int $declaration_sfx_id
 * @property int $order_id
 * @property int $product_variant_id
 * @property int $product_id
 * @property int $product_market_id
 * @property int $status
 * @property int $quantity
 * @property double $price_cost_usd
 * @property double $price_customer
 * @property double $price_customer_usd
 * @property double $price_customer_real_usd
 * @property double $price_buyout_usd
 * @property double $total_price_cost_usd
 * @property double $total_price_customer
 * @property double $total_price_customer_usd
 * @property double $total_price_customer_real_usd
 * @property double $total_price_buyout_usd
 * @property double $real_usd_rate
 * @property double $internal_usd_rate
 * @property int $promocode_id
 * @property int $promocode_personal_id
 * @property string $promocode_personal_name_code
 * @property double $promocode_full_price_customer
 * @property double $promocode_discount_customer
 * @property int $startredeem_at
 * @property int $endredeem_at
 *
 * @property Order $order
 * @property ProductVariant $productVariant
 * @property Product $product
 * @property OrderPackageProduct $orderPackageProduct
 * @property OrderPackage $orderPackage
 * @property MassOrderProduct $massOrderProduct
 * @property Image $image
 * @property Promocode $promocode
 * @property PromocodePersonal $personalPromocode
 * @property RefundedOrderPackageProduct $refundedOrderPackageProduct
 * @property OrderProductVisaCard $orderProductVisaCard
 */
class OrderProduct extends ActiveRecord
{
    //Новый
    const STATUS_NEW = 1;
    //Начата обработка
    const STATUS_STARTED_PROCESSING = 2;
    //В корзине
    const STATUS_IN_BASKET = 3;
    //Выкуплено
    const STATUS_REDEEMED = 5;

    //Ошибка, не выкуплено
    const STATUS_ERROR_NOT_REDEEMED = 6;
    //Ошибка (нет в наличии)
    const STATUS_ERROR_NOT_AVAILABLE = 7;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'order_product';
    }

    /**
     * @return OrderProductQuery|\yii\db\ActiveQuery
     */
    public static function find()
    {
        return new OrderProductQuery(get_called_class());
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
    public function rules()
    {
        return [
            [['declaration_sfx_id', 'order_id', 'product_variant_id', 'product_id', 'product_market_id', 'status', 'quantity', 'startredeem_at', 'endredeem_at'], 'integer'],
            [['promocode_id', 'promocode_full_price_customer', 'promocode_discount_customer', 'promocode_personal_id'], 'integer'],
            [['order_id', 'product_variant_id', 'quantity'], 'required'],
            [['price_cost_usd', 'price_customer', 'price_customer_usd', 'price_customer_real_usd', 'price_buyout_usd', 'total_price_cost_usd', 'total_price_customer', 'total_price_customer_usd', 'total_price_customer_real_usd', 'total_price_buyout_usd', 'real_usd_rate', 'internal_usd_rate'], 'number'],
            [['name_for_declaration'], 'string', 'max' => 255],
            [['promocode_personal_name_code'], 'string', 'max' => 36],
            [['order_id'], 'exist', 'skipOnError' => true, 'targetClass' => Order::class, 'targetAttribute' => ['order_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name_for_declaration' => 'Name For Declaration',
            'declaration_sfx_id' => 'Declaration Sfx ID',
            'order_id' => 'Order ID',
            'product_variant_id' => 'Product Variant ID',
            'product_id' => 'Product ID',
            'product_market_id' => 'Product Market ID',
            'status' => 'Status',
            'quantity' => 'Quantity',
            'price_cost_usd' => 'Price Cost Usd',
            'price_customer' => 'Price Customer',
            'price_customer_usd' => 'Price Customer Usd',
            'price_buyout_usd' => 'Price Buyout Usd',
            'total_price_cost_usd' => 'Total Price Cost Usd',
            'total_price_customer' => 'Total Price Customer',
            'total_price_customer_usd' => 'Total Price Customer Usd',
            'total_price_buyout_usd' => 'Total Price Buyout Usd',
            'startredeem_at' => 'Startredeem At',
            'endredeem_at' => 'Endredeem At',
        ];
    }

    /**
     * @return OrderQuery|\yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    /**
     * @return ProductVariantQuery|\yii\db\ActiveQuery
     */
    public function getProductVariant()
    {
        return $this->hasOne(ProductVariant::class, ['id' => 'product_variant_id']);
    }

    /**
     * @return ProductQuery|\yii\db\ActiveQuery
     */
    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    /**
     * @return OrderPackageProductQuery|\yii\db\ActiveQuery
     */
    public function getOrderPackageProduct()
    {
        return $this->hasOne(OrderPackageProduct::class, ['order_product_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRefundedOrderPackageProduct()
    {
        return $this->hasOne(RefundedOrderPackageProduct::class, ['order_product_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrderPackage()
    {
        return $this->hasOne(OrderPackage::class, ['id' => 'order_package_id'])
            ->viaTable('order_package_product', ['order_product_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMassOrderProduct()
    {
        return $this->hasOne(MassOrderProduct::class, ['order_product_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrderProductVisaCard()
    {
        return $this->hasOne(OrderProductVisaCard::class, ['order_product_id' => 'id']);
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        return [
            'id',
            'order_id',
            'name_for_declaration',
            'name' => function () {
                return $this->productVariant->product->name;
            },
            'is_custom_translated_name' => function () {
                return isset($this->productVariant->product->customTranslate->name) ? 1 : 0;
            },
            'declaration_sfx_id',
            'product_id',
            'product_variant_id',
            'product_market_id',
            'quantity',
            'price_customer',
            'price_customer_rub' => function () {
                return $this->price_customer;
            },
            'total_price_customer',
            'total_price_customer_rub' => function () {
                return $this->total_price_customer;
            },

            'origin_name' => function () {
                return $this->productVariant->product->origin_name;
            },
            'brand_name' => function () {
                return @$this->productVariant->product->brand->name;
            },
            'brand_id' => function () {
                return $this->productVariant->product->brand_id;
            },
            'origin_size' => function () {
                if ($this->productVariant->marketSize) {
                    return $this->productVariant->marketSize->name;
                }
                return null;
            },
            'origin_color' => function () {
                return $this->productVariant->origin_color;
            },
            'images' => function () {
                return $this->productVariant->images;
            },
            'image' => function () {
                return $this->image;
            },
            'promocode_id',
            'promocode_name' => function () {
                if (!empty($this->promocode)) {
                    return $this->promocode->name_code;
                }
                return null;
            },
            'orderProductVisaCard',
            'categories' => function () {
                return $this->productVariant->product->categories;
            },
            'genderLabel' => function () {
                return $this->productVariant->product->genderLabel;
            },
            'size_plus' => function () {
                return @$this->productVariant->marketSize->sizePlus ? 1 : null;
            },
            'size_kids' => function () {
                $sizeKids = @$this->productVariant->marketSize->sizeKids;
                if (!empty($sizeKids)) {
                    return $sizeKids->type_id;
                }
                return null;
            }
        ];
    }

    /**
     * @param int $status
     * @return bool
     */
    public function isNextStatus($status)
    {
        switch ($status) {
            case self::STATUS_STARTED_PROCESSING:
                return $this->status == self::STATUS_NEW;
            case self::STATUS_IN_BASKET:
            case self::STATUS_ERROR_NOT_REDEEMED:
            case self::STATUS_ERROR_NOT_AVAILABLE:
                return $this->status == self::STATUS_STARTED_PROCESSING;
            default:
                return false;
        }
    }

    /**
     * @return float
     */
    public function getSumm()
    {
        if (empty($this->price)) {
            return $this->log->data['ListPrice'] * $this->quantity;
        }

        return $this->price * $this->quantity;
    }

    /**
     * @return array
     */
    public function getCheckStatus()
    {
        //Если сам заказ не в статусах новый или начата обработка
        if (!in_array($this->order->status, [Order::STATUS_NEW, Order::STATUS_STARTED_PROCESSING, Order::STATUS_DELIVERY])) {
            return [];
        }

        //Если сам package не в статусах новый или начата обработка
        if (!in_array($this->orderPackage->status, [OrderPackage::STATUS_NEW, OrderPackage::STATUS_STARTED_PROCESSING, OrderPackage::STATUS_DELIVERY])) {
            return [];
        }

        //Если хоть один товар в package в статусе не выкуплен или нет в наличии
        foreach ($this->orderPackage->packageProducts as $packageProduct) {
            if (in_array($packageProduct->status, [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE])) {
                return [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE];
            }
        }

        switch ($this->status) {
            case OrderProduct::STATUS_NEW:
            case OrderProduct::STATUS_STARTED_PROCESSING:
                return [OrderProduct::STATUS_STARTED_PROCESSING, OrderProduct::STATUS_IN_BASKET, OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE];
            case OrderProduct::STATUS_IN_BASKET:
                return [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE];
            default:
                return [];
        }
    }

    /**
     * Доступный переход в статусы при массовом выкупе
     *
     * @return array
     */
    public function getCheckStatusForMassOrder()
    {
        //Если сам заказ не в статусах новый или начата обработка
        if (!in_array($this->order->status, [Order::STATUS_NEW, Order::STATUS_STARTED_PROCESSING, Order::STATUS_PAYMENT_SUCCESS, Order::STATUS_DELIVERY])) {
            return [];
        }

        //Если сам package не в статусах новый или начата обработка
        if (!in_array($this->orderPackage->status, [OrderPackage::STATUS_NEW, OrderPackage::STATUS_STARTED_PROCESSING, OrderPackage::STATUS_DELIVERY])) {
            return [];
        }

        //Если хоть один товар в package в статусе не выкуплен или нет в наличии
        foreach ($this->orderPackage->packageProducts as $packageProduct) {
            if (in_array($packageProduct->status, [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE])) {
                return [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE];
            }
        }

        switch ($this->status) {
            case OrderProduct::STATUS_NEW:
            case OrderProduct::STATUS_STARTED_PROCESSING:
            case OrderProduct::STATUS_IN_BASKET:
                return [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE];
            default:
                return [];
        }
    }

    /**
     * Обновляет статус для package и order
     * Не использовать в циклах при обработках товаров
     */
    public function updateAllStatus()
    {
        $this->orderPackage->updateStatusByProductStatus();
        $this->order->updateStatusByPackageStatus();
    }

    /**
     * @return array
     * TODO Презентационная логика
     */
    public function classAlert()
    {
        return [
            OrderProduct::STATUS_NEW => 'warning',
            OrderProduct::STATUS_STARTED_PROCESSING => 'success',
            OrderProduct::STATUS_IN_BASKET => 'info',
            OrderProduct::STATUS_REDEEMED => 'info',
            OrderProduct::STATUS_ERROR_NOT_REDEEMED => 'danger',
            OrderProduct::STATUS_ERROR_NOT_AVAILABLE => 'danger'
        ];
    }

    /**
     * @return array
     */
    public function textStatus()
    {
        return [
            OrderProduct::STATUS_NEW => 'Новая',
            OrderProduct::STATUS_STARTED_PROCESSING => 'Начали обработку',
            OrderProduct::STATUS_IN_BASKET => 'В корзине',
            OrderProduct::STATUS_REDEEMED => 'Выкуплено',
            OrderProduct::STATUS_ERROR_NOT_REDEEMED => 'Ошибка, не выкуплено',
            OrderProduct::STATUS_ERROR_NOT_AVAILABLE => 'Нет в наличии'
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
    public function getTextStatus()
    {
        return $this->textStatus()[$this->status];
    }

    /**
     * Ссылка для декларации с GET параметром для таможни
     * @return string
     */
    public function getUrlForDeclaration()
    {
        $usmallUrl = $this->productVariant->product->getUsmallUrl();

        return $usmallUrl . '?pid=' . $this->id;
    }

    /**
     * Цена для декралации
     * @return false|float
     */
    public function getTotalPriceForDeclaration()
    {
        return round($this->total_price_customer_usd, 2);
    }

    /**
     * Цена для декралации
     * @return false|float
     */
    public function getPriceForDeclaration()
    {
        return round($this->price_customer_usd, 2);
    }

    public function changePrice($price)
    {
        $this->price_buyout_usd = $price;
        $this->total_price_buyout_usd = round($price * $this->quantity, 2);
        $this->save();
        $this->order->updateAllTotalPrice();
    }

    public function changeTotalPrice($total)
    {
        $shopfans = $this->order->customer->shopfans;

        $declatarionSfx = $shopfans->getDeclatarionSfxById($this->declaration_sfx_id);

        $message = sprintf('orderId = %s, orderPackageId = %s, orderProductId = %s, declarationSfxId = %s, declatarionSfx = %s',
            $this->order_id, $this->orderPackage->id, $this->id, $this->declaration_sfx_id, $declatarionSfx);
        TelegramQueueJob::push($message, 'Change Total Price, Order Product', Events::getGreenLogsChatId());

        try {
            $shopfans->updateDeclarationSfx(
                $this->declaration_sfx_id,
                $declatarionSfx['package_id'],
                $declatarionSfx['description'],
                $declatarionSfx['weight'],
                $declatarionSfx['quantity'],
                $total,
                $declatarionSfx['recipient_id'],
                $declatarionSfx['product_id'],
                $declatarionSfx['url'],
                $this->product->defaultCategory->hsCode->hscode ?? null
            );
        } catch (\Exception $e) {
            TelegramQueueJob::push($e->getCode() . $e->getMessage(), 'Exception', Events::getGreenLogsChatId());
            \Yii::error($e->getCode() . $e->getMessage());
        }

        TelegramQueueJob::push('_____________________', 'Ending', Events::getGreenLogsChatId());

        $this->price_buyout_usd = round($total / $this->quantity, 2);
        $this->total_price_buyout_usd = $total;
        $this->save();

        $this->order->updateAllTotalPrice();
    }

    /**
     * @param $marketId
     * @return OrderProductQuery|\yii\db\ActiveQuery
     */
    public static function getBuybackProduct($marketId)
    {
        $orderProduct = new self();
        $query = $orderProduct->find()
            ->joinWith('order')
            ->joinWith('orderPackage')
            ->innerJoin(['p' => Payment::tableName()], '{{p}}.[[id]] = {{order}}.[[payment_id]]')
            ->andwhere([
                'product_market_id' => $marketId,
                'p.status_code' => Payment::STATUS_SUCCESS
            ])
            ->andWhere(['>', 'order.id', 200])
            ->andWhere([
                'order.status' =>
                    [Order::STATUS_PAYMENT_SUCCESS, Order::STATUS_STARTED_PROCESSING,
                        Order::STATUS_IN_BASKET, Order::STATUS_DELIVERY]
            ])
            ->andWhere([
                'op.status' =>
                    [OrderPackage::STATUS_STARTED_PROCESSING, OrderPackage::STATUS_IN_BASKET,
                        OrderPackage::STATUS_NEW]
            ])
            ->orderBy(['op.order_id' => SORT_ASC, 'id' => SORT_ASC, 'op.id' => SORT_ASC])
            ->limit(300);
        return $query;
    }

    /**
     * временный костыль на получение количества товаров на выкуп
     *
     * @return array|\yii\db\DataReader
     * @throws \yii\db\Exception
     */
    public static function getCountBuybackProduct()
    {
        $count = \Yii::$app->db->createCommand('SELECT `product_market_id` as `market`, COUNT(1) AS `count` FROM `order_product` LEFT JOIN `order` ON `order_product`.`order_id` = `order`.`id` LEFT JOIN `order_package_product` ON `order_product`.`id` = `order_package_product`.`order_product_id` LEFT JOIN `order_package` `op` ON `order_package_product`.`order_package_id` = `op`.`id` WHERE `order`.`id` > 200 AND (`order`.`status` IN (101, 2, 3, 4)) AND (`op`.`status` IN (2, 3, 1)) GROUP BY `product_market_id`')->queryAll();

        return ArrayHelper::index($count, 'market');
    }

    public function behaviors()
    {
        return [
//            'audit' => AuditBehavior::class,
        ];
    }

    /**
     * Считает итоговую сумму за товары
     * позапросу считает количество товаров
     * для массового выкупа
     * @param $orderProducts
     * @param $count
     * @return array|float|int
     */
    public static function getTotalPriceCart($orderProducts, $count = false)
    {
        $totalPriceCart = 0;
        $totalCountProductCart = 0;
        $totalCountPcsCart = 0;

        /** @var self $orderProduct */
        foreach ($orderProducts as $orderProduct) {
            if ($orderProduct->total_price_buyout_usd == null) {
                $totalPriceCart += $orderProduct->total_price_cost_usd;
            } else {
                $totalPriceCart += $orderProduct->total_price_buyout_usd;
            }
            $totalCountPcsCart += $orderProduct->quantity;
            $totalCountProductCart++;
        }
        if ($count === true) {
            return ['amount' => $totalPriceCart,
                'count_product' => $totalCountProductCart,
                'count_pcs' => $totalCountPcsCart
            ];
        }
        return $totalPriceCart;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert || empty($changedAttributes) || $this->status !== self::STATUS_ERROR_NOT_AVAILABLE) {
            return;
        }

        $productVariant = ProductVariant::find()->byId($this->product_variant_id)->one();
        $productVariant->stock_count = 0;
        $productVariant->losted_at = time();
        $productVariant->save(false);
    }

    /**
     * @return ImageQuery|\yii\db\ActiveQuery
     */
    public function getImage()
    {
        return $this->hasOne(Image::class, ['id' => 'image_id'])->viaTable('order_product_image', ['order_product_id' => 'id']);
    }

    /**
     * Делает рассчет новой стоимости товара для СП
     *
     * @return array
     */
    public function getNewPriceSP()
    {
        //Раньше тут был для СПешниц - выпилили совсем
        return [];
    }

    /**
     * @return OrderQuery|\yii\db\ActiveQuery
     */
    public function getPromocode()
    {
        return $this->hasOne(Promocode::class, ['id' => 'promocode_id']);
    }

    /**
     * @return OrderQuery|\yii\db\ActiveQuery
     */
    public function getPersonalPromocode()
    {
        return $this->hasOne(PromocodePersonal::class, ['id' => 'promocode_personal_id']);
    }

    /**
     * автопересчет сумм стоимости товара
     * @return void
     */
    public function updateTotalPrice()
    {
        $this->total_price_customer = $this->quantity * $this->price_customer;
        $this->total_price_cost_usd = $this->quantity * $this->price_cost_usd;
        $this->total_price_buyout_usd = $this->quantity * $this->price_buyout_usd;
        $this->total_price_customer_usd = $this->quantity * $this->price_customer_usd;
        $this->total_price_customer_real_usd = $this->quantity * $this->price_customer_real_usd;
    }

}
