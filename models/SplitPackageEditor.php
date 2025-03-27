<?php

namespace app\models;


use app\exception\SplitException;
use app\records\OrderComment;
use app\records\OrderPackage;
use app\records\SplitPackage;
use app\records\User;
use app\telegram\Events;
use app\telegram\TelegramQueueJob;
use app\yii\helpers\Mutex;
use Shopfans\Api\UserApi as ShopfansApi;
use yii\base\Model;
use \Yii;

class SplitPackageEditor extends Model
{
    const TYPE_CHECK_STATUS_THIS_PACKAGE = 'this_package';
    const TYPE_CHECK_STATUS_ADD_PACKAGE = 'add_package';
    const TYPE_CHECK_STATUS_SF_PACKAGE = 'sf_package';

    public $tracking_number;
    //id package которому пытаются указать существующий трек
    public $package_id;

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
            ['tracking_number', 'trim'],
            ['tracking_number', 'required'],
            ['tracking_number', 'string'],
            ['package_id', 'required'],
            ['package_id', 'integer'],
            [['package_id'], 'exist', 'targetClass' => OrderPackage::class, 'targetAttribute' => ['package_id' => 'id']],
            ['package_id', 'validatePackageUsmall'],

        ];
    }

    public function validatePackageUsmall($attr)
    {
        //Проверить что у этого package нет tracking_number - тут стоит подумать над разрешением менять номер если он указан
        $package = OrderPackage::find()->byId($this->$attr)->one();
        if (!empty($package) && !empty($package->tracking_number)) {
            $this->addError($attr, 'У этого package есть tracking_number');
            return;
        }
        //Проверить что товары package из одного магазина - не может быть трекномер на разные магазины
        $packageByTracking = OrderPackage::find()->byTrackingNumber($this->tracking_number)->notId($this->$attr)->one()
            ?: SplitPackage::find()->byTrackingNumber($this->tracking_number)->one();
        if (empty($packageByTracking)) {
            $errorMesage = sprintf('Попытка добавить несуществующий трек %s', $this->tracking_number);
            $this->addError($attr, $errorMesage);
            return;
        }

        //Проверить что этого package нет уже в SplitPackage
        //Проверить у SplitPackage есть данные по split
        $splitPackage = SplitPackage::find()->byTrackingNumber($this->tracking_number)->one();
        if (!empty($splitPackage)) {
            $split = explode(',', $splitPackage->split);
            if (is_array($split) && count($split) > 0) {
                if (in_array($package->sf_package_id, $split)) {
                    $this->addError($attr, "Этот $package->id package $package->sf_package_id уже в SplitPackage = $splitPackage->id");
                    return;
                }
            } else {
                $this->addError($attr, 'У этого SplitPackage нет данных по split');
                return;
            }
        }

        if ($packageByTracking instanceof OrderPackage) {
            $marketIdByTracking = $packageByTracking->getPackageProducts()->one()->product_market_id;
            $marketIdByPackage = $package->getPackageProducts()->one()->product_market_id;
            if ($marketIdByPackage != $marketIdByTracking) {
                $marketNameP = Yii::$app->markets->one($marketIdByPackage)->name;
                $marketNameT = Yii::$app->markets->one($marketIdByTracking)->name;
                $errorMesage = sprintf('Попытка добавить трек package принадлежащих разным магазинам %s и %s', $marketNameP, $marketNameT);
                $this->addError($attr, $errorMesage);
                return;
            }
        } else if ($packageByTracking instanceof SplitPackage) {
            $marketIdByTracking = OrderPackage::find()->bySfPackageId($split[0])->one()->getPackageProducts()->one()->product_market_id;
            $marketIdByPackage = $package->getPackageProducts()->one()->product_market_id;
            if ($marketIdByPackage != $marketIdByTracking) {
                $marketNameP = Yii::$app->markets->one($marketIdByPackage)->name;
                $marketNameT = Yii::$app->markets->one($marketIdByTracking)->name;
                $errorMesage = sprintf('Попытка добавить трек package принадлежащих разным магазинам %s и %s', $marketNameP, $marketNameT);
                $this->addError($attr, $errorMesage);
                return;
            }
        }


    }

    /**
     * @return mixed
     * @throws \Throwable
     */
    public function execute()
    {
//        //Если это второй package которому должны присвоить трекинг
//        //то есть у нас нет информации о split
//        return Mutex::sync(__CLASS__ . $this->tracking_number, 600, function () {

        if (Yii::$app instanceof yii\console\Application) {
            $user = User::findOne(User::BOT_ACCOUNT);
        } else {
            $user = User::findOne(Yii::$app->user->id);
        }

            $splitPackage = SplitPackage::find()->byTrackingNumber($this->tracking_number)->one();

            if (empty($splitPackage)) {
                //Проверяем статус в котором может быть package когда пытаются такой же трек использовать другому
                $this->checkStatusPackage(self::TYPE_CHECK_STATUS_THIS_PACKAGE);
                //Проверяем статус в котором может быть package для добавления ему трекномера
                $this->checkStatusPackage(self::TYPE_CHECK_STATUS_ADD_PACKAGE);

                $addPackage = OrderPackage::find()->byId($this->package_id)->one();

                //empty($splitPackage) => эта ситуация когда такой трек должен быть единственный у нас
                $packageWithTn = OrderPackage::find()->byTrackingNumber($this->tracking_number)->one();
                $shopfansApi = $packageWithTn->order->customer->shopfans;

                // запрашиваем разборку ШФ заказа с треком
                try {
                    $response = $shopfansApi->disassemblePackage(
                        $packageWithTn->sf_package_id,
                        // на себя и ШФ заказ, которму пытались присвоить тот же трэк
                        [$addPackage->sf_package_id]);
                    if (isset($response['errors'])) {
                        throw new SplitException('Shopfans не даёт разделить заказ из-за ошибки: '
                            . $response['errors'][0]['message']);
                    }
                } catch (\Exception $ex){
                    $text = $ex->getMessage();
                    $sf_package_id = $splitPackage->sf_package_id ?? 'null';
                    $text .= "\n sf_package_id: {$sf_package_id}";
                    $text .= "\n (SplitPackageEditor.php:154 execute)";
                    TelegramQueueJob::push($text, 'Error in sf query', Events::getSfPackageIdChangeChatId());

                    throw $ex;
                }

                // сохраняем информацию о разобранном ШФ заказе и целевых ШФ заказах
                $splitPackage = new SplitPackage();
                $splitPackage->sf_package_id = $packageWithTn->sf_package_id;
                $splitPackage->tracking_number = $this->tracking_number;
                $splitPackage->customer_id = $packageWithTn->order->customer->id;
                $splitPackage->split = implode(',', $response['dpt_ids']);
                if (!$splitPackage->save()) {
                    // сохраняем в логи, не прерываем процесс, потому что в шопфансе уже всё сделано
                    $message = sprintf(
                        'При разделении ШФ заказа #%s не удалось сохранить информацию из-за ошибки: %s',
                        $splitPackage->sf_package_id, print_r($splitPackage->getErrors(), true));
                    Yii::error($message);
                    TelegramQueueJob::push($message, 'splitPackage empty', Events::getSplitDisChatId());
                }
                try {
                    $sf_package_id = $packageWithTn->sf_package_id ?? 'null';
                    $text = "Добавлен sf_package_id={$sf_package_id}";
                    if(!empty($oldSfPackageId)){
                        $text .= " (заменён с {$oldSfPackageId})";
                    }
                    $text .= " (SplitPackageEditor.php:149 execute)";
                    TelegramQueueJob::push($text, 'Change sf_package_id', Events::getSfPackageIdChangeChatId());
                    Yii::info($text, 'requests-shopfans');
                } catch (\Exception $e) {
                }

                // если была создана копия разделяемого заказа
                if (isset($response['new_id'])) {
                    // загружаем его данные, чтобы обновить трек в заказе
                    try {
                        $packageCopy = $shopfansApi->getPackageById($response['new_id']);
                    } catch (\Exception $exception) {
                        $packageCopy = null;
                        $message = sprintf(
                            'Ошибка запроса к ШФ getPackageById = %s : %s',
                            $response['new_id'], $exception->getMessage());
                        Yii::error($message, 'split-package');
                        TelegramQueueJob::push($message, 'getPackageCopy splitPackage empty', Events::getSplitDisChatId());
                    }
                    if ($packageCopy && isset($packageCopy['tracking_number'])) {
                        $packageWithTn->tracking_number = $packageCopy['tracking_number'];
                    }
                    // меняем идентификатор разделяемого ШФ заказа на его копию
                    $packageWithTn->sf_package_id = $response['new_id'];
                    try {
                        $sf_package_id = $response['new_id'] ?? 'null';
                        $text = "Добавлен sf_package_id={$sf_package_id}";
                        if(!empty($oldSfPackageId)){
                            $text .= " (заменён с {$oldSfPackageId})";
                        }
                        $packageWithTn->addComment($text);
                        $text .= " (SplitPackageEditor.php:184 execute)";
                        TelegramQueueJob::push($text, 'Change sf_package_id', Events::getSfPackageIdChangeChatId());
                        Yii::info($text, 'requests-shopfans');
                    } catch (\Exception $e) {
                    }

                    //Так как package был откреплен на стороне ШФ и создан новый, нужно обновить sf_tracking_number
                    try {
                        $sfShipment = $packageWithTn->order->customer->shopfans->getShipmentById($packageWithTn->sf_shipment_id);
                    } catch (\Exception $exception) {
                        $sfShipment = null;
                        $message = sprintf(
                            'Ошибка запроса к ШФ getShipmentById = %s : %s',
                            $packageWithTn->sf_shipment_id, $exception->getMessage());
                        Yii::error($message, 'split-package');
                        TelegramQueueJob::push($message, 'getShipment splitPackage empty', Events::getSplitDisChatId());
                    }

                    if ($sfShipment && isset($sfShipment) && isset($sfShipment['tracking_number'])) {
                        $packageWithTn->sf_tracking_number = $sfShipment['tracking_number'];
                    }
                    $packageWithTn->status = OrderPackage::STATUS_REDEEMED_TRACK_NUMBER;
                    if (!$packageWithTn->save()) {
                        // сохраняем в логи, не прерываем процесс, потому что в шопфансе уже всё сделано
                        $message = sprintf(
                            'При разделении ШФ заказа #%s не удалось обновить информацию из-за ошибки: %s',
                            $splitPackage->sf_package_id, print_r($packageWithTn->getErrors(), true));
                        Yii::error($message);
                        TelegramQueueJob::push($message, 'save OPWithTn splitPackage empty', Events::getSplitDisChatId());
                    }
                    if ($sfShipment && isset($sfShipment) && isset($sfShipment['action']) && $sfShipment['action'] == 'pack') {
                        try {
                            $sfShipment = $packageWithTn->order->customer->shopfans->packAndShipShipment($packageWithTn->sf_shipment_id);
                            $message = sprintf('packAndShipShipment stopShipment sf_shipment_id = %s order_package = %s order = %s',
                                $packageWithTn->sf_shipment_id, $packageWithTn->id, $packageWithTn->order_id);
                            TelegramQueueJob::push($message, 'stopShipment packAndShipShipment', Events::getSplitDisChatId());
                        } catch (\Exception $exception) {
                            $comment = new OrderComment([
                                'user_id' => $user->id,
                                'entity_type' => OrderComment::TYPE_PACKAGE,
                                'entity_id' => $packageWithTn->id,
                                'text' => sprintf('!!!ОШИБКА ШФ при packAndShipShipment sf_shipment_id = %s сообщение = %s',
                                    $packageWithTn->sf_shipment_id, $exception->getMessage()
                                ),
                            ]);
                            $comment->save();
                        }
                    }
                    $packageWithTn->order->updateStatusByPackageStatus();
                }

                // обновляем трек в заказе, т.к. он изменится после добавления ШФ заказа в список целевых
                try {
                    $package = $addPackage->order->customer->shopfans->getPackageById($addPackage->sf_package_id);
                } catch (\Exception $exception) {
                    $package = null;
                    $message = sprintf(
                        'Ошибка запроса к ШФ getPackageById = %s : %s',
                        $addPackage->sf_package_id, $exception->getMessage());
                    Yii::error($message, 'split-package');
                    TelegramQueueJob::push($message, 'getSFPackage splitPackage empty', Events::getSplitDisChatId());
                }
                if ($package && isset($package['tracking_number'])) {
                    $addPackage->tracking_number = $package['tracking_number'];
                    $addPackage->status = OrderPackage::STATUS_REDEEMED_TRACK_NUMBER;
                    if (!$addPackage->save()) {
                        // сохраняем в логи, не прерываем процесс, потому что в шопфансе уже всё сделано
                        $message = sprintf(
                            'При разделении ШФ заказа #%s не удалось обновить информацию из-за ошибки: %s',
                            $splitPackage->sf_package_id, print_r($addPackage->getErrors(), true));
                        Yii::error($message);
                        TelegramQueueJob::push($message, 'save addPackage splitPackage empty', Events::getSplitDisChatId());
                    }
                    $addPackage->order->updateStatusByPackageStatus();
                }

                try {
                    // запускаемся пока только на асос
                    if ($addPackage->getMarketId() == 32) {
                        $firstSplitSfPackageId = $this->checkSplitPackage($splitPackage->id, $addPackage->external_order_id);
                        if ($firstSplitSfPackageId) {
                            $shopfansApi->disassembleAddRelation([$firstSplitSfPackageId, $splitPackage->sf_package_id], $addPackage->external_order_id);
                            TelegramQueueJob::push($firstSplitSfPackageId . ' ' . $splitPackage->sf_package_id, 'add Relation disassemble', Events::getSplitDisChatId());
                        }
                    }
                } catch (\Exception $e) {
                    $message = sprintf(
                        'При создании связи c disassemble %s произошла ошибка: %s',
                        $splitPackage->sf_package_id, $e->getMessage());
                    Yii::error($e->getMessage());
                    TelegramQueueJob::push($message, 'ERROR add Relation disassemble', Events::getSplitDisChatId());
                }
            } else {
                $storagePackageIds = explode(',', $splitPackage->split);
                if (count($storagePackageIds) < 1 || !is_array($storagePackageIds)) {
                    throw new SplitException("Ошибка splitPackage = $splitPackage->id нет split = $splitPackage->split");
                }

                //Проверяем что текущий package не прописан уже в split
                $addPackage = OrderPackage::find()->byId($this->package_id)->one();
                if (empty($addPackage)) {
                    throw new SplitException("Ошибка не найден package $this->package_id");
                }
                if (in_array($addPackage->sf_package_id, $storagePackageIds)) {
                    throw new SplitException("Ошибка текущий package $this->package_id уже в split $splitPackage->split");
                }

                //Основной package был создан под пользователем которому первому прописали трекномер и открепили package и сделали его split
                $shopfansApi = $splitPackage->customer->shopfans;

                // обновляем запрос на разборку ШФ заказа идентификатором ещё одного ШФ заказа
                $storagePackageIds[] = $addPackage->sf_package_id;
                try {
                    $response = $shopfansApi
                        ->disassemblePackage($splitPackage->sf_package_id, $storagePackageIds);
                    if (isset($response['errors'])) {
                        throw new SplitException('Shopfans не даёт разделить заказ из-за ошибки: '
                            . $response['errors'][0]['message']);
                    }
                } catch (\Exception $ex){
                    $text = $ex->getMessage();
                    $sf_package_id = $splitPackage->sf_package_id ?? 'null';
                    $text .= "\n sf_package_id: {$sf_package_id}";
                    $text .= "\n (SplitPackageEditor.php:327 execute)";
                    TelegramQueueJob::push($text, 'Error in sf query', Events::getSfPackageIdChangeChatId());

                    throw $ex;
                }

                // обновляем информацию о целевых ШФ заказах
                $splitPackage->split = implode(',', $response['dpt_ids']);
                if (!$splitPackage->save()) {
                    // сохраняем в логи, не прерываем процесс, потому что в шопфансе уже всё сделано
                    $message = sprintf(
                        'При разделении ШФ заказа #%s не удалось сохранить информацию из-за ошибки: %s',
                        $splitPackage->sf_package_id, print_r($splitPackage->getErrors(), true));
                    Yii::error($message);
                    TelegramQueueJob::push($message, 'save splitPackage', Events::getSplitDisChatId());
                }

                // обновляем трек в заказе, т.к. он изменится после добавления ШФ заказа в список целевых
                try {
                    $package = $addPackage->order->customer->shopfans->getPackageById($addPackage->sf_package_id);
                } catch (\Exception $exception) {
                    $package = null;
                    $message = sprintf(
                        'Ошибка запроса к ШФ getPackageById = %s : %s',
                        $addPackage->sf_package_id, $exception->getMessage());
                    Yii::error($message, 'split-package');
                    TelegramQueueJob::push($message, 'getSFPackage splitPackage', Events::getSplitDisChatId());
                }
                if ($package && isset($package['tracking_number'])) {
                    $addPackage->tracking_number = $package['tracking_number'];
                    $addPackage->status = OrderPackage::STATUS_REDEEMED_TRACK_NUMBER;
                    if (!$addPackage->save()) {
                        // сохраняем в логи, не прерываем процесс, потому что в шопфансе уже всё сделано
                        $message = sprintf(
                            'При разделении ШФ заказа #%s не удалось обновить информацию из-за ошибки: %s',
                            $splitPackage->sf_package_id, print_r($addPackage->getErrors(), true));
                        Yii::error($message);
                        TelegramQueueJob::push($message, 'save addPackage splitPackage', Events::getSplitDisChatId());
                    }
                    $addPackage->order->updateStatusByPackageStatus();
                }
            }

            $comment = new OrderComment([
                'user_id' => $user->id,
                'entity_type' => OrderComment::TYPE_PACKAGE,
                'entity_id' => $addPackage->id,
                'text' => sprintf('Пользователь %s(%s) добавил трек %s sf_package_id = %s splitPackageId = %s',
                    $user->username, $user->id, $addPackage->tracking_number, $splitPackage->sf_package_id, $splitPackage->id
                ),
            ]);
            $comment->save();

            return $splitPackage->id;
//        });
    }

    private function checkStatusPackage($typeCheckStatus)
    {
        //TODO проверять статус в котором может быть package когда пытаются такой же трек использовать другому
        //TODO проверять статус в котором может быть package для добавления ему трекномера
        //TODO проверять статус в ШФ
        return true;
    }

    /**
     * @param $splitPackageId
     * @param $externalOrderId
     * @return false|integer
     * @throws \yii\db\Exception
     */
    public function checkSplitPackage($splitPackageId, $externalOrderId)
    {
        $firstSplitPackage = Yii::$app->db->createCommand("
                select distinct sp.sf_package_id
                from order_package
                left join split_package sp ON sp.split LIKE CONCAT('%', order_package.sf_package_id, '%')
                where external_order_id = :external_number and sp.id != :split_id order by sp.id",
            ['external_number' => $externalOrderId, 'split_id' => $splitPackageId])
        ->queryOne();

        if(isset($firstSplitPackage['sf_package_id'])){
            return $firstSplitPackage['sf_package_id'];
        }

        return $firstSplitPackage;
    }
}
