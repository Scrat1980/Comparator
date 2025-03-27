<?php
/**
 * @var \app\records\OrderProductQuery $query
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
                    'content' => function (\app\records\OrderProduct $record) {
                        return $record->order_id .
                            '<div class="alert alert-my alert-' . $record->order->getClassAlert() .
                            '" role="alert">' . $record->order->getTextStatus() . '</div>';
                    }
                ],
                [
                    'label' => 'Package',
                    'content' => function (\app\records\OrderProduct $record) {
                        $text =  $record->orderPackage->id .
                            '<div class="alert alert-my alert-' . $record->orderPackage->getClassAlertMassOrderHistory() .
                            '" role="alert">' . $record->orderPackage->getTextStatus() . '</div>';

                        if ($record->orderPackage->tracking_number) {
                            $link = $record->orderPackage->tracking_number;
                            if (strpos($record->orderPackage->tracking_number, 'DP') !== false) {
                                $splitPackage = \app\records\SplitPackage::find()->bySfPackageId($record->orderPackage->sf_package_id)->one();
                                if (!empty($splitPackage)) {
                                    $link = Html::a($record->orderPackage->tracking_number, ['/split-package/view', 'id' => $splitPackage->id],
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
                    'content' => function (\app\records\OrderProduct $record) {
                        return $record->id .
                            '<div class="alert alert-my alert-' . $record->getClassAlert() .
                            '" role="alert">' . $record->getTextStatus() . '</div>';
                    }
                ],
//            [
//                'attribute' => 'sp',
//                'label' => 'СП',
//                'content' => function (\app\records\OrderProduct $record) {
//                    $isSp = '';
//                    if ($record->order->customer->isSp()) {
//                        $isSp = Html::icon('check');
//                    }
//                    if ($record->price_cost_usd != $record->price_buyout_usd) {
//                        if ($record->price_buyout_usd > $record->price_cost_usd) {
//                            $classPrice = 'new-price-large';
//                        } else {
//                            $classPrice = 'new-price-small';
//                        }
//                        $addPriceBuyout = '<br><span class="' . $classPrice . '">' . $record->price_buyout_usd . '</span>';
//                        return $isSp . '&nbsp;&nbsp;' . $record->price_cost_usd . '&nbsp;&nbsp;' . $addPriceBuyout;
//                    }
//                    return $isSp;
//                }
//            ],
                [
                    'attribute' => 'productName',
                    'label' => 'Название продукта',
                    'content' => function (\app\records\OrderProduct $record) {
                        return $record->product->origin_name .
                            '<div class="size-color">' . $record->productVariant->description . '</div>';
                    }
                ],
                'quantity',
//                'endredeem_at:dateTime',
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
