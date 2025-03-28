<?php

namespace app\models;

//use app\records\Customer;
use app\records\MassOrderDiscount;
use app\records\MassOrderProduct;
use app\records\EmailParse;
use app\records\Order;
use app\records\OrderPackage;
//use app\records\OrderPackageProduct;
//use app\records\OrderPayment;
use app\records\OrderProduct;
//use app\records\Payment;
//use app\records\Product;
//use app\records\ProductVariant;
//use app\records\Parsing;
//use app\records\Brand;
//use app\records\MarketSize;
//use app\records\SplitPackage;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class RequestReplicationEditor extends Model
{
    const SCENARIO_SPLIT_PACKAGE = 'split_package';
    const SCENARIO_MASS_ORDERS = 3;

    public $entity_ids;
    public $init_flag; // Атрибут для хранения флага предварительного запроса для brand, parsing, market_size

    public $split_package_id;
    public $prepare_order_id_list;
    public $prepare_sql_queries;

    public $mass_order_discount_id;
    public $show_order_ids;
    public $add_order_queries;

    const BRAND_ID = 2;
    const PARSING_ID = 27;
    const FIRST_PARSING_ID = 27;
    const LAST_PARSING_ID = 27;
    const APPROVED_BY = 1;
    const MARKET_SIZE_ID = 16;
    const ENTITY_IDS_SEPARATOR = ',';

    /**
     * @return array
     */
    public function rules()
    {
        return [
            ['entity_ids', 'string'],
            ['entity_ids', 'required'],
            ['init_flag', 'boolean'],

            // scenario split_package
            ['split_package_id', 'required'],
            ['split_package_id', 'exist', 'targetClass' => SplitPackage::class, 'targetAttribute' => 'id'],
            [['prepare_order_id_list', 'prepare_sql_queries'], 'boolean'],

            ['mass_order_discount_id', 'required'],
            ['mass_order_discount_id', 'integer'],
//            [['show_order_ids', 'add_order_queries'], 'boolean'],
            ['mass_order_discount_id', 'exist', 'targetClass' => MassOrderDiscount::class, 'targetAttribute' => 'id'],
        ];
    }

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['entity_ids', 'init_flag'],
            self::SCENARIO_SPLIT_PACKAGE => ['split_package_id', 'prepare_order_id_list', 'prepare_sql_queries'],
            self::SCENARIO_MASS_ORDERS => ['mass_order_discount_id', 'show_order_ids', 'add_order_queries'],
        ];
    }

//    public function splitPackage()
//    {
//        $splitPackage = SplitPackage::findOne($this->split_package_id);
//        $orderPackage = OrderPackage::find()->where(['in', 'sf_package_id', array_filter(explode(',', $splitPackage->split))])->all();
//        $this->entity_ids = implode(self::ENTITY_IDS_SEPARATOR, array_unique(ArrayHelper::getColumn($orderPackage, 'order_id')));
//
//        $query = $this->getRequestEmailParsing();
//        $query['querySplitPackage'] = $this->getInsertQuery(SplitPackage::tableName(), $splitPackage->attributes) . ';';
//
//        return $query;
//    }

    /**
     * Получить данные заказа для тестировая email parsing
     * @return array
     */
    public function getRequestEmailParsing(): array
    {
        $orderIds = explode(self::ENTITY_IDS_SEPARATOR, $this->entity_ids);
        $orderIds = array_unique($orderIds);

        echo '<pre>';
        var_dump(
            $orderIds
        );
        echo '</pre>';
        die;

        $queryProduct = $this->getProductData($orderIds);
        $queryOrder = $this->getOrderData($orderIds);

        return [
            'initialQuery' => [],
            'queryProduct' => implode('; ', $queryProduct) . ';',
            'queryOrder' => implode('; ', $queryOrder)
        ];
    }

    private function getProductData(array $orderIds): array
    {
        $queryProduct = [];
        foreach ($orderIds as $orderId) {
            if (is_numeric($orderId)) {
                $orderId = (int)$orderId;
                $orderData = $this->getOrder($orderId);
                if ($orderData) {
                    $orderProductData = $this->getOrderProduct($orderData['orderProductId']);
                    $productData = $this->getProduct($orderProductData['productId']);
                    $productVariantData = $this->getProductVariant($orderProductData['productVariantId']);

                    $queryProduct[] = $productData['query'];
                    $queryProduct[] = $productVariantData['query'];
                }
            }
        }

        return $queryProduct;
    }

    private function getOrderData(array $orderIds): array
    {
        $queryOrder = [];
        foreach ($orderIds as $orderId) {
            if (is_numeric($orderId)) {
                $orderId = (int)$orderId;
                $orderData = $this->getOrder($orderId);
                if ($orderData) {
                    $customerData = $this->getCustomer($orderData['orderCustomerId']);
                    $paymentData = $this->getPayments($orderId);
                    $orderPaymentData = $this->getOrderPaymentLink($orderId);
                    $orderPackageData = $this->getOrderPackage($orderData['orderPackageId']);
                    $orderProductData = $this->getOrderProduct($orderData['orderProductId']);
                    $orderPackageProductData = $this->getOrderPackageProduct($orderData['orderPackageId']);

                    $queryOrder[] = $paymentData['query'];
                    $queryOrder[] = $orderData['query'];
                    $queryOrder[] = $customerData['query'];
                    $queryOrder[] = $orderPaymentData['query'];
                    $queryOrder[] = $orderPackageData['query'];
                    $queryOrder[] = $orderProductData['query'];
                    $queryOrder[] = $orderPackageProductData['query'];
                }
            }
        }

        return $queryOrder;
    }

    public function getOrderIds(): array
    {
        return MassOrderProduct::find()
            ->select(['order_id'])
            ->distinct()
            ->where(['mass_order_discount_id' => $this->mass_order_discount_id])
            ->asArray()
            ->column()
        ;
    }

//    public function getMassOrdersHistoryQueries()
//    {
//        $separator = ";\n";
//        $orderIds = $this->getOrderIds();
//
//        $massOrderDiscountQuery = $this->getInsertQuery(
//            MassOrderDiscount::tableName(),
//            MassOrderDiscount::findOne($this->mass_order_discount_id)->attributes
//        );
//
//        $massOrderProductBatchQuery = $this->getMassOrderProductBatchQuery(
//            $this->mass_order_discount_id
//        );
//
//        $queries = $massOrderDiscountQuery
//            . $separator
//            . $massOrderProductBatchQuery;
//
//        return $queries;
//    }

//    private function getMassOrderProductBatchQuery ($massOrderDiscountId): string
//    {
//        $products = MassOrderProduct::find()
//            ->where(['mass_order_discount_id' => $massOrderDiscountId])
//            ->asArray()
//            ->all()
//        ;
//
//        $massOrderProductBatchQuery = $this->getBatchInsertQuery(
//            MassOrderProduct::tableName(),
//            [
//                'id',
//                'mass_order_discount_id',
//                'order_id',
//                'order_product_id',
//                'quantity',
//                'created_at'
//            ],
//            $products
//        );
//
//        return $massOrderProductBatchQuery;
//    }

    private function getOrder($orderId): bool|array
    {
        $order = Order::findOne($orderId);
        if ($order) {
            $orderPackageId = $order->getOrderPackages()->select('op.id')->asArray()->all();
            $orderProductId = $order->getOrderProducts()->select('order_product.id')->asArray()->all();
            return [
                'query' => $this->getInsertQuery(Order::tableName(), $order->attributes),
                'orderPackageId' => ArrayHelper::getColumn($orderPackageId, 'id'),
                'orderProductId' => ArrayHelper::getColumn($orderProductId, 'id'),
                'orderPaymentId' => $order->payment_id,
                'orderSecondPaymentId' => $order->second_payment_id ? $order->second_payment_id : null,
                'orderCustomerId' => $order->customer_id,
            ];
        }
        return false;
    }

//    /**
//     * @param $id
//     * @return array
//     */
//    private function getOrderPayment($id)
//    {
//        $payment = Payment::findOne($id);
//        $params = $payment->attributes;
//        unset($params['tx_data']);
//        $params['success_url'] = 't';
//        $params['fail_url'] = 'f';
//
//        return [
//            'query' => $this->getInsertQuery(Payment::tableName(), $params),
//        ];
//    }

//    /**
//     * @param $brandId, $parsingId, $marketSizeId
//     * @return array
//     */
//    private function getInitialQuery($brandId, $parsingId, $marketSizeId)
//    {
//        $parsing_params = Parsing::findOne($parsingId)->attributes;
//        $brand_params = Brand::findOne($brandId)->attributes;
//        $marketSie_params = MarketSize::findOne($marketSizeId)->attributes;
//
//        return [
//            $this->getInsertQuery(Parsing::tableName(), $parsing_params),
//            $this->getInsertQuery(Brand::tableName(), $brand_params),
//            $this->getInsertQuery(MarketSize::tableName(), $marketSie_params),
//        ];
//    }

//    /**
//     * @param $orderId
//     * @return array
//     */
//    private function getPayments($orderId)
//    {
//        // ищем все линки платежей по заказу
//        $orderPayment = OrderPayment::find()
//            ->where(['order_id' => $orderId])
//            ->asArray()
//            ->all();
//        $paymentId = ArrayHelper::getColumn($orderPayment, 'payment_id');
//        // ищем все платежи по id
//        $payments = Payment::find()->where(['id' => $paymentId])->all();
//        $queryPayments = [];
//        if ($payments) {
//            foreach ($payments as $payment) {
//                $params = $payment->attributes;
//                unset($params['tx_data']);
//                $params['success_url'] = 't';
//                $params['fail_url'] = 'f';
//                $queryPayments[] = $this->getInsertQuery(Payment::tableName(), $params);
//            }
//        }
//        // формируем запрос для платежей
//        return ['query' => implode(';', $queryPayments)];
//    }

//    private function getOrderPaymentLink($orderId)
//    {
//        $queryPayments = [];
//        // ищем все линки платежей по заказу
//        $orderPaymentLinks = OrderPayment::find()->where(['order_id' => $orderId])->all();
//        if ($orderPaymentLinks) {
//            foreach ($orderPaymentLinks as $link) {
//                $params = $link->attributes;
//                $queryPayments[] = $this->getInsertQuery(OrderPayment::tableName(), $params);
//            }
//        }
//        // формируем запрос по линкам платежей
//        return ['query' => implode(';', $queryPayments)];
//    }

    /**
     * @param array $id
     * @return string[]
     */
    private function getOrderPackage(array $id)
    {
        $orderPackages = OrderPackage::find()->where(['id' => $id]);
        $items = [];
        $columns = null;
        /** @var OrderPackage $orderPackage */
        foreach ($orderPackages->each() as $orderPackage) {
            $items[] = $orderPackage->attributes;
            if ($columns == null) {
                $columns = $orderPackage->attributes;
            }
        }
        $query = $this->getBatchInsertQuery(orderPackage::tableName(), array_keys($columns), $items);


        return [
            'query' => $query,
        ];
    }

    /**
     * @param array $id
     * @return array
     */
    private function getOrderProduct(array $id)
    {
        $productsId = [];
        $productVariantsId = [];
        $orderProducts = OrderProduct::find()->where(['id' => $id]);
        $items = [];
        $columns = null;
        /** @var OrderProduct $orderProduct */
        foreach ($orderProducts->each() as $orderProduct) {
            $items[] = $orderProduct->attributes;
            $productsId[] = $orderProduct->product_id;
            $productVariantsId[] = $orderProduct->product_variant_id;
            if ($columns == null) {
                $columns = $orderProduct->attributes;
            }
        }
        $query = $this->getBatchInsertQuery(OrderProduct::tableName(), array_keys($columns), $items);


        return [
            'query' => $query,
            'productId' => $productsId,
            'productVariantId' => $productVariantsId,
        ];
    }

    /**
     * @param $packageId
     * @return array
     */
    private function getOrderPackageProduct($packageId)
    {
        $columns = [];
        $items = [];
        $orderPackageProducts = OrderPackageProduct::find()->where(['order_package_id' => $packageId]);
        /** @var OrderPackageProduct $orderPackageProduct */
        foreach ($orderPackageProducts->each() as $orderPackageProduct) {
            $items[] = $orderPackageProduct->attributes;
            if ($columns == null) {
                $columns = $orderPackageProduct->attributes;
            }
        }
        $query = $this->getBatchInsertQuery(OrderPackageProduct::tableName(), array_keys($columns), $items);

        return [
            'query' => $query,
        ];
    }

    /**
     * @param $id
     * @return array
     */
    private function getProductVariant($id)
    {
        $columns = [];
        $items = [];
        $productVariants = ProductVariant::find()->where(['id' => $id]);
        /** @var ProductVariant $productVariant */
        foreach ($productVariants->each() as $productVariant) {
            $attributes = $productVariant->attributes;
            $attributes['market_size_id'] = 16;
            $items[] = $attributes;
            if ($columns == null) {
                $columns = $attributes;
            }
        }
        $query = $this->getBatchInsertQuery(ProductVariant::tableName(), array_keys($columns), $items);

        return [
            'query' => htmlspecialchars($query) . ' ON DUPLICATE KEY UPDATE description = VALUES(description)',
        ];
    }

    /**
     * @param $id
     * @return array
     */
    private function getProduct($id)
    {
        $columns = [];
        $items = [];
        $products = Product::find()->where(['id' => $id]);
        /** @var Product $product */
        foreach ($products->each() as $product) {
            $attributes = $product->attributes;
            $attributes['description'] = '';
            unset($attributes['default_variant_id']);
            unset($attributes['default_category_id']);
            unset($attributes['market_brand_id']);
            $attributes['brand_id'] = self::BRAND_ID;
            $attributes['first_parsing_id'] = self::FIRST_PARSING_ID;
            $attributes['last_parsing_id'] = self::LAST_PARSING_ID;
            $attributes['approved_by'] = self::APPROVED_BY;
            $items[] = $attributes;
            if ($columns == null) {
                $columns = $attributes;
            }
        }
        $query = $this->getBatchInsertQuery(Product::tableName(), array_keys($columns), $items);

        return [
            'query' => htmlspecialchars($query) . 'ON DUPLICATE KEY UPDATE description = VALUES(description)',
        ];
    }

    private function getCustomer($id)
    {
        $customer = Customer::findOne($id);
        if (
            !in_array($customer->gender, ['male', 'female'])
        )
        {
            $customer->gender = 'male';
        }
        $params = $customer->attributes;
        if (
            !in_array($customer->gender, ['male', 'female'])
        )
        {
            $customer->gender = 'male';
        }
        $params['phone'] = '+999';
        $params['email'] = '123' . $params['email'];
        $params['email_shopfans'] = '123' . $params['email_shopfans'];

        return [
            'query' => $this->getInsertQuery(Customer::tableName(), $params) . ' ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
        ];
    }

    /**
     * @param $tableName
     * @param $params
     * @return string
     */
    private function getInsertQuery($tableName, $params)
    {
        $sql = \Yii::$app->db->getQueryBuilder()->insert($tableName, $params, $params);
        $query = \Yii::$app->db->createCommand()->setSql($sql)->bindValues($params)->getRawSql();

        return $query;
    }

    /**
     * @param $table
     * @param $columns
     * @param $rows
     * @return string
     */
    private function getBatchInsertQuery($table, $columns, $rows)
    {
        $sql = \Yii::$app->db->getQueryBuilder()->batchInsert($table, $columns, $rows);
        return $sql;
    }

    public function getRequestEmailParseData()
    {
        $entityIds = array_map('trim', array_unique(explode(self::ENTITY_IDS_SEPARATOR, $this->entity_ids)));

        $emailParseData = EmailParse::find()
            ->where(['in', 'id', $entityIds])
            ->orWhere(['in', 'external_order_id', $entityIds])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        $queryString = [];

        foreach ($emailParseData as $emailParse) {
            $queryString[] = $this->getInsertQuery(EmailParse::tableName(), $emailParse);
        }

        $queryString = implode(';', $queryString);

        return [
            'query' => $queryString,
        ];
    }
}
