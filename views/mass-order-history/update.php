<?php
/**
 * @var \yii\web\View $this
 * @var \app\records\MassOrderDiscount $record
 */

$this->title = "Update mass order discount $record->id";
?>
<div class="mass-order-discount-update">
    <?= $this->render('_form', compact('record')) ?>
</div>
