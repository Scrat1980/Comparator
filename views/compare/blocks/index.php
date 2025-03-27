<?php
/**
 * @var \app\web\ParseChecker\Comparator\app\web\Comparator\ComparatorForm $model
 * @var array $values
 * @var array $markets
 */

use app\web\Comparator\ComparatorForm;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;

$this->title = 'Bulk getStructure()/getProductVariantExecute() checker';
$this->params['breadcrumbs'][] = $this->title;

$block['model'] = $model;

$block_1['radio']['value'] = $values[0];
$block_1['radio']['id'] = 'type1';
$block_1['text'][1] = [
    'field' => 'url',
    'label' => 'Url',
    'placeholder' => 'https://app.usmall.ru/email-parse/1052027',
];

$block_2['radio']['value'] = $values[1];
$block_2['text'][0] = [
    'field' => 'marketId',
    'label' => 'Market',
    'placeholder' => '',
    'markets' => $markets,
];
$block_2['text'][1] = [
    'field' => 'quantity',
    'label' => 'Quantity',
    'placeholder' => '2',
];

$block_3['radio']['value'] = $values[2];
$block_3['text'][1] = [
    'field' => 'orderNumber',
    'label' => 'Order number',
    'placeholder' => '54799931113',
];

$block_4['radio']['value'] = $values[3] ;
$block_4['text'][1] = [
    'field' => 'trackingNumber',
    'label' => 'Tracking number',
    'placeholder' => '1Z877F4Y1224887532',
];

$block_5['radio']['value'] = $values[4] ;
$block_5['radio']['id'] = 'type5';
$block_5['text'][1] = [
    'field' => 'folder',
    'label' => 'From folder',
    'placeholder' => '/app/runtime/input/',
    'value' => '/app/runtime/input/',
];
?>
<div class="col-sm-6">
    <h3 style="margin: 0;">Get letters by:</h3>
    <?php $form = ActiveForm::begin(); ?>
    <?php $block['form'] = $form; ?>
    
    <fieldset>
        <?= $this->render('_block', ['block' => array_merge($block, $block_1)]) ?>
<!--        --><?php //= $this->render('_block', ['block' => array_merge($block, $block_2)]) ?>
<!--        --><?php //unset($block['text'][0]); ?>
    
<!--        --><?php //= $this->render('_block', ['block' => array_merge($block, $block_3)]) ?>
<!--        --><?php //= $this->render('_block', ['block' => array_merge($block, $block_4)]) ?>
        <?= $this->render('_block', ['block' => array_merge($block, $block_5)]) ?>
        <div style="border: 1px #ccc solid; border-radius: 5px; margin: 5px; padding: 5px;">

            <?php
            $provider = new ActiveDataProvider([
                'query' => (new \yii\db\Query())
                    ->select([
                        'count(*) AS count',
                        'market_id'
                    ])
                    ->from('zz_cache')
                    ->groupBy(['order_id'])
//                ->column()
                ,
                'pagination' =>
//                [
                    false
//                'pageSize' => 5,
//            ],
            ]);
            ?>
            <?= '<br><br>' ?>
            <?= GridView::widget([
                'dataProvider' => $provider,
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn',],
//                'market_id' => 'market_id',
                    'count',
                    [
                        'class' => 'yii\grid\CheckboxColumn',
                        'header' => 'Mass action',
                    ],
                    [
                        'class' => ActionColumn::class,
                        'header' => 'Action'
                        // you may configure additional properties here
                    ],
                ],
            ]) ?>


        </div>
    </fieldset>
    <?= Html::button('Submit (Ctrl+Enter)', [
        'class' => 'btn btn-primary',
        'id' => 'submit',
    ]); ?>
    
    <?php ActiveForm::end(); ?>


</div>
<div class="col-sm-6">
    <h3 style="margin: 0;">Status:</h3>
    <div style="border: 1px #ccc solid; border-radius: 5px; margin: 5px; padding: 5px;">
        Ready
    </div>
    <h3 style="margin: 0;">Results:</h3>
    <div
        style="
            border: 1px #ccc solid;
            border-radius: 5px;
            margin: 5px;
            padding: 5px;
            height: 40vh;
            overflow-y:scroll;
        "
        id="results"
    >
    </div>
</div>
<?php
$this->registerJsFile(
//    '@web/js/structureVariantsChecker.js',
    '@web/js/comparator.js',
    ['position' => View::POS_END,]
);
$this->registerJsFile('@web/js/comparator/Form.submit.js', ['position' => View::POS_END,]);
$this->registerJsFile('@web/js/comparator/Form.initialize.js', ['position' => View::POS_END,]);
$this->registerJsFile('@web/js/comparator/App.pinger.js', ['position' => View::POS_END,]);
