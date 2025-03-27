<?php

namespace app\markets;


use app\records\EmailParse;
use app\records\EmailParseCost;
use app\records\EmailParseCostCard;
use app\records\EmailParseCostData;
use app\records\MassOrderDiscount;
use app\records\OrderPackage;
use app\records\OrderProduct;
use app\records\Setting;
use app\records\SplitPackage;
use app\records\User;
use app\telegram\Events;
use app\telegram\TelegramQueueJob;
use app\web\editors\OrderPackageEditor;
use app\web\editors\SplitPackageEditor;
use Shopfans\Api\UserApi;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;

/**
 * Class ParseEmail
 *
 * @package app\markets
 */
abstract class ParseEmail extends BaseObject
{
    /** @var string */
    public $from;

    /** @var string */
    public $to;

    /** @var string */
    public $subject;

    /** @var string */
    public $date;

    /** @var string */
    public $body;

    /** @var string */
    public $raw;

    /** @var boolean */
    public $web = false;

    /** @var integer */
    private $email_parse_id;

    protected $multi_orders = [];

    /**
     * id Магазина в нашей системе
     *
     * @return int
     */
    abstract public function getMarketId();

    /**
     * Метод возвращает принадлежность текущему магазину
     * в нем нужно понять от какого магазина пришло письмо
     *
     * @return bool
     */
    abstract public function checkCurrentStore(): bool;

    public function execute()
    {
        if (!$this->web) {
            //Бред какой-то дубляжи отработаем
            $check = EmailParse::find()->byHash(sha1($this->raw))->one();
            if (!empty($check)) {
                TelegramQueueJob::push('email_parse_id = ' . $check->id, '!!!ALARM Дубль письма 1');
                return true;
            }
        }
        try {
            $dataOrderTrack = $this->getDataOrderTrack();
        } catch (\Exception $exception) {
            TelegramQueueJob::push($exception->getMessage(), '!!!ALARM getDataOrderTrack');
            TelegramQueueJob::push($exception->getTraceAsString(), '!!!ALARM getDataOrderTrack');
            return true;
        }

        TelegramQueueJob::push($dataOrderTrack, 'getDataOrderTrack');
//        if (count($dataOrderTrack) > 0) {
//            TelegramQueueJob::push($dataOrderTrack, 'Вытащили из письма', TelegramQueueJob::getEmailParseChatId());
//        }

        if ($this->web) {
            $dataOrderTrack = $this->filterResultData($dataOrderTrack);
            TelegramQueueJob::push('filterResultData: ' . count($dataOrderTrack), 'WEB filterResultData', Events::getEmailParseWebChatId());
        }

        $resultData = $dataOrderTrack;
        $productVariantExecute = $this->getProductVariantExecute($resultData);

        $marketsName = ArrayHelper::map(Yii::$app->markets->all(), 'id', 'name');
        $marketName = $marketsName[$this->getMarketId()];
        //10819 должна отработать если не нашли ни одного товара в базе
        //нужно добавить проверку что товары уже могут быть с трек-номером
        if (!$productVariantExecute || count($productVariantExecute) < 1) {
            if (!empty($resultData) && !$this->web) {
                $this->notificationOfProductInSplitAndTrackNumber($resultData, $marketName);
            } elseif (!empty($resultData) && $this->web) {
                TelegramQueueJob::push('ничего не нашли, возможно еще целиком в процессе', 'Проверить', Events::getEmailParseWebChatId());
            }
            return true;
        }

        if (!empty($resultData)) {
            $this->noticeProductSearch($resultData);
        }

        $validateData = $this->validateProductVariantExecute($productVariantExecute);
        if ($this->web) {
            TelegramQueueJob::push($validateData, 'validateData', Events::getEmailParseWebChatId());
        }
        $this->setEmailParse($dataOrderTrack, $validateData);
        $web = '';
        if ($this->web) {
            $web = ' web';
        }
        TelegramQueueJob::push('https://app.usmall.ru/email-parse/' . $this->email_parse_id, $marketName . ' ' . $this->email_parse_id . $web, TelegramQueueJob::getEmailParseChatId());
        TelegramQueueJob::push('https://app.usmall.ru/email-parse/' . $this->email_parse_id, $marketName . ' ' . $this->email_parse_id . $web, Events::getEmailParseErrorChatId());
        TelegramQueueJob::push('https://app.usmall.ru/email-parse/' . $this->email_parse_id, $marketName . ' ' . $this->email_parse_id . $web, Events::getEmailParseWebChatId());
        if ($validateData == false) {
            //TODO сообщить в телеграм об ошибке
            TelegramQueueJob::push('validateData пусто', 'validateData', Events::getEmailParseWebChatId());
        }

        if ($this->web) {
            $orderPackagesForDivision = $this->orderPackagesForDivisionExecute($validateData);
            TelegramQueueJob::push(count($orderPackagesForDivision), 'count orderPackagesForDivision', Events::getEmailParseWebChatId());
            foreach ($orderPackagesForDivision as $key => $data) {
                if (!empty($data)) {
                    $this->search(strval($key), $data);
                    sleep(2);
                }
            }
            TelegramQueueJob::push('Обработка email завершена', 'Результат для ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
            TelegramQueueJob::push('Обработка email завершена', 'Результат для ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
            TelegramQueueJob::push('Обработка email завершена', 'Результат для ' . $this->email_parse_id, Events::getEmailParseWebChatId());
        } else {
//            $hash = sha1($this->raw);
//            Mutex::sync(__CLASS__ . $hash, 600, function () use ($validateData) {
//                //Бред какой-то дубляжи отработаем
//                $check = EmailParse::find()->byHash(sha1($this->raw))->andWhere(['<>', 'id', $this->email_parse_id])->one();
//                if (!empty($check)) {
//                    TelegramQueueJob::push('email_parse_id = ' . $check->id, '!!!ALARM Дубль письма 2');
//                    return true;
//                }

                $orderPackagesForDivision = $this->orderPackagesForDivisionExecute($validateData);
                foreach ($orderPackagesForDivision as $key => $data) {
                    $this->search(strval($key), $data);
                }
                TelegramQueueJob::push('Обработка email завершена', 'Результат для ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                TelegramQueueJob::push('Обработка email завершена', 'Результат для ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
//            });
        }
        return true;
    }

    /**
     * Обработка письма для бухгалтерии.
     */
    public function executeAccounting()
    {
        if($this->checkConfirmEmail()){
            $message = $this->getEmailMessage();
            $this->confirmEmailParseCost($message);
            return true;
        }

        if($this->checkShippingEmail()){
            $message = $this->getEmailMessage();
            $this->shippingEmailParseCost($message);
            return true;
        }

        if($this->checkCancelEmail()){
            $message = $this->getEmailMessage();
            $this->cancelEmailParseCost($message);
            return true;
        }

        if($this->checkRefundEmail()){
            $message = $this->getEmailMessage();
            $this->refundEmailParseCost($message);
            return true;
        }

        //добавить логирование если дошло до сюда ???
        return true;
    }

    private function search($trackingNumber, $orderPackages)
    {
        /* @var OrderPackage $orderPackage */
        foreach ($orderPackages as $key => $orderPackage) {
            $message = sprintf('order: %s  package: %s', $orderPackage->order_id, $orderPackage->id);
            TelegramQueueJob::push($message, 'search ' . $this->email_parse_id);

            //ТУТ РЕАЛЬНЫЕ ДЕЙСТВИЯ
            $message = sprintf('Нашлось для https://app.usmall.ru/order/view/%s?type=only package = %s', $orderPackage->order_id, $orderPackage->id);
            TelegramQueueJob::push($message, 'Результат для ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());

            $orderPackageEditor = new OrderPackageEditor();
            $orderPackageEditor->setScenario(OrderPackageEditor::SCENARIO_CHANGE_TRACK_NUMBER_BOT);
            $emailParse = EmailParse::findOne($this->email_parse_id);

            $stopShip = 0;
//            if(strpos($trackingNumber, $this->multi_orders) !== false){
//                $stopShip = 1;
//            }

            $loadData = [
                'order_id' => $orderPackage->order_id,
                'package_id' => $orderPackage->id,
                'tracking_number' => $trackingNumber,
                'type_changed' => 1,
                'stop_ship' => $stopShip,
            ];

            if ($orderPackageEditor->load($loadData) && $orderPackageEditor->validate()) {
                try {
                    $user = User::findOne(User::BOT_ACCOUNT);
                    if (!(Yii::$app instanceof yii\console\Application)) {
                        Yii::$app->user->login($user);
                    }
                    $message = sprintf('package_id=%s count products=%s status=%s ', $orderPackageEditor->package_id, count($orderPackage->packageProducts), $orderPackage->status);
                    TelegramQueueJob::push($message, 'before ParseEmail changeTrackNumber');
                    $result = $orderPackageEditor->changeTrackNumber();
                    $message = sprintf('result=%s package_id=%s tracking_number=%s ', $result, $orderPackageEditor->package_id, $orderPackageEditor->tracking_number);
                    TelegramQueueJob::push($message, 'ParseEmail changeTrackNumber');
                    if ($result == OrderPackageEditor::REDIRECT_SPLIT) {
//                                    $message = sprintf('https://app.usmall.ru/split-package/add-package?package_id=%s&tracking_number=%s', $orderPackageEditor->package_id, $orderPackageEditor->tracking_number);
//                                    $message = '... страшно, а что поделать ...';
//                                    TelegramQueueJob::push($message, 'Ща буду SPLIT делать ' . $trackingNumber, TelegramQueueJob::getEmailParseChatId());
                        //Авторизуемся под пользователем бота чтобы при установки сплита был указан в комментариях пользователь
                        $splitPackageEditor = new SplitPackageEditor([
                            'tracking_number' => $trackingNumber,
                            'package_id' => $orderPackageEditor->package_id,
                        ]);
                        if ($splitPackageEditor->validate()) {
                            try {
                                $splitPackageId = $splitPackageEditor->execute();
                                $message = sprintf('https://app.usmall.ru/split-package/%s', $splitPackageId);
                                TelegramQueueJob::push($message, 'Выполнен SPLIT ' . $trackingNumber . ' ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());

                            } catch (\Exception $exception) {
                                $message = sprintf('https://app.usmall.ru/split-package/add-package?package_id=%s&tracking_number=%s', $orderPackageEditor->package_id, $orderPackageEditor->tracking_number);
                                TelegramQueueJob::push($message, '!!!АЛАРМАА SPLIT ' . $trackingNumber . ' ' . $orderPackageEditor->package_id . ' ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                                TelegramQueueJob::push($message, '!!!АЛАРМАА SPLIT ' . $trackingNumber . ' ' . $orderPackageEditor->package_id . ' ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
                                TelegramQueueJob::push($exception->getMessage() . '____________' . $exception->getTraceAsString(), '!!!АЛАРМАА SPLIT ' . $trackingNumber . ' ' . $orderPackageEditor->package_id . ' ' . $this->email_parse_id, Events::getSplitDisChatId());
                            }
                        } else {
                            $message = sprintf("Данные входные \n tracking_number = %s \n package_id = %s", $trackingNumber, $orderPackageEditor->package_id);
                            $message .= "\n";
                            $message .= sprintf("Данные in SplitPackageEditor \n tracking_number = %s \n package_id = %s", $splitPackageEditor->tracking_number, $splitPackageEditor->package_id);
                            $message .= "\n";
                            $message .= sprintf("Данные validate SplitPackageEditor \n %s", implode("\n", $splitPackageEditor->getErrors()));

                            TelegramQueueJob::push($message, 'Валидация для SPLIT не прошла' . $trackingNumber . ' ' . $orderPackageEditor->package_id . ' ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                        }
                    }
                    if ($result == OrderPackageEditor::REDIRECT_ORDER) {
                        $message = sprintf('https://app.usmall.ru/order/%s', $orderPackageEditor->order_id);
                        TelegramQueueJob::push($message, 'Нужно проверить установку первого трека ' . $trackingNumber . ' ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                    }
                    if ($result == OrderPackageEditor::REDIRECT_ORDER_SF_ERROR) {
                        $message = sprintf('https://app.usmall.ru/order/%s', $orderPackageEditor->order_id);
                        TelegramQueueJob::push($message, 'Нужно проверить установку первого трека ' . $trackingNumber . ' ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                        TelegramQueueJob::push($message, 'Ошибка на стороне ШФ см. комментарий у package_id = ' . $orderPackage->id . ' ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                        TelegramQueueJob::push($message, 'Ошибка на стороне ШФ см. комментарий у package_id = ' . $orderPackage->id . ' ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
                    }
                } catch (\Exception $exception) {
                    TelegramQueueJob::push($exception->getMessage(), 'Ошибка указания трека = ' . $trackingNumber . ' ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                    TelegramQueueJob::push($exception->getMessage(), 'Ошибка указания трека = ' . $trackingNumber . ' ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
                    Yii::error($exception->getTraceAsString(), 'split-package');
                    TelegramQueueJob::push($exception->getTraceAsString(), 'Ошибка указания трека = ' . $trackingNumber . ' ' . $this->email_parse_id, Events::getSplitDisChatId());
                }
            } else {
                TelegramQueueJob::push(['loadData' => $loadData, 'error' => $orderPackageEditor->errors], '****************** Результат для ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
            }
            $this->updateEmailParse($orderPackage);
            unset($orderPackages[$key]);
        }

        //Если после всех обработок в массиве $orderPackages остались данные значит не смогли внести трек-номер
        if (count($orderPackages) > 0) {
            foreach ($orderPackages as $orderPackage) {
                $messageArray[] = sprintf(" %s заказе %s ", $orderPackage->id, $orderPackage->order_id);
            }
            TelegramQueueJob::push(implode("\n", $messageArray), 'Данные,которые не смогли разнести после всех обработок. Для ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
            TelegramQueueJob::push(implode("\n", $messageArray), 'Данные,которые не смогли разнести после всех обработок. Для ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
        }
    }

    /**
     * Метод ищет product_variant_id и сравнивает количество
     * должен изменить количество или удалить из данных
     *
     * @param $dataOrderByProductVariantIds
     * @param $orderProductData
     * @return bool
     */
    private function checkDataOrder(&$dataOrderByProductVariantIds, $orderProductData)
    {
        //Вначале пробежаться и понять что количества позволяют и удалить или изменить количество
        $flag = true;
        foreach ($orderProductData as $orderProduct) {
            if (isset($orderProduct['product_variant_id']) && isset($orderProduct['product_variant_id'])) {
                $productVariantId = (int)$orderProduct['product_variant_id'];
                if (isset($dataOrderByProductVariantIds[$productVariantId])) {
                    $quantityParse = $dataOrderByProductVariantIds[$productVariantId]['quantity'];
                    if ((int)$quantityParse == (int)$orderProduct['quantity']) {
                        //Если одинаковое количество то удаляем этот продукт
                    } elseif ((int)$quantityParse > (int)$orderProduct['quantity']) {
                        //Если количество больше чем в package то изменяем количество
                    } else {
                        //Если количество не совпадает ставим флаг false
                        $flag = false;
                    }
                } else {
                    //Если нет в package такого product_variant_id
                    $flag = false;
                }
            }
        }

        //и удалить или изменить количество
        if ($flag === true) {
            foreach ($orderProductData as $orderProduct) {
                if (isset($orderProduct['product_variant_id']) && isset($orderProduct['product_variant_id'])) {
                    $productVariantId = (int)$orderProduct['product_variant_id'];
                    if (isset($dataOrderByProductVariantIds[$productVariantId])) {
                        $quantityParse = $dataOrderByProductVariantIds[$productVariantId]['quantity'];
                        if ((int)$quantityParse == (int)$orderProduct['quantity']) {
                            //Если одинаковое количество то удаляем этот продукт
                            unset($dataOrderByProductVariantIds[$productVariantId]);
                        } elseif ((int)$quantityParse > (int)$orderProduct['quantity']) {
                            //Если количество больше чем в package то изменяем количество
                            $dataOrderByProductVariantIds[$productVariantId]['quantity'] = (int)$quantityParse - (int)$orderProduct['quantity'];
                        } else {
                            //TODO сообщение о том что количество в письме меньше чем в package
                            TelegramQueueJob::push($dataOrderByProductVariantIds[$productVariantId], 'Количество в письме меньше чем в package');
                        }
                    }
                }
            }
        }

        return $flag;
    }

    /**
     * Валидация данных
     *
     * @param $productVariantExecute
     * @return array|bool
     */
    private function validateProductVariantExecute($productVariantExecute)
    {
        if (!is_array($productVariantExecute) || count($productVariantExecute) < 1) {
            //TODO сообщить в телеграм об ошибке
            TelegramQueueJob::push($productVariantExecute, 'validateProductVariantExecute' . "\n" . "не массив или count < 1");
            return false;
        }
        $validateData = [];
        $multiTrack = false;
        $externalOrderId = '';
        $trackingNumber = '';
        foreach ($productVariantExecute as $orderTrack) {
            if (!isset($orderTrack['external_order_id'])
                || !isset($orderTrack['tracking_number'])
                || !isset($orderTrack['product_variant_id'])
                || $orderTrack['product_variant_id'] == null
                || !isset($orderTrack['quantity'])
                || !isset($orderTrack['full_data'])
            ) {
                //TODO сообщить в телеграм об ошибке
//                TelegramQueueJob::push($orderTrack, 'validateProductVariantExecute' . "\n" . "множественная проверка");
                continue;
            }

            if ($externalOrderId == '') {
                $externalOrderId = $orderTrack['external_order_id'];
            }

            if ($trackingNumber == '') {
                $trackingNumber = $orderTrack['tracking_number'];
            }
            if (!$multiTrack && $trackingNumber !== $orderTrack['tracking_number']) {
                $multiTrack = true;
            }

            $validateData[] = $orderTrack;
        }
        if (count($validateData) < 1) {
            //TODO сообщить в телеграм об ошибке
            TelegramQueueJob::push($productVariantExecute, 'validateProductVariantExecute' . "\n" . "после проверок нет данных");
            return false;
        }

        if ($multiTrack) {
            $data = [];
            foreach ($validateData as $item) {
                $data[$item['tracking_number']][] = $item;
            }
            $validateData = ['multi_track' => true, 'external_order_id' => $externalOrderId, 'data' => $data];
        } else {
            $validateData = ['multi_track' => false, 'external_order_id' => $externalOrderId, 'data' => $validateData];
        }

        if ($this->web) {
            TelegramQueueJob::push($validateData, 'validateData Macys', Events::getEmailParseWebChatId());
        }

        return $validateData;
    }

    /**
     * Запись в БД инфы
     *
     * @param $dataOrderTrack
     */
    private function setEmailParse($resultData, $validateData)
    {
        $hash = sha1($this->raw);
        $emailParse = new EmailParse();
        $emailParse->load(['EmailParse' => current($resultData)]);
        $emailParse->validate();

        $emailParse->hash = $hash;
        $emailParse->eml = $this->raw;
        $emailParse->result_data = Json::encode($resultData);
        $emailParse->validate_data = Json::encode($validateData);
        $emailParse->market_id = $this->getMarketId();
        $emailParse->web = $this->web;
        $emailParse->save();

        $this->email_parse_id = $emailParse->id;
    }

    /**
     * @param int $operation
     * @param string $type
     * @param string $data
     *
     * @return ?EmailParseCostData
     */
    protected function setEmailParseCostData(int $operation, string $type)
    {
        $emailParseCostData = new EmailParseCostData();
        $emailParseCostData->type = $type;
        $emailParseCostData->data = $this->raw;
        $emailParseCostData->operation = $operation;
        $emailParseCostData->market_id = $this->getMarketId();
        if (!$emailParseCostData->save()) {
            \Yii::warning(array_merge(["Can not save email parse cost data."], $emailParseCostData->getErrors()));
            return null;
        }

        return $emailParseCostData;
    }

    /**
     * Запись в БД инфы
     *
     * @param array $resultData
     * @param ?EmailParseCostData $data
     * @param ?array|Message $message
     */
    protected function setEmailParseCost(array $resultData, EmailParseCostData $data = null, $message = null)
    {
        if(!isset($resultData['operation'])){
            $resultData['operation'] = 0;
        }

        // под вопросом, пока что убираем проверку
//        if(!$this->checkExternalNumber($resultData['external_number'])){
//            return;
//        }

        $emailParseCost = new EmailParseCost();
        $emailParseCost->price = $resultData['price'];
        $emailParseCost->external_number = $resultData['external_number'];
        $emailParseCost->operation = $resultData['operation'];
        $emailParseCost->market_id = $this->getMarketId();
        if ($message instanceof Message) {
            $emailParseCost->date_origin = $message->getHeaderValue('Date', '');
            $emailParseCost->date_parsed = $emailParseCost->date_origin === '' ? 0 : (strtotime($emailParseCost->date_origin) ?: 0);
        } else {
            $emailParseCost->date_origin = '';
            $emailParseCost->date_parsed = time();
        }
        $emailParseCost->validate();

        $emailParseCost->market_id = $this->getMarketId();

        if ($emailParseCost->save()) {
            if (isset($resultData['card_number'])) {
                foreach ($resultData['card_number'] as $cardNumber) {
                    $emailParseCostCard = new EmailParseCostCard();
                    $emailParseCostCard->email_parse_cost_id = $emailParseCost->id;
                    $emailParseCostCard->external_number = $emailParseCost->external_number;
                    $emailParseCostCard->card_number = $cardNumber;
                    $emailParseCostCard->save();
                }
            }

            $marketsName = ArrayHelper::map(Yii::$app->markets->all(), 'id', 'name');
            $marketName = $marketsName[$this->getMarketId()];
            $message = sprintf("External Number: %s \nPrice: %s ", $resultData['external_number'] . '  ' . $emailParseCost->operation, $resultData['price']);
            TelegramQueueJob::push($message, $marketName, Events::getEmailParseCostChatId());

            if (isset($data)) {
                $data->updateAttributes(['email_parse_cost_id' => $emailParseCost->id, 'updated_at' => time()]);
            }
        }
    }

    /**
     * Временный метод нужно будет сделать линкование с таблицами для хранения данных по существующим сущностям
     *
     * @param $orderPackageData
     */
    private function updateEmailParse($orderPackageData)
    {
        $emailParse = EmailParse::find()->byId($this->email_parse_id)->one();
        if (!empty($emailParse)) {
            if (empty($emailParse->order_package_data)) {
                $emailParse->order_package_data = Json::encode([$orderPackageData]);
            } else {
                $oldOrderPackageData = Json::decode($emailParse->order_package_data, true);
                try {
                    $emailParse->order_package_data = Json::encode(array_merge($oldOrderPackageData, [$orderPackageData]));
                } catch (\Exception $exception) {
                    //TODO просто для того чтобы не валились ошибки
                }
            }

            $emailParse->save(false);
        }

    }

    /**
     * Метод должен вернуть массив данных для подачи на вход getDataOrderTrack
     *
     * @return array
     */
    abstract public function getDataOrderTrack(): array;

    /**
     * Поиск по данным которые спарсили из письма
     *
     * Метод должен вернуть массив данных в котором
     * будет информация по заказам и трек номерам
     *  [
     *      [
     *          'external_order_id' => '111-5866755-0160202',
     *          'tracking_number' => '1ZY26V500302292707',
     *          'product_variant_id' => null,
     *          'quantity' => string '1'
     *          'full_data' =>
     *              [
     *                  'style' => string 'MF96P14CHN',
     *                  'color' => string 'BONE',
     *                  'size' => string 'XS',
     *                  'quantity' => 1,
     *                  'price' => '$65.63',
     *                  'total' => '$65.63',
     *                  'status' => 'Shipped',
     *                  'external_order_id' => 'wa75137794',
     *                  'tracking_number' => '1Z37W9X60259598006',
     *              ]
     *      ],
     *      [
     *          'external_order_id' => '111-5866755-0160202',
     *          'tracking_number' => '1ZY26V500302292707',
     *          'product_variant_id' => 5457675,
     *          'quantity' => string '1'
     *          'full_data' =>
     *              [
     *                  'style' => string '30S0GAYB6L',
     *                  'color' => string 'NAVY',
     *                  'size' => string 'ONE SIZE',
     *                  'quantity' => 1,
     *                  'price' => '$129.00',
     *                  'total' => '$129.00',
     *                  'status' => 'Shipped',
     *                  'external_order_id' => 'wa75137794',
     *                  'tracking_number' => '1Z37W9X60259598006',
     *              ]
     *      ],
     *  ]
     * @param $resultData
     * @return array
     */
    abstract public function getProductVariantExecute(&$resultData);

    /**
     * Возвращает объект Message с содержимым письма по его сырым данным `raw`.
     *
     * @return \ZBateson\MailMimeParser\Message
     */
    public function getEmailMessage()
    {
        return (new MailMimeParser())->parse($this->raw);
    }

    /**
     * @param array $resultData
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function noticeProductSearch(array $resultData)
    {
        if ($this->web) {
            TelegramQueueJob::push('noticeProductSearch', 'Macys', Events::getEmailParseWebChatId());
            $trackingNumber = [];
            foreach ($resultData as $product) {
                $trackingNumber[] = $product['tracking_number'];
            }
            $trackingNumber = array_unique($trackingNumber);
            TelegramQueueJob::push($trackingNumber, 'noticeProductSearchMacys', Events::getEmailParseWebChatId());
            if (!empty($trackingNumber)) {
                $splitPackages = SplitPackage::find()->where(['tracking_number' => $trackingNumber])->all();
                if (count($splitPackages) !== count($trackingNumber)) {
                    foreach ($splitPackages as $splitPackage) {
                        if ($key = array_search($splitPackage->tracking_number, $trackingNumber) !== false) {
                            unset($trackingNumber[$key]);
                        }
                    }
                }
            }
            if (!empty($trackingNumber)) {
                $data = [];
                $count = 0;
                $extNumber = '';

                foreach ($trackingNumber as $number) {
                    foreach ($resultData as $product) {
                        if ($product['tracking_number'] == $number) {
                            if ($extNumber == '') {
                                $extNumber = $product['external_order_id'];
                            }
                            $data[$number][] = $product['name'];
                            $count++;
                        }
                    }
                    if (isset($data[$number])) {
                        $data[$number] = implode("\n", $data[$number]);
                    }
                }

                $marketsName = ArrayHelper::map(Yii::$app->markets->all(), 'id', 'name');
                $marketName = $marketsName[$this->getMarketId()];

                $message = sprintf("Данные из маркета по заказу %s были получены. Market: %s \n Кол-во не найденных товаров: %s \n", $extNumber, $marketName, $count);

                if (!empty($data)) {
                    $trackingMessage = '';
                    foreach ($data as $key => $item) {
                        $trackingMessage .= sprintf("\n Трек-номер: %s \n %s", $key, $item);
                    }
                    $message .= $trackingMessage;
                    TelegramQueueJob::push($message, $marketName, Events::getEmailParseWebChatId());
                }
            }
        } else {
            $name = ArrayHelper::getColumn($resultData, 'name');
            $marketsName = ArrayHelper::map(Yii::$app->markets->all(), 'id', 'name');
            $marketName = $marketsName[$this->getMarketId()];

            $message = sprintf("Данные из письма были считаны. Market: %s \n Тема письма: %s \n Дата: %s \n Кол-во не найденных товаров: %s", $marketName, $this->subject, $this->date, count($resultData));
            TelegramQueueJob::push($name, sprintf("Товары, которые не удалось найти. Market: %s ", $marketName), Events::getEmailParseErrorChatId());

            if (!empty($name)) {
                TelegramQueueJob::push($message, $marketName, Events::getEmailParseErrorChatId());
                TelegramQueueJob::push($name, sprintf("Товары, которые не удалось найти. Market: %s ", $marketName), Events::getEmailParseErrorChatId());
            }
        }
    }

    /**
     * @param array $productsEmail
     * @return void
     */
    public function notificationCheckForDivision(array $productsEmail)
    {
        $data = [];
        $trackingNumber = $productsEmail[0]['tracking_number'];
        foreach ($productsEmail as $product) {
            $data[] = $product['full_data']['name'] . ' - ' . $product['quantity'] . 'шт/ ' . $product['full_data']['quantity'] . 'шт';
        }

        $message = sprintf("Не найдено в заказах. Трек-номер: %s", $trackingNumber);

        if (!empty($data)) {
            TelegramQueueJob::push($message, 'Товары, которые не удалось найти ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
            TelegramQueueJob::push($data, $this->email_parse_id, Events::getEmailParseErrorChatId());
        }
    }

    /** Функции по обработке и отправки данных для создания деклараций в shopfans */

    /** основная функция по обработке данных и формировании запроса к shopfans */
    public function executeRequest()
    {
        try {
            $dataOrderTrack = $this->getDataOrderTrack();
        } catch (\Exception $exception) {
            return true;
        }

        if (count($dataOrderTrack) > 0) {
            TelegramQueueJob::push($dataOrderTrack, 'Вытащили из письма', Events::getEmailParseManifestChatId());

            if ($dataOrderTrack['multi_track']) {
                foreach ($dataOrderTrack['products'] as $products) {
                    $this->executeSingleQuery(['email' => $dataOrderTrack['email'], 'store_url' => $dataOrderTrack['store_url'], 'products' => $products]);
                }
            } else {
                $this->executeSingleQuery($dataOrderTrack);
            }
        }

        return true;
    }

    /**
     * Валидация и форматирование данных
     * для отправки запроса в shopfans
     *
     * @param $dataOrderTrack
     * @return array|bool
     */
    private function validateQueryParams($dataOrderTrack)
    {
        if (!is_array($dataOrderTrack) || count($dataOrderTrack) < 1) {
            return false;
        }
        $validateData = [];
        $trackingNumber = '';
        foreach ($dataOrderTrack['products'] as $orderTrack) {
            if (!isset($orderTrack['tracking_number'])
                || !isset($orderTrack['name'])
                || !isset($orderTrack['color'])
                || !isset($orderTrack['size'])
                || !isset($orderTrack['price'])
                || !isset($orderTrack['quantity'])
                || !isset($orderTrack['url'])
            ) {
                continue;
            }

            if ($trackingNumber == '') {
                $trackingNumber = $orderTrack['tracking_number'];
            }

            $item = [];
            $item['description'] = $orderTrack['name'] . sprintf(" Color: %s; Size: %s", $orderTrack['color'], $orderTrack['size']);
            $item['url'] = $orderTrack['url'];
            $item['quantity'] = $orderTrack['quantity'];
            $item['price'] = $orderTrack['price'];

            $validateData[] = $item;
        }
        if (empty($validateData)) {
            return [];
        }
        return ['email' => $dataOrderTrack['email'], 'store_url' => $dataOrderTrack['store_url'], 'tracking' => $trackingNumber, 'items' => $validateData];
    }

    private function executeSingleQuery($dataOrderTrack)
    {
        $queryParams = $this->validateQueryParams($dataOrderTrack);

        if (empty($queryParams)) {
            TelegramQueueJob::push('Нет данных для отправки в shopfans', 'Уведомление', Events::getEmailParseManifestChatId());
        } else {
            TelegramQueueJob::push($queryParams, 'Параметры запроса', Events::getEmailParseManifestChatId());
            try {
                $shopfans = \Yii::$container->get(\Shopfans\Api\UserApi::class);
                $result = $shopfans->createManifest($queryParams);
            } catch (\Exception $e) {
                TelegramQueueJob::push($e->getMessage(), 'ОШИБКА', Events::getEmailParseManifestChatId());
                TelegramQueueJob::push($e->getTraceAsString(), 'ОШИБКА', Events::getEmailParseManifestChatId());

                return false;
            }

            TelegramQueueJob::push($result, 'РЕЗУЛЬТАТ', Events::getEmailParseManifestLogsChatId());
            TelegramQueueJob::push($result, 'РЕЗУЛЬТАТ', Events::getEmailParseManifestChatId());
        }
    }

    public function changeName($name, $orderProduct)
    {
        return $name . ' / Заказ: ' . $orderProduct->order_id;
    }

    /**
     * Не используется,
     * сделали выборку по товарам
     * где статус order package выкуплено
     *
     * @param $withTrackNumberOrderProduct
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function notificationOfProductWithTrackNumber($withTrackNumberOrderProduct)
    {
        $count = count($withTrackNumberOrderProduct);
        $emailMessage = $this->getEmailMessage();
        $subject = $emailMessage->getHeaderValue('subject');
        $date = $emailMessage->getHeaderValue('date');
        $marketsName = ArrayHelper::map(Yii::$app->markets->all(), 'id', 'name');
        $marketName = $marketsName[$this->getMarketId()];
        $productNames = implode("\n", $withTrackNumberOrderProduct);
        $message = sprintf("Было найдено товаров: %s, с присвоенными в заказе трек-номерами. \n Market: %s \n Тема письма: %s \n Дата: %s \n  Наименовая товаров: \n",
                $count, $marketName, $subject, $date) . $productNames;

        TelegramQueueJob::push($message, 'Трек-номера уже присвоены', TelegramQueueJob::getEmailParseChatId());
        TelegramQueueJob::push($message, 'Трек-номера уже присвоены', Events::getEmailParseErrorChatId());
    }

    public function refundedOrderProducts($refundedData)
    {
        $user = User::findOne(User::BOT_ACCOUNT);
        if (!(Yii::$app instanceof yii\console\Application)) {
            Yii::$app->user->login($user);
        }

        $message = '';

        foreach ($refundedData as $packageData) {
            $productIds = ArrayHelper::getColumn($packageData['packageProducts'], 'order_product_id');
            $statusOrderProducts = ArrayHelper::getColumn($packageData['packageProducts'], 'status');
            $statusOrderProducts = array_values(array_unique($statusOrderProducts));
            /** @var $orderPackage OrderPackage */
            $orderPackage = $packageData['object'];
            if (count($statusOrderProducts) == 1 && $statusOrderProducts[0] == OrderProduct::STATUS_REDEEMED && $orderPackage->refund_payment_id == null) {
                if ($packageData['count'] == count($productIds)) {
                    $orderPackage->refundMoney(-1, true);
                    if ($orderPackage->refund_payment_id) {
                        $message = 'Возврат оформлен.';
                    } else {
                        $message = 'Что-то пошло не так, проверить оформление платежа на возврат';
                    }
                } elseif (($packageData['count']) > count($productIds)) {
                    $packageProducts = $orderPackage->getPackageProducts()->indexBy('id')->all();
                    $resultDiffPackageProductIds = array_diff(array_keys($packageProducts), $productIds);

                    //нужно делить
                    $orderPackageEditor = new OrderPackageEditor();
                    $orderPackageEditor->setScenario(OrderPackageEditor::SCENARIO_PARTITION_PACKAGE);

                    $loadDataPartition = [
                        'packageId' => $orderPackage->id,
                        'productIds' => $resultDiffPackageProductIds
                    ];
                    if ($orderPackageEditor->load($loadDataPartition) && $orderPackageEditor->validate()) {
                        try {
                            $orderPackageEditor->partitionPackage(true);
                            $orderPackage->refresh();
                            $packageProducts = $orderPackage->getPackageProducts()->indexBy('id')->asArray()->all();
                            $resultDiffPackageProductIds = array_diff(array_keys($packageProducts), $productIds);

                            //Если результат пустой => все товары из package присутствуют, можно проводить возврат
                            if (count($resultDiffPackageProductIds) == 0) {
                                $orderPackage->refundMoney(-1, true);
                                if ($orderPackage->refund_payment_id) {
                                    $message = "Возврат оформлен.";
                                } else {
                                    $message = 'Что-то пошло не так, проверить оформление платежа на возврат';
                                }
                            }
                        } catch (\Exception $e) {
                            $message = sprintf('message = %s order_id = %s package_id = %s', $e->getMessage(), $orderPackage->order_id, $orderPackage->id);
                            TelegramQueueJob::push($message, '!!!ОШИБКА при делении package ', Events::getEmailParseReturnsChatId());
                        }
                    } else {
                        TelegramQueueJob::push(['loadDataPartition' => $loadDataPartition, 'error' => $orderPackageEditor->errors], '!!!ОШИБКА валидации orderPackageEditor ', Events::getEmailParseReturnsChatId());
                    }

                    $info = ArrayHelper::getColumn($packageData['packageProducts'], 'info');
                    $message .= " Номер заказа в маркете: " . $orderPackage->external_order_id . implode('  ', $info) .
                        " \nOrder Package: " . $orderPackage->id . " \nСсылка на заказ: https://app.usmall.ru/order/" . $orderPackage->order_id;
                    TelegramQueueJob::push($message, $orderPackage->external_order_id, Events::getEmailParseReturnsChatId());

                } else {
                    TelegramQueueJob::push('что-то пошло не так товаров в пекадже меньше чем в списке на возврат', '!!!ОШИБКА', Events::getEmailParseReturnsChatId());
                    $info = ArrayHelper::getColumn($packageData['packageProducts'], 'info');
                    $info = "Номер заказа в маркете: " . $orderPackage->external_order_id . implode('  ', $info) .
                        " \nOrder Package: " . $orderPackage->id . " \nСсылка на заказ: https://app.usmall.ru/order/" . $orderPackage->order_id;
                    TelegramQueueJob::push($info, $orderPackage->external_order_id, Events::getEmailParseReturnsChatId());
                }
            }
        }
    }

    /**     *
     * @param $validateData
     * @return array|string
     */
    public function orderPackagesForDivisionExecute($validateData)
    {
        $orderProducts = [];
        $orderPackages = [];
        $externalOrderId = '';
        if (isset($validateData['external_order_id']) && $validateData['external_order_id'] != '') {
            $externalOrderId = $validateData['external_order_id'];
        } else {
            TelegramQueueJob::push('Не найден номер заказа', 'Валидация', Events::getEmailParseTestChatId());
            return '';
        }

        $exclusion = [];
        if ($validateData['multi_track']) {
            foreach ($validateData['data'] as $data) {
                $orderProducts = $this->getOrderProducts($externalOrderId, $exclusion);
                $trackingNumber = $data[0]['tracking_number'];
                $orderPackages[$trackingNumber] = $this->checkQtyOrderProducts($data, $orderProducts, $exclusion);
            }
        } else {
            $orderProducts = $this->getOrderProducts($externalOrderId, $exclusion);
            $trackingNumber = $validateData['data'][0]['tracking_number'];
            $orderPackages[$trackingNumber] = $this->checkQtyOrderProducts($validateData['data'], $orderProducts, $exclusion);
        }

        if ($this->web) {
            foreach ($orderPackages as $key => $item) {
                TelegramQueueJob::push($key, 'trackNumber', Events::getEmailParseTestChatId());
            }
        }

        return $orderPackages;
    }

    public function checkQtyOrderProducts(&$productsEmail, &$orderProducts, &$exclusion)
    {
        $packages = [];

        /* @var OrderProduct $orderProduct */
        foreach ($productsEmail as $key => &$productEmail) {
            foreach ($orderProducts as $oprId => $orderProduct) {
                if ($orderProduct->product_variant_id === $productEmail['product_variant_id']) {
                    $exclusion[] = $orderProduct->id;
                    $packages[$orderProduct->orderPackage->id]['products'][$orderProduct->id] = $orderProduct;
                    if ($productEmail['quantity'] >= $orderProduct->quantity) {
                        $productEmail['quantity'] -= $orderProduct->quantity;
                        unset($orderProducts[$oprId]);
                        if ($productEmail['quantity'] == 0) {
                            unset($productsEmail[$key]);
                            continue 2;
                        }
                    } else {
                        $packages[$orderProduct->orderPackage->id]['quantity'][$orderProduct->id] = $orderProduct->quantity - $productEmail['quantity'];
                        unset($productsEmail[$key]);
                        continue 2;
                    }
                }
            }
        }

        // уведомление про то что осталось с письма, не смогли найти и остатки
        if (!empty($productsEmail)) {
            $this->notificationCheckForDivision(array_values($productsEmail));
        }

        $orderPackages = $this->checkPartitionPackages($packages);

        return $orderPackages;
    }

    /**
     * @param $externalOrderId
     * @param $exclusion
     * @return OrderProduct[]|array|\yii\db\ActiveRecord[]
     */
    private function getOrderProducts($externalOrderId, $exclusion = [])
    {
        $orderProducts = [];
        if ($externalOrderId == '') {
            TelegramQueueJob::push('Номер заказа не известен (getOrderProducts)',
                'Валидация');
            return $orderProducts;
        }
        $orderProductQuery = OrderProduct::find()
            ->joinWith('orderPackage')
            ->where(['op.status' => OrderPackage::STATUS_REDEEMED])
            ->andWhere(['op.external_order_id' => $externalOrderId])
            ->indexBy('id')
            ->orderBy(['quantity' => SORT_DESC, 'op.order_id' => SORT_ASC]);

        //исключить продукты, которым уже нашли информацию в письме
        if (!empty($exclusion)) {
            $orderProductQuery->andWhere(['not in', 'order_product.id', $exclusion]);
        }

        $orderProducts = $orderProductQuery->all();

        if (empty($orderProducts) && empty($exclusion)) {
            TelegramQueueJob::push('Не найдены товары в packages со статусом выкуплено по номеру заказа ' . $externalOrderId,
                'Валидация');
            return $orderProducts;
        }

        return $orderProducts;
    }

    /**
     * @param $packages
     * @return array|string
     */
    private function checkPartitionPackages($packages)
    {
        if (empty($packages)) {
            TelegramQueueJob::push('Не найдены товары в packages со статусом выкуплено (checkPartitionPackages)',
                'Валидация');
            return '';
        }
        $orderPackage = [];
        foreach ($packages as $key => $package) {
            $orderPackage = OrderPackage::findOne($key);
            if (empty($orderPackage)) {
                TelegramQueueJob::push('Не найден order_package id ' . $key,
                    'Валидация');
                continue;
            }
            $orderProductData = $orderPackage->getPackageProducts()->indexBy('id')->asArray()->all();
            if (empty($orderProductData)) {
                TelegramQueueJob::push('Не найдены товары в order_package id ' . $key,
                    'Валидация');
                continue;
            }
            $resultDiffOrderProductIds = array_diff(array_keys($orderProductData), array_keys($package['products']));
            $quantityProduct = [];

            if (isset($package['quantity'])) {
                $quantityProduct = $package['quantity'];
                foreach ($quantityProduct as $oprId => $qty) {
                    if (array_search($oprId, $resultDiffOrderProductIds) === false) {
                        $resultDiffOrderProductIds[] = $oprId;
                    }
                }
            }
            //если НЕ все товары найденные в письме отсутствуют в package
            //Обработка перенесена в начало из условия иначе чтобы вначале поделить этот пекадж
            //и потом если все подходит установить трек
            if (count($resultDiffOrderProductIds) !== 0 && ((count($resultDiffOrderProductIds) != count($orderProductData)) || !empty($quantityProduct))) {

                $orderPackageEditor = new OrderPackageEditor();
                $orderPackageEditor->setScenario(OrderPackageEditor::SCENARIO_PARTITION_PACKAGE);

                $loadDataPartition = [
                    'packageId' => $orderPackage->id,
                    'productIds' => $resultDiffOrderProductIds,
                    'quantityProducts' => $quantityProduct,
                ];
                if ($orderPackageEditor->load($loadDataPartition) && $orderPackageEditor->validate()) {
                    try {
                        $message = sprintf('order: %s  package: %s', $orderPackage->order_id, $orderPackage->id);
                        TelegramQueueJob::push($message, 'checkPartitionPackages ' . $this->email_parse_id);
                        $orderPackageEditor->partitionPackage(true);
                        $message = sprintf('разделил order_id = %s package_id = %s', $orderPackage->order_id, $orderPackage->id);
                        TelegramQueueJob::push($message, 'УспешНо разделил package ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                    } catch (\Exception $exception) {
                        $message = sprintf('message = %s order_id = %s package_id = %s', $exception->getMessage(), $orderPackage->order_id, $orderPackage->id);
                        TelegramQueueJob::push($message, '!!!ОШИБКА при делении package ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                        TelegramQueueJob::push($message, '!!!ОШИБКА при делении package ' . $this->email_parse_id, Events::getEmailParseErrorChatId());
                        TelegramQueueJob::push($exception->getTraceAsString(), '!!!ОШИБКА при делении package ' . $this->email_parse_id);
                        TelegramQueueJob::push($exception->getTraceAsString(), '!!!ОШИБКА при делении package ' . $orderPackage->id . ' ' . $this->email_parse_id, Events::getSplitDisChatId());
                    }
                } else {
                    TelegramQueueJob::push(['loadDataPartition' => $loadDataPartition, 'error' => $orderPackageEditor->errors], '!!!ОШИБКА валидации orderPackageEditor ' . $this->email_parse_id, TelegramQueueJob::getEmailParseChatId());
                    TelegramQueueJob::push(['loadDataPartition' => $loadDataPartition, 'error' => $orderPackageEditor->errors], '!!!ОШИБКА валидации orderPackageEditor ' . $this->email_parse_id, Events::getSplitDisChatId());
                }
            }

            $orderPackage->refresh();
            //делать ли повторную проверку?
            $orderPackages[] = $orderPackage;
        }

        return $orderPackages;
    }

    private function notificationOfProductInSplitAndTrackNumber($resultData, $marketName)
    {
        $externalOrderId = $resultData[0]['external_order_id'];

        $queryResult = Yii::$app->db->createCommand('select order_package.id, order_package.order_id, order_package.external_order_id, order_package.tracking_number,
                                                            sp.tracking_number as split_tracking_number,
                                                            sp.id as split_id,
                                                            opp.order_product_id,
                                                            op.name_for_declaration
                                                        from order_package
                                                            left join order_package_product opp ON order_package.id = opp.order_package_id
                                                            left join order_product op ON op.id = opp.order_product_id
                                                            left join split_package sp ON sp.split LIKE CONCAT("%", order_package.sf_package_id, "%")
                                                        where external_order_id = :externalOrderId and order_package.status = 8', ['externalOrderId' => $externalOrderId])
                                                        ->queryAll();

        $resultArraySplit = [];
        $resultArrayNotSplit = [];
        $productNotFound = [];

        if (!empty($queryResult)) {
            //ищем совпадение по split_tracking_number или tracking_number
            foreach ($resultData as $resultItem) {
                $found = false;
                foreach ($queryResult as $queryItem) {
                    if ($resultItem['tracking_number'] == $queryItem['split_tracking_number']) {
                        $resultArraySplit[] = [
                            'name' => $resultItem['name'],
                            'split_id' => $queryItem['split_id'],
                            'split_tracking_number' => $queryItem['split_tracking_number'],
                        ];
                        $found = true;
                        break; // Прерываем внутренний цикл, так как нашли совпадение
                    } elseif ($resultItem['tracking_number'] == $queryItem['tracking_number']) {
                        $resultArrayNotSplit[] = [
                            'name' => $resultItem['name'],
                            'order_id' => $queryItem['order_id'],
                            'tracking_number' => $resultItem['tracking_number'],
                            'split_tracking_number' => $queryItem['split_tracking_number'],
                        ];
                        $found = true;
                        break; // Прерываем внутренний цикл, так как нашли совпадение
                    }
                }
                if (!$found) {
                    $productNotFound[] = $resultItem['name'];
                }
            }

            //формируем массив уникальных значений. split_id, split_tracking_number из $resultArray
            $uniqueSplitIds = [];
            foreach ($resultArraySplit as $item) {
                $uniqueSplitIds[$item['split_id']] = $item['split_tracking_number'];
            }

            //формируем массив уникальных значений order_id из $resultArrayNotSplit
            $uniqueNotSplitIds = array_unique(array_column($resultArrayNotSplit, 'order_id'));

            $emailMessage = $this->getEmailMessage();
            $subject = $emailMessage->getHeaderValue('subject');
            $date = $emailMessage->getHeaderValue('date');

            //если есть не найденные товары, то формируем уведомление
            if (!empty($productNotFound)) {
                //формируем уведомление
                $message = sprintf("Данные о товарах были найдены. \nMarket: %s \nТема письма: %s \nДата: %s \nКол-во найденных товаров: %s \nНомер заказа: %s \n",
                        "<strong>$marketName</strong>", $subject, $date, "<strong>" . count($resultData) . "</strong>", "<strong>$externalOrderId</strong>");

                if (!empty($resultArraySplit)) {
                    $message .= "-------------------\n";
                    $message .= "Товаров в сплите (" . count($resultArraySplit) . "): \n";
                    foreach ($uniqueSplitIds as $key => $value) {
                        $message .= "<a href='https://app.usmall.ru/split-package/$key'><strong>$value</strong></a>\n";
                    }
                }

                if (!empty($resultArrayNotSplit)) {
                    $message .= "-------------------\n";
                    $message .= "Товары без сплита (" . count($resultArrayNotSplit) . "): \n";
                    foreach ($uniqueNotSplitIds as $item) {
                        $message .= "<a href='https://app.usmall.ru/order/view/$item?type=only'><strong>$item</strong></a>\n";
                    }
                }

                //выводим список не найденных позиции в заказах
                $message .= "\n-------------------\n";
                $message .= "Не нашли в заказах: (" . count($productNotFound) . ")\n";
                //перечень не найденных позиции в заказах
                $message .= implode("\n", $productNotFound);
            }
            else {
                return;
            }
        } else {
            //если queryResult пустой значит не нашли ни одного товара в заказах
            $emailMessage = $this->getEmailMessage();
            $subject = $emailMessage->getHeaderValue('subject');
            $date = $emailMessage->getHeaderValue('date');
            $productNames = ArrayHelper::getColumn($resultData, 'name');
            $productNames = implode("\n", $productNames);
            $product = current($resultData);
            $message = sprintf("Данные о товарах были найдены. Товары в заказах не обнаружены.\n Market: %s\n Тема письма: %s\n Дата: %s\n Кол-во найденных товаров: %s\n Номер заказа: %s\n Наименование товаров:\n",
                    $marketName, $subject, $date, count($resultData), $product['external_order_id']) . $productNames;
        }

        TelegramQueueJob::push($message, 'Проверить письмо', TelegramQueueJob::getEmailParseChatId());
        TelegramQueueJob::push($message, 'Проверить письмо', Events::getEmailParseErrorChatId());
    }

    public function checkExternalNumber($externalNumber)
    {
        $massOrderDiscount = MassOrderDiscount::find()->byExternalNumber($externalNumber)->one();
        if ($massOrderDiscount instanceof MassOrderDiscount) {
            return true;
        }

        return false;
    }

    public function checkCancelEmailParseCost($externalNumber)
    {
        $emailParseCost = EmailParseCost::find()
            ->where(['external_number' => $externalNumber])
            ->andWhere(['market_id' => $this->getMarketId()])
            ->andWhere(['operation' => EmailParseCost::OPERATION_CANCEL])
            ->one();
        if ($emailParseCost instanceof EmailParseCost) {
            return true;
        }

        return false;
    }

    public function checkConfirmEmail()
    {
        return false;
    }

    public function checkShippingEmail()
    {
        return false;
    }

    public function checkCancelEmail()
    {
        return false;
    }

    public function checkRefundEmail()
    {
        return false;
    }

    public function confirmEmailParseCost($message)
    {
        return;
    }

    public function shippingEmailParseCost($message)
    {
        return;
    }

    public function refundEmailParseCost($message)
    {
        return;
    }
    public function cancelEmailParseCost($message)
    {
        return;
    }

    protected function filterResultData($dataOrderTrack)
    {
        //проверяем
        if (isset($dataOrderTrack[0]['external_order_id']) && $dataOrderTrack[0]['external_order_id'] != '') {
            $externalOrderId = $dataOrderTrack[0]['external_order_id'];

            $queryResult = Yii::$app->db->createCommand('select
                                                            order_package.tracking_number,
                                                            sp.tracking_number as split_tracking_number
                                                        from order_package
                                                            left join split_package sp ON sp.split LIKE CONCAT("%", order_package.sf_package_id, "%")
                                                        where external_order_id = :externalOrderId', ['externalOrderId' => $externalOrderId])
                ->queryAll();

            //получаем уникальный список tracking_number и split_tracking_number из $queryResult
            $trackingNumbersQueryResult = array_filter(
                array_unique(
                    array_merge(
                        ArrayHelper::getColumn($queryResult, 'tracking_number'),
                        ArrayHelper::getColumn($queryResult, 'split_tracking_number')
                    )
                )
            );

            //удаляем из $dataOrderTrack tracking_number, которые есть в $trackingNumbersQueryResult
            foreach ($dataOrderTrack as $key => $value) {
                if (in_array($value['tracking_number'], $trackingNumbersQueryResult)) {
                    unset($dataOrderTrack[$key]);
                }
            }

            //устанавливаем индексы списка $dataOrderTrack
            return array_values($dataOrderTrack);
        }

        return $dataOrderTrack;
    }

    public function noticeEmptyTrack($noticeData)
    {
        $count = count($noticeData);
        $emailMessage = $this->getEmailMessage();
        $subject = $emailMessage->getHeaderValue('subject');
        $date = $emailMessage->getHeaderValue('date');
        $marketsName = ArrayHelper::map(Yii::$app->markets->all(), 'id', 'name');
        $marketName = $marketsName[$this->getMarketId()];
        $productNames = ArrayHelper::getColumn($noticeData, 'name');
        $productNames = implode("\n", $productNames);
        $message = sprintf("Было найдено товаров: %s, нет трек-номера в письме. \n Market: %s \n Тема письма: %s \n Дата: %s \n  Наименовая товаров: \n",
                $count, $marketName, $subject, $date) . $productNames;

        TelegramQueueJob::push($message, 'Нет трек-номеров', Events::getEmailParseWebChatId());
//        TelegramQueueJob::push($message, 'Трек-номера уже присвоены', Events::getEmailParseErrorChatId());
    }

    /**
     * @param OrderPackage $originalPackage
     * @param array $cancelledProductIds
     * @param array $cancelledProductCount productId=>count
     * @return void
     */
    public function initPackageRefund($originalPackage,
                                      $cancelledProductIds,
                                      $cancelledProductCount,
                                      $externalOrderNumber)
    {
        if (empty($cancelledProductIds)) {
            return;
        }

        if ($originalPackage->isNeedSplitPackage($cancelledProductIds, $cancelledProductCount)) {
            // не совпадает количество продуктов в пекедже - надо делить пекедж
           $item = $this->splitPackage($originalPackage, $cancelledProductIds, $cancelledProductCount, $externalOrderNumber);
           if($item){
               $originalPackage = $item;
           } else {
               $message = "Ошибка деления package при отмене части пекеджа {$originalPackage->id} \n";
               $message .=  "https://app.usmall.ru/order/view/{$originalPackage->order_id}?type=only";
               $message .= "https://app.usmall.ru/email-parse/index?external_order_id={$externalOrderNumber}";

               TelegramQueueJob::push($message, $externalOrderNumber, Events::getEmailParseReturnsTechChatId());
           }
        }
        $refundEnabled = Setting::enabled(Setting::PARSE_EMAIL_REFUND_ENABLED);
        if($refundEnabled) {
            $originalPackage->refundMoney(-1);
            $originalPackage->successReturnMoney();
        }

        $marketsName = ArrayHelper::map(Yii::$app->markets->all(), 'id', 'name');
        $marketName = $marketsName[$this->getMarketId()];

        $message = sprintf("Было найдено отмен товаров: %s. \nMarket: %s \nPackageId: %s  \nOrder: %s \nID товаров: \n",
                count($cancelledProductIds), $marketName, $originalPackage->id, 'https://app.usmall.ru/order/view/' . $originalPackage->order_id . '?type=only');
        $message .=  implode(',', $cancelledProductIds);
        $message .= "\n https://app.usmall.ru/email-parse/index?external_order_id={$externalOrderNumber}";

        TelegramQueueJob::push($message, 'Нашли отмену товаров '. $externalOrderNumber, Events::getEmailParseReturnsChatId());
    }


    /**
     * выделение отменённых товаров в отдельный пекедж и возврат
     * @param OrderPackage $originalPackage
     * @param array $cancelledProductIds
     * @param array $cancelledProductCount
     * @param $externalOrderNumber
     */
    public function splitPackage($originalPackage, $cancelledProductIds, $cancelledProductCount, $externalOrderNumber)
    {
        $idsStr = implode(', ', $cancelledProductIds);

        $products = $originalPackage->getPackageProducts()->all();

        $oldPackageProductIds = [];
        foreach ($products as $product) {
            if(in_array($product->id, $cancelledProductIds)) {
                $oldPackageProductIds[] = $product->product_id;
            }
        }
        $data = [
            'packageId' => $originalPackage->id,
            'productIds' => $cancelledProductIds,
            'quantityProducts' => $cancelledProductCount, // для разделения количества productId => count
        ];

        $orderPackageEditor = new OrderPackageEditor();
        $orderPackageEditor->setScenario(OrderPackageEditor::SCENARIO_PARTITION_PACKAGE);
        if ($orderPackageEditor->load($data) && $orderPackageEditor->validate()) {
            if ($orderPackageEditor->partitionPackage(true) === false) {
                $message = "Order id: {$originalPackage->order_id} \n Package id: {$originalPackage->id} \n";
                $message .= "Order product ids: {$idsStr} \n";
                $message .= "https://app.usmall.ru/order/view/{$originalPackage->order_id}?type=only \n";
                $message .= "https://app.usmall.ru/email-parse/index?external_order_id={$externalOrderNumber}";

                TelegramQueueJob::push(
                    $message,
                    'Ошибка деления package. '. $externalOrderNumber,
                    Events::getEmailParseReturnsChatId()
                );
            } else {
                $newPackageId = $orderPackageEditor->newPackageId;
                $newPackage = OrderPackage::findOne($newPackageId);
                $products = $newPackage->getPackageProducts()->all();

                $newPackageProductIds = [];
                foreach ($products as $product) {
                    $newPackageProductIds[] = $product->product_id;
                }

                if (!empty(array_diff($oldPackageProductIds, $newPackageProductIds))) {
                    $diffArr = array_diff($oldPackageProductIds, $newPackageProductIds);
                    $diff = implode(',', $diffArr);

                    $message = "Ошибка деления package из бота отмены. Разделённый список не совпадает с изначальным \n";
                    $message .= "Order id: {$originalPackage->order_id}, package id: {$originalPackage->id} \n";
                    $message .= "Diff order product ids: {$diff} \n";
                    $message .= "https://app.usmall.ru/order/view/{$originalPackage->order_id}?type=only \n";
                    $message .= "https://app.usmall.ru/email-parse/index?external_order_id={$externalOrderNumber}";

                    TelegramQueueJob::push(
                        $message,
                        'Ошибка деления package',
                        Events::getEmailParseReturnsChatId()
                    );
                    return false;
                }
                return $newPackage;
            }
        } else {
            $message = "Ошибка деления package из бота отмены. orderPackageEditor не прошёл валидацию \n";
            $message .= "Order id: {$originalPackage->order_id}, package id: {$originalPackage->id}, order product ids: {$idsStr} \n";
            $message .= "https://app.usmall.ru/order/view/{$originalPackage->order_id}?type=only \n";
            $message .= "https://app.usmall.ru/email-parse/index?external_order_id={$externalOrderNumber}";

            TelegramQueueJob::push(
                $message,
                "Ошибка деления package {$externalOrderNumber}",
                Events::getEmailParseReturnsChatId()
            );
        }
        return false;
    }
}
