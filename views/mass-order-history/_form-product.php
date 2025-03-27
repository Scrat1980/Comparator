<?php
/**
 * @var \yii\web\View $this
 * @var \app\records\MassOrderProduct $record
 */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

?>
<div class="mass-order-discount-form">
    <?php $form = ActiveForm::begin() ?>
    <?= $form->field($record, 'mass_order_discount_id')->textInput(['autofocus' => true]) ?>
    <?= $form->field($record, 'order_id')->textInput(['readonly' => true]) ?>
    <?= $form->field($record, 'order_product_id')->textInput(['readonly' => true]) ?>
    <?= $form->field($record, 'quantity')->textInput(['readonly' => true]) ?>
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    <?php ActiveForm::end() ?>
</div>
