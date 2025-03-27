<?php

namespace app\controllers;

//use app\web\Comparator\ComparatorForm;
//use app\web\Comparator\Helper;
//use app\web\Comparator\LowLevel\PingerService;
//use app\web\Comparator\Wirer;
use Exception;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\grid\GridView;
use yii\web\Controller;
use yii\web\Response;

class CompareController extends Controller
{
    public $layout = 'my';
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['actions' => [
                    'index', 'get-files', 'get-tags', 'is-model-valid',
                ], 'allow' => true,],],
            ],
        ];
    }
    public function beforeAction($action): bool
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        return $this->render('index2', []);

    }
//    public function actionIndex()
//    {
//
//        $model = new ComparatorForm();
//
//        if (
//            Yii::$app->request->isPost
//            && $model->load(Yii::$app->request->post())
//            && $model->validate()
//        ) {
//            try {
//                $comparatorService = (new Wirer())
//                    ->getComparatorService();
//                $comparatorService->compare($model);
//
//            } catch (Exception $e) {
//                (PingerService::getInstance())
//                    ->switch(PingerService::OFF);
//
//                return $this->asJson('Switched off pinger ' . $e->getMessage());
//            }
//
//            (PingerService::getInstance())->switch(PingerService::OFF);
//
//            return $this->asJson('Call to CompareController finished!');
//        }
//
//        if ($model->errors) {
//            return $this->asJson($model->errors);
//        }
//        $helper = new Helper();
//
//        return $this->render('index2', [
//            'model' => $model,
//            'markets' => Yii::$app->markets->all(),
//            'files' => $helper->getFiles(),
//            'defaultTag' => $helper->getDefaultTag(),
//            'tags' => $this->getTags(),
//        ]);
//    }

    public function actionGetFiles(): Response
    {
        return $this->asJson((new Helper())->getFiles());
    }

    public function actionGetTags(): Response
    {
        return $this->asJson($this->getTags());
    }

    private function getTags(): string
    {
        $provider = new ActiveDataProvider(
            [
                'query' => (new Query())
                    ->select([
                        'zz_tags.id',
                        'tag',
                        'count(*) AS count',
                    ])
                    ->from(
                        'zz_tags'
                    )->innerJoin(
                        'zz_cache_tags', 'zz_tags.id = zz_cache_tags.zz_tags_id'
                    )->innerJoin(
                        'zz_cache', 'zz_cache_tags.zz_cache_id = zz_cache.id'
                    )->groupBy(['zz_tags.id'])
                    ->orderBy(['zz_tags.id' => SORT_DESC]),
//                ->column()
                'pagination' =>
                    false,
//          [
//              'pageSize' => 15,
//          ],
            ]
        );

        try {
//    throw new Exception('Oops!');
            $cachedFiles = GridView::widget(['dataProvider' => $provider, 'options' => ['style' => 'max-height: 30vh;',], 'columns' => [['class' => 'yii\grid\SerialColumn', 'headerOptions' => ['style' => 'width: 30px; overflow: hidden;text-align: center;'], 'contentOptions' => ['style' => 'width: 30px; overflow: hidden;'],], //                'id',
//                [
//                    'attribute' => 'old_file_name',
//                    'header' => 'Name',
//                    'headerOptions' => ['style' => 'width: 20%; overflow: hidden;'],
//                    'contentOptions' => ['style' => 'width: 20%; overflow: hidden;'],
//                ],
                ['attribute' => 'tag', 'header' => 'Tag / Use case number', 'headerOptions' => ['style' => 'width: 60%; overflow: hidden;text-align: center;'], 'contentOptions' => ['style' => 'width: 60%; overflow: hidden;'],], ['attribute' => 'count', 'header' => 'Number of letters', 'headerOptions' => ['style' => 'width: 35%; overflow: hidden;text-align: center;'], 'contentOptions' => ['style' => 'width: 35%; overflow: hidden;'],], ['class' => 'yii\grid\CheckboxColumn', 'checkboxOptions' => function (
                    $model, $key, $index, $column
                ) {
                    return ['value' => $model['id']];
                }, 'header' => 'Action', 'headerOptions' => ['style' => 'width: 20%; overflow: hidden;text-align: center;'], 'contentOptions' => ['style' => 'width: 20%; overflow: hidden; text-align: center;',],], //                [
//                    'header' => 'Action',
//                    'value' => function ($model) { return ''; },
//                    'headerOptions' => ['style' => 'width: 20%; overflow: hidden;'],
//                    'contentOptions' => ['style' => 'width: 20%; overflow: hidden;'],
//                ],
//                [
//                    'class' => ActionColumn::class, 'header' => 'Action',
//                    'headerOptions' => ['style' => 'width: 20%; overflow: hidden;'],
//                    'contentOptions' => ['style' => 'width: 20%; overflow: hidden;'],
//                ],
            ], 'headerRowOptions' => ['style' => 'display: flex; overflow: hidden; width: 100%'], 'rowOptions' => ['style' => 'display: flex; overflow: hidden; width: 100%'],]);
        } catch (Exception $e) {
//        echo 'Stored files table<br>';
            echo $e->getMessage();
        }

        return $cachedFiles ?? '';
    }

    public function actionIsModelValid()
    {
        $model = new ComparatorForm();

        return $this->asJson(
            Yii::$app->request->isPost
            && $model->load(Yii::$app->request->post())
            && $model->validate()
        );

    }

}