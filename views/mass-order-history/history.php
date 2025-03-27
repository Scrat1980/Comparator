<?php
/**
 * @var MassOrderProductQuery $query
 * @var $queryParams array
 * @var $navItems array
 * @var $marketId int
 * @var $marketName string
 * @var MassOrderDiscountFilter $filter
 * @var View $this
 */

use app\records\MassOrderProductQuery;
use app\web\filters\MassOrderDiscountFilter;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use \yii\bootstrap5\Html;
use yii\web\View;

$this->title = $marketName;
?>

<?= $this->render('_header', compact('navItems')) ?>
<h3><?= $marketName ?></h3>

<div class="mass-order-history">
    <?= GridView::widget([
        'dataProvider' => new ActiveDataProvider([
            'query' => $filter->search($queryParams)
        ]),
        'rowOptions' => function ($record) {
            if ($record->checkAwaitingArriving()) {
                return ['class' => 'success'];
            }
        },
        'columns' => [
            'id',
            'external_number',
            'discount',
            'delivery_cost_usd',
            [
                'attribute' => 'user_id',
                'label' => 'Пользователь',
                'value' => function ($record) {
                    $user = \app\records\User::findOne($record->user_id);
                    if (!empty($user)) {
                        return $user->username;
                    }
                    return $record->user_id;
                },
            ],
            [
                'attribute' => 'account',
            ],
            [
                'attribute' => 'count',
                'label' => 'Кол-во товаров',
                'value' => function (\app\records\MassOrderDiscount $record) {
                    $countOrderProduct = $record->getMassOrderProduct()->count();
                    $quantityOrderProduct = $record->getCountProducts();
                    return $countOrderProduct . ' / ' . $quantityOrderProduct;
                }
            ],
            [
                'attribute' => 'totalPrice',
                'label' => 'Сумма',
                'value' => function (\app\records\MassOrderDiscount $record) {
                    return $record->getTotalPrice();
                }
            ],
            'created_at:dateTime',
            [
                'class' => \yii\grid\ActionColumn::class,
                'template' => '{view} {update} {audit} {delete}',
                'visibleButtons' => [
                    'update' => Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT),
                    'view' => true,
                    'audit' => Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT),
                    'delete' => Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT),
                ],
                'buttons' => [
                    'audit' => function ($url, $record) {
                        return
                            Html::a(Html::icon('time'),

                                ['/audit/index', 'entity' => 'MassOrderDiscount', 'entity_id' => $record->id], [
                                    'title' => 'View History',
                                    'target' => '_blank',
                                ]);
                    }
                ]
            ],
        ],
    ]) ?>
</div>
<?php $this->beginBlock('sidebar') ?>

    <div class="alert">
        <?= Html::a(
            'Список парсингов писем',
            '/email-parse/index?market_id=' . $marketId,
            [
                'class' => 'btn btn-primary',
                'target' => '_blank',
            ]
        )
        ?>
    </div>
    <?= $this->render('_filter', compact('filter')) ?>

<?php $this->endBlock() ?>
