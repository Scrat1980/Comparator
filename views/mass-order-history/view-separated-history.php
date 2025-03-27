<?php
/**
 * @var \yii\web\View $this
 * @var \app\records\MassOrderDiscount $model
 * @var \app\records\MassOrderProductQuery $query
 * @var string $marketName
 * @var int $countOrderProductWithTrack
 */

$this->title = "Mass Order Discount $model->id";
?>
<h3><?= $this->title ?></h3>
<div class="mass-order-discount-view-separated-history">
        <?= $this->render('_view-header', ['model' => $model, 'marketName' => $marketName, 'countOrderProductWithTrack' => $countOrderProductWithTrack]) ?>
        <?= $this->render('_grid-product-separated-history', compact('query')) ?>
    </div>

