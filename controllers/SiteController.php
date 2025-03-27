<?php

namespace app\controllers;

use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
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

    public function actionIndex()
    {
//        return $this->redirect('/compare');
        return $this->render('index');
    }

}
