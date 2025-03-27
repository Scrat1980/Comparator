<?php
/**
 * @var \app\records\MassOrderProductQuery $query
 */

use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\bootstrap5\Html;

?>

<div class="mass-order-product-view">
    <?= GridView::widget([
        'dataProvider' => new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
            'sort' => [
                'defaultOrder' => [
                    'id' => SORT_DESC,
                ]
            ],
        ]),
        'columns' => [
            [
                'class' => 'yii\grid\SerialColumn',
            ],
            [
                'label' => 'Order',
                'content' => function (\app\records\MassOrderProduct $record) {
                    return $record->orderProduct->order_id .
                        '<div class="alert alert-my alert-' . $record->orderProduct->order->getClassAlert() .
                        '" role="alert">' . $record->orderProduct->order->getTextStatus() . '</div>';
                }
            ],
            [
                'label' => 'Package',
                'content' => function (\app\records\MassOrderProduct $record) {
                    $text =  $record->orderProduct->orderPackage->id .
                        '<div class="alert alert-my alert-' . $record->orderProduct->orderPackage->getClassAlertMassOrderHistory() .
                        '" role="alert">' . $record->orderProduct->orderPackage->getTextStatus() . '</div>';

                    if ($record->orderProduct->orderPackage->tracking_number) {
                        $link = $record->orderProduct->orderPackage->tracking_number;
                        if (strpos($record->orderProduct->orderPackage->tracking_number, 'DP') !== false) {
                            $splitPackage = \app\records\SplitPackage::find()->bySfPackageId($record->orderProduct->orderPackage->sf_package_id)->one();
                            if (!empty($splitPackage)) {
                                $link = Html::a($record->orderProduct->orderPackage->tracking_number, ['/split-package/view', 'id' => $splitPackage->id],
                                    [
                                        'target' => '_blank',
                                        'style' => 'color: red;'
                                    ]
                                );
                            }
                        }
                        $text = $text . '  ' . $link;
                    }

                    return $text;
                }
            ],
            [
                'label' => 'Product',
                'content' => function (\app\records\MassOrderProduct $record) {
                    return $record->orderProduct->id .
                        '<div class="alert alert-my alert-' . $record->orderProduct->getClassAlert() .
                        '" role="alert">' . $record->orderProduct->getTextStatus() . '</div>';
                }
            ],
//            [
//                'attribute' => 'sp',
//                'label' => 'СП',
//                'content' => function (\app\records\MassOrderProduct $record) {
//                    $isSp = '';
//                    if ($record->order->customer->isSp()) {
//                        $isSp = Html::icon('check');
//                    }
//                    if ($record->orderProduct->price_cost_usd != $record->orderProduct->price_buyout_usd) {
//                        if ($record->orderProduct->price_buyout_usd > $record->orderProduct->price_cost_usd) {
//                            $classPrice = 'new-price-large';
//                        } else {
//                            $classPrice = 'new-price-small';
//                        }
//                        $addPriceBuyout = '<br><span class="' . $classPrice . '">' . $record->orderProduct->price_buyout_usd . '</span>';
//                        return $isSp . '&nbsp;&nbsp;' . $record->orderProduct->price_cost_usd . '&nbsp;&nbsp;' . $addPriceBuyout;
//                    }
//                    return $isSp;
//                }
//            ],
            [
                'attribute' => 'productName',
                'label' => 'Название продукта',
                'content' => function (\app\records\MassOrderProduct $record) {
                    return $record->orderProduct->product->origin_name .
                        '<div class="size-color">' . $record->orderProduct->productVariant->description . '</div>';
                }
            ],
            'quantity',
            [
                'attribute' => 'totalPrice',
                'label' => 'Сумма',
                'value' => function (\app\records\MassOrderProduct $record) {
                    return $record->getTotalPrice();
                }
            ],
            'created_at:dateTime',
            [
                'attribute' => 'order_id',
                'label' => '',
                'format' => 'raw',
                'value' => function ($record) {
                    return Html::a(
                        'Посмотреть заказ',
                        '/order/' . $record->order_id,
                        [
                            'class' => 'btn btn-info'
                        ]
                    );
                },
            ],
            [
                'class' => \yii\grid\ActionColumn::class,
                'template' => '{update-product} {audit} {delete-product}',
                'visibleButtons' => [
                    'update-product' => Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT),
                    'delete-product' => Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT),
                    'audit' => Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT),
                ],
                'buttons' => [
                    'audit' => function ($url, $record) {
                        return
                            Html::a(Html::icon('time'),

                                ['/audit/index', 'entity' => 'MassOrderProduct', 'entity_id' => $record->id], [
                                    'title' => 'View History',
                                    'target' => '_blank',
                                ]);
                    },
                    'update-product' => function ($url, $record) {
                        return
                            Html::a(Html::icon('edit'),

                                ['update-product', 'id' => $record->id], [
                                    'title' => 'Edit',
                                    'target' => '_blank',
                                ]);
                    },
                    'delete-product' => function ($url, $record) {
                        return
                            Html::a(Html::icon('trash'),
                                ['delete-product', 'id' => $record->id], [
                                    'title' => 'Delete',
                                ]);
                    }
                ]
            ],
        ],
    ]) ?>
</div>

<?php

$css = <<<CSS
    .alert-my {
        padding: 5px;
        margin-bottom: 2px;
        font-size: small;
    }
    .new-price-large {
        color: #ff2351;
        font-weight: bold;
    }
    .new-price-small {
        color: #20b214;
        font-weight: bold;
    }
CSS;

$this->registerCss($css);


