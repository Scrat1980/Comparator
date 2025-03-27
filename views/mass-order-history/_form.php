<?php
/**
 * @var \yii\web\View $this
 * @var \app\records\MassOrderDiscount $record
 */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

?>
<div class="mass-order-discount-form">
    <?php $form = ActiveForm::begin() ?>
    <?= $form->field($record, 'user_id')->textInput() ?>
    <?= $form->field($record, 'external_number')->textInput() ?>
    <?= $form->field($record, 'market_id')->textInput(['readonly' => true]) ?>
    <?= $form->field($record, 'account')->textInput() ?>
    <?= $form->field($record, 'discount')->textInput(['autofocus' => true]) ?>
    <?= $form->field($record, 'delivery_cost_usd')->textInput() ?>
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    <?php ActiveForm::end() ?>
</div>
