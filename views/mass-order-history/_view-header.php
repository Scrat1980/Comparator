<?php
/**
* @var \app\records\MassOrderDiscount $model
* @var string $marketName
* @var int $countOrderProductWithTrack
*/

use shopfans\widgets\LoadingOverlayAsset;

?>
<?php if(Yii::$app->user->can(\app\Access::ORDER_DEVELOPER)): ?>
<div>
    <?= \yii\bootstrap5\Html::a('Update', ['/mass-order-history/update', 'id' => $model->id], ['class' => 'btn btn-success']) ?>
</div>
<?php endif; ?>
<div>
<?= \yii\widgets\DetailView::widget([
    'model' => $model,
    'attributes' => [
        'id',
        [
            'attribute' => 'market_id',
            'label' => 'Маркет',
            'value' => function ($record) use ($marketName) {
                return $marketName;
            }
        ],
        'external_number',
        [
            'attribute' => 'tracking_number',
            'label' => 'Номер отслеживания(трек номер)',
            'format' => 'raw',
            'value' => function (\app\records\MassOrderDiscount $model) {
                return '<div class="change-tracking-number"> <input id="js-tracking-number"/>' .
                    \yii\bootstrap5\Html::button('Внести трек-номер', ['class' => 'btn btn-info', 'id' => 'js-change-tracking-number']) . '</div>';
            }
        ],
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
        'account',
        'created_at:dateTime',
        [
            'attribute' => 'count',
            'label' => 'Количество товаров с треком',
            'value' => function () use ($countOrderProductWithTrack) {
                return $countOrderProductWithTrack;
            }
        ]
    ],
]) ?>

<div> <p>
<?= \yii\bootstrap5\Html::a('Вернуть в масс-ордер (актуальное, все товары без треков)', ['/mass-order-history/return-to-redeem-order', 'id' => $model->id], ['class' => 'btn btn-danger']) ?>
<?= \yii\bootstrap5\Html::a('Вернуть в масс-ордер (актуальное)', ['/mass-order-history/return-to-order', 'id' => $model->id], ['class' => 'btn btn-danger']) ?>
<?= \yii\bootstrap5\Html::a('Вернуть в масс-ордер (separated history)', ['/mass-order-history/return-to-separated-order', 'id' => $model->id], ['class' => 'btn btn-danger']) ?>
    </p> </div>
<?php //if (Yii::$app->user->can(\app\Access::MASS_ORDER_HISTORY_EDIT)): ?>
<!--    <div class="promocode-section-index" style="margin-top: 20px">-->
<!--        <p><input id='js-input-promocode' name="percent" type="text" data-modid ="--><?php //=$model->id?><!--" data-marketid ="--><?php //=$model->market_id?><!--"></p>-->
<!--        <p>-->
<!--            <button id="js-promocode-section" class="btn btn-info">Внести изменения по цене (%)</button>-->
<!--        </p>-->
<!--    </div>-->
<?php //endif; ?>

<div id='js-package-data' data-modid ="<?=$model->id?>" data-marketid ="<?=$model->market_id?>"></div>

    <?= \app\web\widgets\Nav::widget([
        'options' => ['class' => 'nav-tabs'],
        'items' => [
            [
                'label' => \app\web\User::t('app', 'Actual'),
                'url' => ['view-actual', 'id' => $model->id],
                'active' => explode('/', Yii::$app->requestedRoute)[1] === 'view-actual',
            ],
            [
                'label' => \app\web\User::t('app', 'Separated History'),
                'url' => ['view-separated-history', 'id' => $model->id],
                'active' => explode('/', Yii::$app->requestedRoute)[1] === 'view-separated-history',
            ],
            [
                'label' => \app\web\User::t('app', 'History'),
                'url' => ['view-history', 'id' => $model->id],
                'active' => explode('/', Yii::$app->requestedRoute)[1] === 'view-history',
            ],
        ],
    ]) ?>

</div>
<?php
LoadingOverlayAsset::register($this);
$script = <<<JS
$('#js-promocode-section').on('click', function (e){
    $.LoadingOverlay("show");
    var percent = $('#js-input-promocode').val();
    var massOrderDiscountId = $('#js-input-promocode').data('modid');
    var marketId = $('#js-input-promocode').data('marketid');

    $.ajax({
        'type' : 'POST',
        'url' : '/mass-order-history/change-price-by-promocode/' ,
        'dataType' : 'json',
        'data' : {
            'percent' : percent,
            'massOrderDiscountId' : massOrderDiscountId,
            'marketId' : marketId
        },
        'success' : function(data){
            $.LoadingOverlay("hide");
            if (data.error) {
                alert(data.error);
            }
            if (data.exception){
                alert(data.exception.code + ' ' + data.exception.message);
            }
            if(data.response.code == 200 || data.response.code == 0){
                window.location.reload();
            }
        },
        'error' : function(request, status, error){
            $.LoadingOverlay("hide");
            alert('error:' + data.exception.code + ' ' + data.exception.message);
            console.log('error:' + error);
        }
    });
});

$('#js-change-tracking-number').on('click', function (e){
    $.LoadingOverlay("show");
    var trackingNumber = $('#js-tracking-number').val();
    var massOrderDiscountId = $('#js-package-data').data('modid');
    var marketId = $('#js-package-data').data('marketid');
    let messageArr = [];
            $.ajax({
            'type' : 'POST',
            'url' : '/mass-order-history/change-tracking-number/' ,
            'dataType' : 'json',
            'data' : {
                'trackingNumber' : trackingNumber,
                'massOrderDiscountId' : massOrderDiscountId,
                'marketId' : marketId
            },
            'success' : function(data){
                if (data.error) {
                    messageArr.push(data.error);
                    $('#js-package-data').html(data.error);
                }
                if (data.exception){
                    messageArr.push(data.exception.code + ' ' + data.exception.message);
                }
                if(!('error' in data)){
                    window.location.reload();
                }
                $.LoadingOverlay("hide");
            },
            'error' : function(request, status, error){
                messageArr.push('error:' + data.exception.code + ' ' + data.exception.message);
                console.log('error:' + error);
                $.LoadingOverlay("hide");
            }
        });
});


JS;
$this->registerJs($script, \yii\web\View::POS_READY);
