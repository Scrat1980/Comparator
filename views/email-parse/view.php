<?php


use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\web\View;
use yii\widgets\DetailView;
use app\records\EmailParse;

/* @var $this yii\web\View */
/* @var $model EmailParse */
/* @var $lastId integer|null */
/* @var $nextIdByMarket integer|null */
/* @var $productCountByStatus array|null */
/* @var $emlResultArray array|null */
/* @var $massOrderId integer|null */

$this->title = 'Email Parse';
$this->params['breadcrumbs'][] = $this->title;
?>

    <div style="margin-bottom: 20px"><?= \yii\bootstrap5\Html::a('К списку', '/email-parse/index', ['class' => 'btn btn-info']) ?>
        <?php if ($lastId !== null && $model->id < $lastId): ?>
            <?= \yii\bootstrap5\Html::a('Следующий', '/email-parse/' . ($model->id + 1), ['class' => 'btn btn-info']) ?>
        <?php endif; ?>
        <?php if ($nextIdByMarket !== null): ?>
            <?= \yii\bootstrap5\Html::a('Следующий по маркету', '/email-parse/' . $nextIdByMarket, ['class' => 'btn btn-info']) ?>
        <?php endif; ?>
    </div>

    <div class="email-parse-view">
        <div class="row">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    'external_order_id',
                    [
                        'attribute' => 'tracking_number',
                        'label' => 'Tracking Number',
                        'value' => function (EmailParse $model) {
                            return $model->getTrackingNumber();
                        },
                    ],
                    [
                        'attribute' => 'market_id',
                        'label' => 'Market Name',
                        'value' => function (EmailParse $model) {
                            return Yii::$app->markets->one($model->market_id)->name;
                        },
                    ],
                    [
                        'attribute' => 'created_at',
                        'label' => 'Дата создания',
                        'value' => function (EmailParse $model) {
                            return isset($model->created_at) ? \Yii::$app->formatter->asDatetime
                            ($model->created_at, 'php:d-m-Y H:i:s') : '';
                        }
                    ],
                    [
                        'attribute' => 'total_result_data',
                        'label' => 'Считано из письма',
                        'value' => function (EmailParse $model) {
                            if ($model->web) {
                                return $model->getTotalResultData() . ' - ' . $model->getTotalResultDataWeb();
                            } else {
                                return $model->getTotalResultData();
                            }
                        },
                    ],
                    [
                        'attribute' => 'total_validate_data',
                        'label' => 'Найдено товаров',
                        'value' => function (EmailParse $model) {
                            return $model->getTotalValidateData();
                        },
                    ],
                    [
                        'attribute' => 'total_order_package_data',
                        'label' => 'Сохранено в заказах',
                        'value' => function (EmailParse $model) {
                            return $model->getTotalOrderProductData();
                        },
                    ],
                    [
                        'attribute' => 'count_products_with_track',
                        'format' => 'raw',
                        'label' => ($massOrderId !== 0) ? '<a href="/mass-order-history/view-actual/' . $massOrderId . '">Mass Order History</a><br>Товары с треком' : 'Mass Order History<br>Товары с треком',
                        'value' => function () use ($productCountByStatus) {
                            $withTrackingQuantity = $productCountByStatus['with_tracking'];
                            $otherStatusQuantity = $productCountByStatus['other_status'];
                            $totalQuantity = $withTrackingQuantity + $otherStatusQuantity;

                            return "$withTrackingQuantity из $totalQuantity";
                        }
                    ],
                    [
                        'attribute' => 'eml_result_with_track',
                        'format' => 'raw',
                        'label' => 'Web<br>Товары с треком',
                        'value' => function () use ($emlResultArray) {
                            $withTrackingQty = $emlResultArray['with_tracking']['total_qty'] ?? 0;
                            $otherStatusQty = $emlResultArray['no_tracking']['total_qty'] ?? 0;
                            $totalQty = $withTrackingQty + $otherStatusQty;

                            return "$withTrackingQty из $totalQty";
                        },
                        'visible' => $model->web
                    ],
                    [
                        'attribute' => 'split_package',
                        'label' => 'Split Package',
                        'format' => 'raw',
                        'value' => function (EmailParse $model) {
                            $splitPackages = $model->getSplitPackageData();
                            $button = '';
                            foreach ($splitPackages as $key => $splitPackage) {
                                $button .= ' ' . Html::a($key,
                                        ['/split-package/' . $splitPackage->id],
                                        ['class' => 'btn btn-info']);
                            }
                            return $button;
                        },
                    ],
                    [
                        'attribute' => 'action_button',
                        'value' => function (EmailParse $model) {
                            return 'потом будут действия/кнопки';
                        },
                    ],

                ],
            ]) ?>
        </div>
        <?=Html::button('result-data', ['class' => 'js-result-data-button btn btn-success'])?>
        <div class="row js-result-data" data-email-parse-id="<?=$model->id?>">
        </div>
        <?=Html::button('validate-data', ['class' => 'js-validate-data-button btn btn-success'])?>
        <div class="row js-validate-data" data-email-parse-id="<?=$model->id?>">
        </div>
        <?=Html::button('order-package-data', ['class' => 'js-order-package-data-button btn btn-success'])?>
        <div class="row js-order-package-data" data-email-parse-id="<?=$model->id?>">
        </div>
        <br><br>
        <div class="row">
            <?php if ($model->web) {
                print "<pre>";
                print_r(\yii\helpers\Json::decode($model->eml));
                print "</pre>";
            } else {
                echo '<iframe
                width="100%" height="1200px" src="/email-parse/html/' . $model->id . '" id="js-request-copy-letter" allow="clipboard-read; clipboard-write"></iframe>';
            } ?>
        </div>
    </div>

    <textarea id="eml-text" style="display: none;"><?= $model->eml ?></textarea>

    <div class="flex right" style="margin-top: 20px;">
        <?= Html::button(
            'Скопировать исходник',
            ['id' => 'js-button-copy-letter', 'class' => 'btn btn-info']
        )
        ?>
    </div>


<?php
$script = <<<JS
    $('#js-button-copy-letter').on('click', function() {
        var emlText = $('#eml-text').val();

        navigator.clipboard.writeText(emlText).then(function() {
        $('#js-button-copy-letter').text('Eml скопирован');

        setTimeout(function() {
            $('#js-button-copy-letter').text('Скопировать исходник');
        }, 2000);
        }).catch(function() {
            $('#js-button-copy-letter').text('Ошибка копирования eml');
        });
    });

    $('.js-result-data-button').on('click', function(e) {
        e.preventDefault();
        var emailParseId = $('.js-result-data').data('emailParseId');
        $('.js-result-data').load( '/email-parse/result-data/' + emailParseId);
    });
    $('.js-validate-data-button').on('click', function(e) {
        e.preventDefault();
        var emailParseId = $('.js-validate-data').data('emailParseId');
        $('.js-validate-data').load( '/email-parse/validate-data/' + emailParseId);
    });
    $('.js-order-package-data-button').on('click', function(e) {
        e.preventDefault();
        var emailParseId = $('.js-order-package-data').data('emailParseId');
        $('.js-order-package-data').load( '/email-parse/order-package-data/' + emailParseId);
    });

JS;

$this->registerJs($script, View::POS_READY);

