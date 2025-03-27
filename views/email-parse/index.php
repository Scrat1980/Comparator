<?php
/**
 * @var View $this
 * @var EmailParseFilter $filter
 * @var $navItems array[]
 */

use app\records\EmailParse;
use app\Access;
use app\models\EmailParseFilter;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\web\View;

$this->title = 'Email-parse';
$this->params['breadcrumbs'][] = $this->title;
$emailParse = EmailParse::find()->last()->one();

?>
<div class="email-parse-index">
    <?= Nav::widget([
        'options' => ['class' => 'nav-tabs'],
        'items' => $navItems,
    ]) ?>
    <?= GridView::widget([
        'dataProvider' => new ActiveDataProvider([
            'query' => $filter->search(),
            'sort' => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC,
                ],
            ],
        ]),
        'rowOptions' => function ($record) {
            if (isset($record->web)) {
                return ['class' => 'info'];
            }
        },
        'columns' => [
            'id',
            'tracking_number',
            'external_order_id',
            [
                'attribute' => 'market_id',
                'label' => 'Market',
                'format' => 'raw',
                'value' => function ($record) {
                    return 66;
//                    return \Yii::$app->markets->one($record->market_id)->name;
                },
            ],
            [
                'attribute' => 'created_at',
                'label' => 'Обработано',
                'format' => 'raw',
                'value' => function ($record) {
                    return \Yii::$app->formatter->asDatetime($record->created_at, 'php:d-m-Y H:i:s');
                },
            ],
            [
                'class' => ActionColumn::class,
                'template' => '{view}',
                'visibleButtons' => [
                    'view' => true,
                ],
            ],

        ],
    ]) ?>
</div>

<?php $this->beginBlock('sidebar') ?>
<div class="alert"><?= Html::a('Парсинг письма', '/email-parse/parse-letter', ['class' => 'btn btn-primary'])?></div>
<div class="alert alert-warning">
    <h4>Последний ЧИХ!</h4>
    <p>
        Последнее письмо было обработано <?= isset($emailParse) ? \Yii::$app->formatter->asDatetime
        ($emailParse->created_at, 'php:d-m-Y H:i:s') : '' ?>
    </p>
</div>
<?= $this->render('_filter', compact('filter')) ?>
<?php $this->endBlock() ?>
