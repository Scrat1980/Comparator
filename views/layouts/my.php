<?php
/**
 * @var View $this
 * @var string $content
 */

use yii\web\View;

?>
<?php $this->beginContent(__DIR__ . '/private.php') ?>
<div class="row">
    <?= $content ?>
</div>
<?php $this->endContent() ?>
