<?php
/** @var \yii\web\View $this
 * @var $navItems array
 * @var \app\records\MassOrderDiscountQuery $query
 * @var \app\web\filters\MassOrderDiscountFilter $filter
 * @var $queryParams array
 */

use app\records\User;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;

$this->title = "Mass Order History";
$this->params['breadcrumbs'][] = $this->title;
?>
<h3><?= $this->title ?></h3>
<div class="mass-order-discount-list">
    <?= $this->render('_header', compact('navItems')) ?>
    <?= GridView::widget([
        'dataProvider' => new ActiveDataProvider([
            'query' => $filter->search($queryParams),
            'sort' => [
                'defaultOrder' => [
                    'id' => SORT_DESC,
                ]
            ],
        ]),
        'rowOptions' => function ($record) {
            if ($record->checkAwaitingArriving()) {
                return ['class' => 'success'];
            }
        },
        'columns' => [
            'id',
            [
                'attribute' => 'market_id',
                'label' => 'Маркет',
                'value' => function (\app\records\MassOrderDiscount $record) use ($navItems) {
                    return $navItems[$record->market_id]['label'];
                }
            ],
            'external_number',
            'discount',
            'delivery_cost_usd',
//            [
//                'attribute' => 'user_id',
//                'label' => 'Пользователь',
//                'value' => function ($record) {
//                    $user = User::findOne($record->user_id);
//                    if (!empty($user)) {
//                        return $user->username;
//                    }
//                    return $record->user_id;
//                },
//            ],
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
                'template' => '{view} {update}',
                'visibleButtons' => [
                    'update' => Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT),
                    'view' => true
                ],
            ],
        ],
    ]) ?>
</div>

<?php $this->beginBlock('sidebar') ?>
    <?= $this->render('_filter', compact('filter')); ?>
<?php $this->endBlock() ?>

