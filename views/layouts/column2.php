<?php
/**
 * @var View $this
 * @var string $content
 */

use yii\web\View;

?>
<?php $this->beginContent(__DIR__ . '/private.php') ?>
<div class="row">
    <div class="col-lg-9 col-lg-pull-3">
        <?= $content ?>
    </div>
    <div class="col-lg-3 col-lg-push-9">
        <?= $this->blocks['sidebar'] ?? '' ?>
    </div>
</div>
<?php $this->endContent() ?>
