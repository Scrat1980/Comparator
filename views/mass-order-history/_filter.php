<?php
/**
 * @var \yii\web\View $this
 * @var \app\web\filters\MassOrderDiscountFilter $filter
 */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

?>
<div class="mass-order-history-filter">
    <h3>Filter</h3>
    <?php $form = ActiveForm::begin([
        'id' => 'search-form',
        'action' => ['index'],
        'method' => 'get',
        'enableClientValidation' => false,
    ]) ?>
    <?= $form->field($filter, 'external_order_id') ?>
    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::button('Сбросить', ['class' => 'btn btn-warning btn-md js-clear-search']) ?>
    </div>
    <?php ActiveForm::end() ?>

    <?php $form = ActiveForm::begin([
        'id' => 'search-form',
        'action' => ['index'],
        'method' => 'get',
        'enableClientValidation' => false,
    ]) ?>
    <?= $form->field($filter, 'account') ?>
    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::button('Сбросить', ['class' => 'btn btn-warning btn-md js-clear-search']) ?>
    </div>
    <?php ActiveForm::end() ?>

    <!-- фильтр по продукту -->
    <?php $form = ActiveForm::begin([
        'id' => 'search-form-product',
        'method' => 'get', 
        'action' => ['index'],
        'enableClientValidation' => false
        ]); ?>
        <?= $form->field($filter, 'order_product_id') ?>
    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::button('Сбросить', ['class' => 'btn btn-warning btn-md js-clear-search-product']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
<?php
$script = <<<JS
    $('.js-clear-search').on('click', function(e) {
        e.preventDefault();
        $('#tracking_number').val('');
        $('#external_order_id').val('');
        $('#search-form').submit();
    });
    // сброс фильтра по продукту
    $('.js-clear-search-product').on('click', function(e) {
        e.preventDefault();
        $('#order_product_id').val('');
        $('#search-form-product').submit();
    })
JS;

$this->registerJs($script, \yii\web\View::POS_READY);
