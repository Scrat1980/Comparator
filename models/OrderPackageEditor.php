<?php

namespace app\models;

use app\records\Audit;
use app\records\Order;
use app\records\OrderComment;
use app\records\OrderPackage;
use app\records\OrderProduct;
use app\records\SplitPackage;
use app\records\User;
use app\telegram\Events;
use app\telegram\TelegramQueueJob;
use Shopfans\Api\UserApi as ShopfansApi;
use Shopfans\Api\UserApiException;
use yii\base\Model;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Order Package Editor
 */
class OrderPackageEditor extends Model
{
    const REDIRECT_SPLIT = 'redirect_split';
    const REDIRECT_ORDER = 'redirect_order';
    const REDIRECT_ORDER_SF_ERROR = 'redirect_order_sf_error';

    const SCENARIO_CHANGE_TRACK_NUMBER = 'change_track_number';
    const SCENARIO_CHANGE_TRACK_NUMBER_BOT = 'change_track_number_bot';
    const SCENARIO_PARTITION_PACKAGE = 'partition_package';

    public $tracking_number;
    public $package_id;
    public $order_id;
    //для понимания кто вызвал
    public $type_changed = 0;
    //признак один трек-номер на несколько external_number
    public $stop_ship = 0;
    public $productIds;
    public $packageId;
    public $quantityProducts;
    public $newPackageId;

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return [
            self::SCENARIO_CHANGE_TRACK_NUMBER => ['tracking_number', 'package_id', 'order_id'],
            self::SCENARIO_CHANGE_TRACK_NUMBER_BOT => ['tracking_number', 'package_id', 'order_id', 'type_changed', 'stop_ship'],
            self::SCENARIO_PARTITION_PACKAGE => ['productIds', 'packageId', 'quantityProducts'],
        ];
    }

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
            ['tracking_number', 'required'],
            ['package_id', 'required'],
            ['order_id', 'required'],
            [['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => 'id'],
            [['package_id'], 'exist', 'targetClass' => OrderPackage::class, 'targetAttribute' => 'id'],
            ['tracking_number', 'string'],
            ['type_changed', 'integer'],
            [['productIds', 'packageId'], 'required', 'on' => [self::SCENARIO_PARTITION_PACKAGE]],
            [['packageId'], 'exist', 'targetClass' => OrderPackage::class, 'targetAttribute' => 'id', 'on' => [self::SCENARIO_PARTITION_PACKAGE]],
            [['productIds'], 'exist', 'targetClass' => OrderProduct::class, 'targetAttribute' => 'id', 'allowArray' => true, 'on' => [self::SCENARIO_PARTITION_PACKAGE]],
        ];
    }

    /**
     * @return OrderPackage|null
     * @throws NotFoundHttpException
     */
    private function getOrderPackage()
    {
        if ($orderPackage = OrderPackage::findOne($this->package_id)) {
            return $orderPackage;
        }
        throw new NotFoundHttpException('orderPackage not found');
    }

    /**
     * @return string
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function changeTrackNumber()
    {
        $orderPackage = $this->getOrderPackage();
        if (empty($orderPackage->sf_package_id)) {
            throw new BadRequestHttpException('Посылки не существует');
        }
        if (!in_array($orderPackage->status, [
            OrderPackage::STATUS_REDEEMED,
            OrderPackage::STATUS_REDEEMED_TRACK_NUMBER
        ])) {
            throw new BadRequestHttpException('В текущем статусе действие невозможно');
        }
        if (empty($orderPackage->external_order_id)) {
            throw new BadRequestHttpException('Не указан номер заказа в магазине');
        }
        //на всякий случай
        if ($this->tracking_number == '') {
            throw new BadRequestHttpException('Что за фигня - трек-номер пустой(пустая строка)');
        }

        //Если вызвал изменение парсел email
        if ($this->type_changed == 1) {
            if ($this->tracking_number == $orderPackage->tracking_number) {
                throw new BadRequestHttpException('Одинаковые трек номера - уже установлен ' . $this->tracking_number);
            }
            if (!empty($orderPackage->tracking_number) && $orderPackage->tracking_number != '' && $this->tracking_number != $orderPackage->tracking_number) {
                throw new BadRequestHttpException(sprintf('У package_id = %s (order = %s) уже есть трек номер %s, и попытка сменить на %s',
                    $orderPackage->id, $orderPackage->order_id, $orderPackage->tracking_number, $this->tracking_number));
            }
        }

        if ($this->tracking_number != '') {
            $packageExistTracking = OrderPackage::find()->byTrackingNumber($this->tracking_number)->notId($orderPackage->id)->one()
                ?: SplitPackage::find()->byTrackingNumber($this->tracking_number)->one();
            if ($packageExistTracking) {
                return self::REDIRECT_SPLIT;
            }
        }
        $packageSf = $orderPackage->order->customer->shopfans->getPackageById($orderPackage->sf_package_id);
        $packageDetails = $orderPackage->order->customer->shopfans->updatePackage(
            $orderPackage->sf_package_id,
            $packageSf['store'],
            $packageSf['order'],
            $this->tracking_number,
            $packageSf['name'],
            $packageSf['weight'],
            $packageSf['notes']
        );
        /* Идентификатор заказа может измениться на другой, если номер отслеживания пришел позже
         * чем реальная посылка с этим номером пришла на склад. В этом случае Shopfans переносит
         * декларацию из обновляемой посылки в реально прибывшую с таким номером отслеживания. Так
         * же пришедшая посылка переносится в исходящую посылку, чтобы не менять sf_shipment_id.
         */
        if (isset($packageDetails['id']) && $packageDetails['id'] != $orderPackage->sf_package_id) {
            $sOldPackageId = $orderPackage->sf_package_id;
            $orderPackage->sf_package_id = $packageDetails['id'];
            try {
                $text = "Добавлен sf_package_id={$packageDetails['id']}";
                if(!empty($sOldPackageId)){
                    $text .= " (заменён с {$sOldPackageId})";
                }
                $orderPackage->addComment($text);
                $text .= " (OrderPackageEditor.php:154 changeTrackNumber)";
                TelegramQueueJob::push($text, 'Change sf_package_id', Events::getSfPackageIdChangeChatId());
                Yii::info($text, 'requests-shopfans');
            } catch (\Exception $e) {
            }
            if ($orderPackage->save(false)) {
                Audit::log($orderPackage,
                    sprintf('Shopfans Package ID has been changed from #%s to #%s',
                        $sOldPackageId, $orderPackage->sf_package_id));
            }
        }

        $orderPackage->tracking_number = $this->tracking_number;
        $orderPackage->redeemtrack_at = time();
        $orderPackage->status = OrderPackage::STATUS_REDEEMED_TRACK_NUMBER;
        $orderPackage->save();
        $orderPackage->order->updateStatusByPackageStatus();

        if(Yii::$app instanceof yii\console\Application){
            $user = User::findOne(User::BOT_ACCOUNT);
        } else {
            $user = User::findOne(Yii::$app->user->id);
        }
        $comment = new OrderComment([
            'user_id' => $user->id,
            'entity_type' => OrderComment::TYPE_PACKAGE,
            'entity_id' => $orderPackage->id,
            'text' => sprintf('Пользователь %s(%s) добавил трек %s',
                $user->username, $user->id, $this->tracking_number
            ),
        ]);
        $comment->save();

        if ($this->stop_ship) {
            try {
                $stopShipment = $orderPackage->order->customer->shopfans->stopShipment($orderPackage->sf_shipment_id);
                $message = sprintf('stopShipment sf_shipment_id = %s order_package = %s order = %s',
                    $orderPackage->sf_shipment_id, $orderPackage->id, $orderPackage->order_id);
                TelegramQueueJob::push($message, 'stopShipment', Events::getSplitDisChatId());
            } catch (\Exception $exception) {
                $comment = new OrderComment([
                    'user_id' => $user->id,
                    'entity_type' => OrderComment::TYPE_PACKAGE,
                    'entity_id' => $orderPackage->id,
                    'text' => sprintf('!!!ОШИБКА ШФ при stopShipment sf_shipment_id = %s сообщение = %s',
                        $orderPackage->sf_shipment_id, $exception->getMessage()
                    ),
                ]);
                $comment->save();
                return self::REDIRECT_ORDER_SF_ERROR;
            }
        } else {
            try {
                $packShipment = $orderPackage->order->customer->shopfans->packAndShipShipment($orderPackage->sf_shipment_id);
            } catch (\Exception $exception) {
                $comment = new OrderComment([
                    'user_id' => $user->id,
                    'entity_type' => OrderComment::TYPE_PACKAGE,
                    'entity_id' => $orderPackage->id,
                    'text' => sprintf('!!!ОШИБКА ШФ при packAndShipShipment sf_shipment_id = %s сообщение = %s',
                        $orderPackage->sf_shipment_id, $exception->getMessage()
                    ),
                ]);
                $comment->save();
                return self::REDIRECT_ORDER_SF_ERROR;
            }
        }

        return self::REDIRECT_ORDER;
    }

    /**
     * @param $flagBotEmailParse
     * @return bool
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     * @throws \yii\db\StaleObjectException
     */
    public function partitionPackage($flagBotEmailParse = false)
    {
        $time = microtime(true);
        $packageProducts = OrderProduct::findAll(['id' => $this->productIds]);
        $errorProduct = false;
        $newProduct = false;
        //TODO пересмотреть получение market_id
        $store = '';
        // пересоздавать декларации или нет
        $flag = false;
        /** @var OrderProduct $product */
        foreach ($packageProducts as $product) {
            if($store === ''){
                $store = Yii::$app->markets->one($product->product_market_id)->homeUrl;
            }
            if (in_array($product->status, [OrderProduct::STATUS_ERROR_NOT_REDEEMED, OrderProduct::STATUS_ERROR_NOT_AVAILABLE])) {
                $errorProduct = true;
                continue;
            }
            if (in_array($product->status, [OrderProduct::STATUS_NEW])) {
                $newProduct = true;
            }
        }

        $package = OrderPackage::findOne($this->packageId);
        if (!(Yii::$app instanceof yii\console\Application) && !Yii::$app->user->can(\app\Access::ORDER_CORRECT)) {
            if ($package->status != OrderPackage::STATUS_REDEEMED && ($errorProduct || $newProduct)) {
                return false;
            }
        }

        $updateCountProducts = [];
        $createProducts = [];
        $unlinkProducts = [];
        //Проверить что все товары принадлежат этому package ???
        //Проверить что количество товаров которые хотят разделить не равно количеству всех товаров в package
        //Определить какие товары создать, обновить количества, просто перенести


        if ($this->quantityProducts == [] && $package->getTotalQuantityPackageProduct(true) <= count($this->productIds)) {
            throw new NotFoundHttpException('Нельзя разделить package');
        }

        $totalQuantity = 0;
        // если товар в статусе ошибка переносим целиком
        /** @var OrderProduct $orderProduct */
        foreach ($packageProducts as $orderProduct) {
            if ($errorProduct) {
                $totalQuantity += $orderProduct->quantity;
                $unlinkProducts[$orderProduct->id] = $orderProduct;
            } else {
                if (isset($this->quantityProducts[$orderProduct->id])) {
                    $totalQuantity += $this->quantityProducts[$orderProduct->id];
                    // пересчет суммы за промокод
                    $promocode_full_price_customer = null;
                    $promocode_discount_customer = null;
                    if ($orderProduct->promocode_id) {
                        if ($orderProduct->quantity < 1) {
                            throw new NotFoundHttpException('Нельзя разделить package. Количество товара меньше 1! ' . $orderProduct->id);
                        }
                        $promocode_full_price_customer = $orderProduct->promocode_full_price_customer / $orderProduct->quantity;
                        $promocode_discount_customer = $orderProduct->promocode_discount_customer / $orderProduct->quantity;
                    }

                    $updateCountProducts[$orderProduct->id] = ['quantity' => ($orderProduct->quantity - $this->quantityProducts[$orderProduct->id]),
                        'product' => $orderProduct,
                        'promocode_full_price_customer' => $promocode_full_price_customer,
                        'promocode_discount_customer' => $promocode_discount_customer];
                    $createProducts[$orderProduct->id] = ['quantity' => $this->quantityProducts[$orderProduct->id],
                        'attributes' => $orderProduct->attributes,
                        'promocode_full_price_customer' => $promocode_full_price_customer,
                        'promocode_discount_customer' => $promocode_discount_customer];
                } else {
                    $totalQuantity += $orderProduct->quantity;
                    $unlinkProducts[$orderProduct->id] = $orderProduct;
                }
            }
        }

        if ($package->getTotalQuantityPackageProduct() <= $totalQuantity) {
            throw new NotFoundHttpException('Нельзя разделить package');
        }

        TelegramQueueJob::push('Start', 'Start', Events::getGreenLogsChatId());
        //Создать новый sf_package
        $order = Order::findOne(['id' => $package->order_id]);
        /** @var ShopfansApi $shopfans */
        $shopfans = $order->customer->shopfans;
        $shopfansService = Yii::$app->shopfans;
        $externalOrderId = $package->external_order_id;

        //10866
//        if (!$errorProduct) {

        if ($newProduct) {
            $externalOrderId = null;
        }

        $sfPackage = $shopfans->createPackage(
            $store,
            $externalOrderId,
            null,
            null,
            1.000,
            null
        );

        $sfPackageId = $sfPackage['id'];

        //параметр название посылки не используется, если будут возвращать учитывать
        // что эта функция используется из под консоли(email-parse через очереди),
        // при запросе текста ищется user, чтобы запросить язык, это генерирует ошибки
        // \app\web\User::t('app', 'Parcel name'),
        // deliveryMethodid: 6 - упрощенный способ доставки для клиентов usmall
        // значение из shopfans
        $sfShipment = $shopfans->createShipment(
            false,
            'Parcel name',
            true,
            $package->sf_address_id,
            null,
            6,
            [$sfPackageId]
        );
        $sfShipmentId = $sfShipment['id'];
        if (isset($sfShipment['tracking_number']) && $sfShipment['tracking_number'] !== '') {
            $sfShipmentTrackingNumber = $sfShipment['tracking_number'];
        } else {
            $sfShipmentTrackingNumber = null;
        }
//        } else {
//            $sfPackageId = null;
//            $sfShipmentId = null;
//            $sfShipmentTrackingNumber = null;
//        }

        //Обернуть в транзакцию
        $transaction = \Yii::$app->db->beginTransaction();
        //Создать новый package
        $newPackage = new OrderPackage();
        $newPackage->attributes = $package->attributes;
        $newPackage->isNewRecord = true;
        $newPackage->id = null;
        $newPackage->tracking_number = null;
        $newPackage->external_order_id = $externalOrderId;
        $newPackage->sf_package_id = $sfPackageId;
        try {
            $text = "Добавлен sf_package_id={$sfPackageId}";
            $newPackage->addComment($text);
            $text .= " (OrderPackageEditor.php:384 partitionPackage)";
            TelegramQueueJob::push($text, 'Change sf_package_id', Events::getSfPackageIdChangeChatId());
            Yii::info($text, 'requests-shopfans');
        } catch (\Exception $e) {
        }
        $newPackage->sf_shipment_id = $sfShipmentId;
        $newPackage->sf_tracking_number = $sfShipmentTrackingNumber;

        if ($errorProduct && $newProduct) {
            $newPackage->status = OrderPackage::STATUS_NEW;
        } elseif ($newProduct) {
            $newPackage->status = OrderPackage::STATUS_NEW;
        } elseif ($errorProduct) {
            $newPackage->status = OrderPackage::STATUS_ERROR_NOT_AVAILABLE;
        }

        $newPackage->save();

        foreach ($unlinkProducts as $product) {
            $package->unlink('packageProducts', $product, true);
            $newPackage->link('packageProducts', $product);
        }
        foreach ($createProducts as $product){
            $orderProduct = new OrderProduct();
            $orderProduct->attributes = $product['attributes'];
            $orderProduct->isNewRecord = true;
            $orderProduct->id = null;
            $orderProduct->quantity = $product['quantity'];
            $orderProduct->promocode_full_price_customer = $product['quantity'] * $product['promocode_full_price_customer'];
            $orderProduct->promocode_discount_customer = $product['quantity'] * $product['promocode_discount_customer'];
            $orderProduct->updateTotalPrice();
            $orderProduct->save();

            $newPackage->link('packageProducts', $orderProduct);
        }
        foreach ($updateCountProducts as $product){
            $orderProduct = $product['product'];
            $orderProduct->quantity = $product['quantity'];
            $orderProduct->promocode_full_price_customer = $product['quantity'] * $product['promocode_full_price_customer'];
            $orderProduct->promocode_discount_customer = $product['quantity'] * $product['promocode_discount_customer'];
            $orderProduct->updateTotalPrice();
            $orderProduct->save();
        }

        if ($package->getPackageProducts()->count() == 0) {
            $transaction->rollBack();

            $message = sprintf('Пустой package(%s), заказ(%s), id товаров для деления: (%s);  flagBotEmailParse (%s), time: %s',
                $package->id, $package->order_id, implode(', ', $this->productIds), $flagBotEmailParse, (microtime(true) - $time));
            TelegramQueueJob::push($message, 'EMPTY PACKAGE', Events::getGreenLogsChatId());

            TelegramQueueJob::push('Ending', 'Ending', Events::getGreenLogsChatId());
        } else {
            $transaction->commit();

            $message = sprintf('orderId = %s Изначальный usmall package: packageId = %s, sfPackageId = %s, sfShipmentId = %s. Новый usmall package: packageId = %s, sfPackageId = %s, sfShipmentId = %s',
                $package->order_id, $package->id, $package->sf_package_id, $package->sf_shipment_id, $newPackage->id, $newPackage->sf_package_id, $newPackage->sf_shipment_id);
            TelegramQueueJob::push($message, 'Info', Events::getGreenLogsChatId());

            try {
                //Декларации, обновляем в старом и новом пекадже
                $shopfansService->replaceAllDeclarationForPackage($order->customer, $newPackage);
                $shopfansService->replaceAllDeclarationForPackage($order->customer, $package);
            } catch (UserApiException $e) {
                $package->addError($e);
            } finally {
                $this->updatePackage($order, $newPackage, $package, $flagBotEmailParse);
            }

            TelegramQueueJob::push('Ending', 'Ending', Events::getGreenLogsChatId());

        }

        $this->newPackageId = $newPackage->id;

        if ($package->getPackageProducts()->count() == 0) {
            $message = sprintf('Пустой package(%s), заказ(%s), id товаров для деления: (%s);  flagBotEmailParse (%s), time: %s',
                $package->id, $package->order_id, implode(', ', $this->productIds), $flagBotEmailParse, (microtime(true) - $time));
            TelegramQueueJob::push($message, 'EMPTY PACKAGE', Events::getGreenLogsChatId());
        } else {
            $message = sprintf('Заказ(%s),  flagBotEmailParse (%s), time: %s', $package->order_id, $flagBotEmailParse, (microtime(true) - $time));
            TelegramQueueJob::push($message, 'TIME', Events::getGreenLogsChatId());
        }

        return true;
    }

    /**
     * @param $order
     * @param $newPackage
     * @param $package
     * @param $flagBotEmailParse
     * @return void
     */
    private function updatePackage($order, $newPackage, $package, $flagBotEmailParse)
    {
        //Пересчитать весь order и все packages
        $newPackage->updateStatusByProductStatus();
        $package->updateStatusByProductStatus();
        $order->updateStatusByPackageStatus();
        $order->updateAllTotalPrice();

        //Добавить комментарии про разделение товаров?

        if ($flagBotEmailParse === false) {
            $user = User::findOne(Yii::$app->user->id);
        } else {
            $user = User::findOne(User::BOT_ACCOUNT);
        }

        $comment = new OrderComment([
            'user_id' => $user->id,
            'entity_type' => OrderComment::TYPE_PACKAGE,
            'entity_id' => $newPackage->id,
            'text' => sprintf('Пользователь %s(%s) создал этот package(%s) делением package(%s)',
                $user->username, $user->id, $newPackage->id, $package->id
            ),
        ]);
        $comment->save();

        $comment = new OrderComment([
            'user_id' => $user->id,
            'entity_type' => OrderComment::TYPE_PACKAGE,
            'entity_id' => $package->id,
            'text' => sprintf('Пользователь %s(%s) разделил этот package(%s), новый package(%s)',
                $user->username, $user->id, $package->id, $newPackage->id
            ),
        ]);
        $comment->save();
    }
}
