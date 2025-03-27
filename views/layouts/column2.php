<?php
/**
 * @var \yii\web\View $this
 * @var string $content
 */
?>
<?php $this->beginContent(__DIR__ . '/private.php') ?>
<div class="col">
    <div class="col-lg-3 col-lg-push-9">
        <?= $this->blocks['sidebar'] ?? '' ?>
    </div>
    <div class="col-lg-9 col-lg-pull-3">
        <?= $content ?>
    </div>
</div>
<?php $this->endContent() ?>
