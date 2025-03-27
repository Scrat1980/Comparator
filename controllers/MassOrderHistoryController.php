<?php

namespace app\controllers;

use app\controllers\FlashTrait;
use app\records\MassOrderDiscount;
use app\records\MassOrderProduct;
use app\records\OrderPackage;
use app\Access;
use app\models\MassOrderProductEditor;
use app\models\OrderPackageEditor;
use app\models\PriceChangeEditor;
use app\models\SplitPackageEditor;
use app\models\MassOrderDiscountFilter;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MassOrderHistoryController extends Controller
{
    use FlashTrait;
    public $layout = 'column2';

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index',],
                        'roles' => ['?',],
                        'allow' => true,
                    ],
                ],
            ],
        ];
    }


    /**
     * @return string
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionIndex()
    {
        // объект фильтрации, сюда будем помещать данные для передачи в фильтр
        $queryParams = [];
        // проверяем наличие параметра order_product_id
        $orderProductId = Yii::$app->request->get('order_product_id');

        // если передан параметр order_product_id
        if ($orderProductId) {
            // получаем данные mass_order_discount_id
            $massOrderProductData = MassOrderProduct::find()
                ->select('mass_order_discount_id')
                ->where(['order_product_id' => $orderProductId])
                ->column();

            // если mass_order_discount_id найден
            if (!empty($massOrderProductData)) {
                // если больше одного
                if (count($massOrderProductData) > 1) {
                    return $this->render('index', [
                        'navItems' => $this->getNavItems(),
                        'filter' => MassOrderDiscountFilter::ensure(),
                        'queryParams' => $massOrderProductData,
                    ]);
                    // если один
                } elseif (count($massOrderProductData) == 1) {
                    return $this->redirect(['view-actual', 'id' => $massOrderProductData[0]]);
                }
            }
        }

        // возвращаем представление
        return $this->render('index', [
            'navItems' => $this->getNavItems(),
            'filter' => MassOrderDiscountFilter::ensure(),
            'queryParams' => $queryParams
        ]);
    }

    /**
     * @param $market_id
     * @return string
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionHistory($market_id)
    {
        $navItems = $this->getNavItems();

        return $this->render('history', [
            'navItems' => $navItems,
            'marketId' => $market_id,
            'marketName' => $navItems[$market_id]['label'],
            'queryParams' => [],
            'filter' => MassOrderDiscountFilter::ensure(),
        ]);
    }

    /**
     * @param $id
     * @return Response
     */
    public function actionView($id)
    {
        return $this->redirect(['view-actual', 'id' => $id]);
    }

    public function actionViewHistory($id)
    {
        $record = $this->findRecord($id);

        $markets = Yii::$app->markets->all();
        $marketName = '';
        foreach ($markets as $market) {
            if ($market->id == $record->market_id) {
                $marketName = $market->name;
                break;
            }
        }

        $countOrderProductWithTrack = $record->getMassOrderProductsWithTracking();

        return $this->render('view-history', [
            'model' => $record,
            'query' => $record->getMassOrderProduct(),
            'marketName' => $marketName,
            'countOrderProductWithTrack' => $countOrderProductWithTrack
        ]);
    }

    public function actionViewSeparatedHistory($id)
    {
        $record = $this->findRecord($id);

        $markets = Yii::$app->markets->all();
        $marketName = '';
        foreach ($markets as $market) {
            if ($market->id == $record->market_id) {
                $marketName = $market->name;
                break;
            }
        }

        $countOrderProductWithTrack = $record->getSeparatedOrderProduct()
            ->andWhere(['not', ['op.tracking_number' => null]])
            ->andWhere(['not', ['op.tracking_number' => '']])
            ->andWhere(['op.status' => '8'])
            ->sum('quantity');

        return $this->render('view-separated-history', [
            'model' => $record,
            'query' => $record->getSeparatedOrderProduct(),
            'marketName' => $marketName,
            'countOrderProductWithTrack' => $countOrderProductWithTrack
        ]);
    }

    public function actionViewActual($id)
    {
        $record = $this->findRecord($id);

        $markets = Yii::$app->markets->all();
        $marketName = '';
        foreach ($markets as $market) {
            if ($market->id == $record->market_id) {
                $marketName = $market->name;
                break;
            }
        }

        //получаем количество товаров с треком
        $countOrderProductWithTrack = $record->getActualOrderProduct()
            ->andWhere(['not', ['op.tracking_number' => null]])
            ->andWhere(['not', ['op.tracking_number' => '']])
            ->andWhere(['op.status' => '8'])
            ->sum('quantity');

        return $this->render('view-actual', [
            'model' => $record,
            'query' => $record->getActualOrderProduct(),
            'marketName' => $marketName,
            'countOrderProductWithTrack' => $countOrderProductWithTrack
        ]);
    }

    /**
     * @param $id
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionUpdate($id)
    {
        $record = $this->findRecord($id);
        if ($record->load(Yii::$app->request->bodyParams) && $record->validate() && $record->save()) {
            return $this->success('Mass Order Discount ' . $id . ' was updated.')->redirect(['view', 'id' => $id]);
        }
        return $this->render('update', compact('record'));
    }

    /**
     * @param $id
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionUpdateProduct($id)
    {
        $record = $this->findRecordProduct($id);
        if ($record->load(Yii::$app->request->bodyParams) && $record->validate() && $record->save()) {
            return $this->success('Mass Order Product ' . $id . ' was updated.')->redirect(['view', 'id' => $record->mass_order_discount_id]);
        }
        return $this->render('update-product', compact('record'));
    }

    /**
     * @param $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete($id)
    {
        $record = $this->findRecord($id);
        if ($record->massOrderProduct) {
            $this->error('К записи привязаны товары. Нeльзя удалять');
            return $this->redirect(['history', 'marketId' => $record->market_id]);
        }

        if ($record->delete()) {
            $this->success('Mass Order Discount was deleted.');
        }
        return $this->redirect(['index']);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDeleteProduct($id)
    {
        $record = $this->findRecordProduct($id);
        if ($record->delete()) {
            $this->success('Mass Order Product was deleted.');
        }
        return $this->redirect(['view', 'id' => $record->mass_order_discount_id]);
    }

    /**
     * @return array
     * @throws InvalidConfigException
     * @throws Exception
     */
    private function getNavItems(): array
    {
        $items = [32 => ['label' => 'Kors', 'url' => 'url']];
//        $action = 'index?market_id=';
//        $markets = \Yii::$app->markets->all();
//        $items = [];
//        foreach ($markets as $market) {
//            $items[$market->id] = ['label' => $market->name, 'url' => $action . $market->id];
//        }
        return $items;
    }

    /**
     * @param int $id
     * @return MassOrderDiscount|null
     */
    protected function findRecord(int $id)
    {
        if ($model = MassOrderDiscount::findOne($id)) {
            return $model;
        }
        throw new NotFoundHttpException('Not found.');
    }

    /**
     * @param int $id
     * @return MassOrderProduct|null
     */
    protected function findRecordProduct(int $id)
    {
        if ($model = MassOrderProduct::findOne($id)) {
            return $model;
        }
        throw new NotFoundHttpException('Not found.');
    }

    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionReturnToOrder($id){

        $model = new MassOrderProductEditor();
        $model->mass_order_discount_id = $id;
        $model->returnToOrder();

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionReturnToRedeemOrder($id){

        $model = new MassOrderProductEditor();
        $model->mass_order_discount_id = $id;
        $model->returnToRedeemOrder();

        return $this->redirect(['view', 'id' => $id]);
    }


    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionReturnToSeparatedOrder($id){

        $model = new MassOrderProductEditor();
        $model->mass_order_discount_id = $id;
        $model->returnToSeparatedOrder();

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * @return array
     */
    public function actionChangePriceByPromocode()
    {
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        $model = new PriceChangeEditor();
        $model->scenario = PriceChangeEditor::SCENARIO_PERCENT_MOD;
        $response = [];

        if ($model->load(Yii::$app->request->post(), '') && $model->validate()) {
            $response = $model->priceChangeByPromocodeMod();
        } else {
            $response['error'][] = 'Не были переданы данные о товарах';
        }

        if (isset($response['error'])) {
            $error = '';
            foreach ($response['error'] as $item) {
                $error .= $item . ' ';
            }
            $response['error'] = $error;
        }

        return $response;
    }

    public function actionChangeTrackingNumber()
    {
        Yii::$app->getDb()->enableSlaves = false;
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        $params = Yii::$app->request->post();
        $response = [];
        $trackingNumber = $params['trackingNumber'];
        $massOrderDiscountId = $params['massOrderDiscountId'];

        $massOrderDiscount = $this->findRecord($massOrderDiscountId);
        $packageData = $this->getOrderPackage($massOrderDiscount->external_number);

        foreach ($packageData as $package) {
            $orderPackageEditor = new OrderPackageEditor();
            $orderPackageEditor->setScenario(OrderPackageEditor::SCENARIO_CHANGE_TRACK_NUMBER_BOT);
            $loadData = [
                'order_id' => $package['order_id'],
                'package_id' => $package['package_id'],
                'tracking_number' => $trackingNumber,
                'type_changed' => 1
            ];

            if ($orderPackageEditor->load($loadData) && $orderPackageEditor->validate()) {
                try {
                    $result = $orderPackageEditor->changeTrackNumber();
                    if ($result == OrderPackageEditor::REDIRECT_SPLIT) {
                        $splitPackageEditor = new SplitPackageEditor([
                            'tracking_number' => $trackingNumber,
                            'package_id' => $orderPackageEditor->package_id,
                        ]);
                        if ($splitPackageEditor->validate()) {
                            try {
                                $splitPackageId = $splitPackageEditor->execute();
                                $message = sprintf('https://app.usmall.ru/split-package/%s', $splitPackageId);
//                                $response['d'][] = 'package id ' . $package['package_id'] . '  ' . $message;

                            } catch (\Exception $exception) {
                                $message = sprintf('!!!АЛАРМАА SPLIT https://app.usmall.ru/split-package/add-package?package_id=%s&tracking_number=%s', $orderPackageEditor->package_id, $orderPackageEditor->tracking_number);
//                                $response['error'][] = 'package id ' . $orderPackageEditor->package_id . '  ' . $message;
                                $response['error'][] = 'package id ' . $exception->getTraceAsString();
                            }
                        } else {
                            $message = sprintf("Данные входные \n tracking_number = %s \n package_id = %s", $trackingNumber, $orderPackageEditor->package_id);
                            $message .= "\n";
                            $message .= sprintf("Данные in SplitPackageEditor \n tracking_number = %s \n package_id = %s", $splitPackageEditor->tracking_number, $splitPackageEditor->package_id);
                            $message .= "\n";
                            $message .= sprintf("Данные validate SplitPackageEditor \n %s", implode("\n", $splitPackageEditor->getErrors()));
                            $response['error'][] = 'package id ' . $orderPackageEditor->package_id . '  ' . $message;
                        }
                    }
//                if ($result == OrderPackageEditor::REDIRECT_ORDER) {
//                    $message = sprintf('https://app.usmall.ru/order/%s', $orderPackageEditor->order_id);
//                }
                    if ($result == OrderPackageEditor::REDIRECT_ORDER_SF_ERROR) {
                        $message = sprintf('Ошибка https://app.usmall.ru/order/%s', $orderPackageEditor->order_id);
                        $response['error'][] = 'package id ' . $orderPackageEditor->package_id . '  ' . $message;
                    }
                } catch (\Exception $exception) {
                    $response['error'][] = 'package id ' . $orderPackageEditor->package_id . '  ' . $exception->getMessage();
                }
            }
        }

        if(isset($response['error'])) {
            $response['error'] = implode(' <br> ', $response['error']);
        }

        return $response;
    }

    public function getOrderPackage($externalNumber)
    {
        $orderPackage = OrderPackage::find()
            ->where(['external_order_id' => $externalNumber])
            ->andWhere(['status' => OrderPackage::STATUS_REDEEMED])
            ->andWhere(['tracking_number' => ''])
            ->all();

        $packageData = [];

        foreach ($orderPackage as $package){
            $packageData[] = ['package_id' => $package->id, 'order_id' => $package->order_id];
        }

        return $packageData;
    }
}
