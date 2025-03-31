<?php

namespace app\controllers;

use app\models\RequestReplicationEditor;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;

class RequestReplicationController extends Controller
{
    use FlashTrait;

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => [
                            'orders'
                        ],
                        'roles' => ['?',],
                        'allow' => true,
                    ],
                ],
            ],
        ];
    }

    public function actionOrders(): string
    {
        /** @var/ RequestReplicationEditor $model */
        $model = new RequestReplicationEditor(['scenario' => RequestReplicationEditor::SCENARIO_MASS_ORDERS]);
        $query = ['initialQuery' => '', 'queryProduct' => '', 'queryOrder' => ''];

        if (
            $model->load(Yii::$app->request->post())
            && $model->validate()
        ) {
            $query = $model->getRequestEmailParsing();
        }

        return $this->render('email-parsing', [
            'model'        => $model,
            'labelEntity'  => 'Order Id',
            'initialQuery' => $query['initialQuery'],
            'queryProduct' => $query['queryProduct'],
            'queryOrder'   => $query['queryOrder']
        ]);
    }

    public function actionEmailParseData(): string
    {
        $model = new RequestReplicationEditor();
        $query = [];

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $query = $model->getRequestEmailParseData();
        }

        return $this->render('email-parse-data', [
            'model' => $model,
            'query' => $query
        ]);
    }
}
