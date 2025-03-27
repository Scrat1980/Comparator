<?php
/**
 * @var \yii\web\View $this
 * @var \app\records\MassOrderProduct $record
 */

$this->title = "Update mass order product $record->id";
?>
<div class="mass-order-product-update">
    <?= $this->render('_form-product', compact('record')) ?>
</div>
