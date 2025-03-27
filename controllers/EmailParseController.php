<?php

namespace app\controllers;

use app\markets\ParseEmail;
use app\records\EmailParse;
use app\records\MassOrderDiscount;
use app\records\OrderProduct;
use app\Access;
use app\models\EmailParseFilter;
use yii\filters\AccessControl;
use yii\web\{Controller, NotFoundHttpException};
use Yii;
use yii\helpers\Json;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;

class EmailParseController extends Controller
{
    public $layout = 'column2';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => [
                            'index',
                            'view',
                            'html',
                            'result-data',
                            'validate-data',
                            'order-package-data',
                            'parse-letter',
                            'parse-letter-old',
                            'view-html'
                        ],
                        'roles' => ['?',],
                        'allow' => true,
                    ],
                ],
            ],
        ];
    }

    private function getConfigMacys()
    {
        return [
            'from'    => 'CustomerService@oes.macys.com',
            'to'      => 'imaplib@shopfans.ru',
            'date'    => 'Thu, 27 Feb 2020 14:52:40 +0300',
            'subject' => 'test_message',
            'body'    => '',
            'raw'     => '',
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'navItems' => [
                'label' => 'label',
                'url' => 'url',
            ],
//            'navItems' => $this->getNavItems(),
            'filter' => EmailParseFilter::ensure(),
        ]);
    }


    /**
     * Displays a single Email model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        $nextIdByMarket = EmailParse::find()
            ->select('id')
            ->where(['market_id' => $model->market_id])
            ->andWhere(['>', 'id', $model->id])
            ->asArray()
            ->one();

        $lastId = EmailParse::find()
            ->select('id')
            ->asArray()
            ->last()
            ->one();

        $massOrderId = (int) MassOrderDiscount::find()
            ->select('id')
            ->where(['external_number' => $model->external_order_id])
            ->scalar();

        $productsArray = OrderProduct::find()
            ->innerJoinWith('orderPackage')
            ->where(['op.external_order_id' => $model->external_order_id])
            ->all();

        $productCountByStatus = [
            'with_tracking' => 0,
            'other_status'  => 0
        ];

        foreach ($productsArray as $product) {
            $status = $product->orderPackage->status;
            $quantity = $product->quantity;

            if ($status == 8) {
                $productCountByStatus['with_tracking'] += $quantity;
            } else {
                $productCountByStatus['other_status'] += $quantity;
            }
        }

        $emlResultArray = [];

        //обрабатываем web
        if ($model->web) {
            $emlArray = Json::decode($model->eml);

            $emlResultArray = [
                'with_tracking' => [
                    'total_items' => 0,
                    'total_qty' => 0,
                    'details' => []
                ],
                'no_tracking' => [
                    'total_items' => 0,
                    'total_qty' => 0,
                    'details' => []
                ]
            ];

            foreach ($emlArray as $entry) {
                $tracking = $entry['tracking'] ?? 'no tracking';
                $itemSum = 0;
                $quantitySum = 0;

                foreach ($entry['items'] as $item) {
                    $itemSum ++;
                    $quantitySum += $item['quantity'];
                }

                if ($tracking === 'no tracking') {
                    $key = 'no_tracking';
                } else {
                    $key = 'with_tracking';
                }

                $emlResultArray[$key]['total_items'] += $itemSum;
                $emlResultArray[$key]['total_qty'] += $quantitySum;

                $emlResultArray[$key]['details'][$tracking] = [
                    'items' => $itemSum,
                    'qty' => $quantitySum
                ];
            }
        }

        return $this->render('view', [
            'model' => $model,
            'lastId' => $lastId['id'] ?? null,
            'nextIdByMarket' => $nextIdByMarket['id'] ?? null,
            'productCountByStatus' => $productCountByStatus,
            'emlResultArray' => $emlResultArray,
            'massOrderId' => $massOrderId
        ]);
    }

    public function actionHtml($id)
    {
        $model = $this->findModel($id);
        $mailParser = new MailMimeParser();
        //Получаем оригинал письма в формате eml
        $message = $mailParser->parse($model->eml);
        //Получаем преобразованный код письма в html
        $htmlContent = $message->getHtmlContent();

        return $htmlContent;
    }

    public function actionResultData($id)
    {
        $model = $this->findModel($id);
        return $this->renderAjax('vd', [
            'data' => $model->result_data,
        ]);
    }

    public function actionValidateData($id)
    {
        $model = $this->findModel($id);
        return $this->renderAjax('vd', [
            'data' => $model->validate_data,
        ]);
    }

    public function actionOrderPackageData($id)
    {
        $model = $this->findModel($id);
        return $this->renderAjax('vd', [
            'data' => $model->order_package_data,
        ]);
    }

    public function actionParseLetterOld()
    {
        $raw = Yii::$app->request->getBodyParam('raw');
        if ($raw) {
            $emailMessage = (new MailMimeParser())->parse($raw);
            $data = ['body' => '',
                'from' => $emailMessage->getHeaderValue('from'),
                'to' => $emailMessage->getHeaderValue('to'),
                'date' => $emailMessage->getHeaderValue('date'),
                'subject' => $emailMessage->getHeaderValue('subject'),
                'web' => true,
                'raw' => $raw,
            ];

            Yii::info(__METHOD__ . PHP_EOL
                . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ParseEmail::class . '::search');

            $url = 'https://app.usmall.ru/api/email-parse';

            $data = http_build_query($data);

            $curl = curl_init(); // создаем экземпляр curl
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_VERBOSE, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_URL, $url);

            $result = curl_exec($curl);

            Yii::info($result, ParseEmail::class . '::search');
        }

        return $this->render('parse-letter');
    }

    public function actionParseLetter()
    {
        $model = new \app\api\filters\EmailParseFilter($this->getConfigMacys());

        if (Yii::$app->request->isPost) {
            $model->raw = (string)Yii::$app->request->post('raw', '');

            $message = Message::from($model->raw);
            $from    = $message->getHeader('From');
            $subject = $message->getHeader('Subject');
            $date    = $message->getHeader('Date');

            $model->from    = (string)($from ? $from->getValue() : '');
            $model->subject = (string)($subject ? $subject->getValue() : '');
            $model->date    = (string)($date ? $date->getValue() : '');
        }

        return $this->render('email-parse', ['model' => $model]);
    }

    public function actionViewHtml()
    {
        $model = new \app\api\filters\EmailParseFilter($this->getConfigMacys());

        if (Yii::$app->request->isPost) {
            $model->raw = (string)Yii::$app->request->post('raw', '');

            $message = (new MailMimeParser())->parse($model->raw);
            $htmlContent = $message->getHtmlContent();

//            var_dump(Yii::$app->request->post('subject', ''));

            echo $htmlContent;
            die();
        }
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    private function getNavItems(): array
    {
        $action = 'index?market_id=';
        $markets = \Yii::$app->markets->all();
        $items = [];
        foreach ($markets as $market) {
            $items[$market->id] = ['label' => $market->name, 'url' => $action . $market->id];
        }
        return $items;
    }

    /**
     * @param $id
     * @return EmailParse|array|null
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        $model = $model = EmailParse::find()
            ->byId($id)
            ->one();
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
