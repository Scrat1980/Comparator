<?php
/** @var View $this
 * @var array $navItems
 * @var MassOrderDiscountQuery $query
 * @var MassOrderDiscountFilter $filter
 * @var array $queryParams
 */

use app\Access;
use app\records\MassOrderDiscountQuery;
use app\records\User;
use app\models\MassOrderDiscountFilter;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\web\View;

$this->title = "Mass Order History";
$this->params['breadcrumbs'][] = $this->title;
?>
<h3><?= $this->title ?></h3>
<div class="mass-order-discount-list" style="overflow-x: scroll;">
    <?= $this->render('_header', compact('navItems')) ?>
    <?php try {
        echo GridView::widget([
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
                    'class' => ActionColumn::class,
                    'template' => '{view} {update}',
                    'visibleButtons' => [
                        'update' => Yii::$app->user->can(Access::MASS_ORDER_HISTORY_EDIT),
                        'view' => true
                    ],
                ],
            ],
        ]);
    } catch (Throwable $e) {
        echo 'Grid view error: ' . $e->getMessage();
    } ?>
</div>

<?php $this->beginBlock('sidebar') ?>
    <?= $this->render('_filter', compact('filter')); ?>
<?php $this->endBlock() ?>

