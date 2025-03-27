<?php
/**
 * @var View $this
 * @var EmailParseFilter $filter
 */

use app\models\EmailParseFilter;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\web\View;

?>
<div class="email-parse-filter">
    <h3>Filter</h3>
    <?php $form = ActiveForm::begin([
        'id' => 'search-form',
        'action' => ['index'],
        'method' => 'get',
        'enableClientValidation' => false,
    ]) ?>
    <?= $form->field($filter, 'tracking_number') ?>
    <?= $form->field($filter, 'external_order_id') ?>
    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::button('Сбросить', ['class' => 'btn btn-warning btn-md js-clear-search']) ?>
    </div>
    <?php ActiveForm::end() ?>
</div>
<?php
$script = <<<JS
    $('.js-clear-search').on('click', function(e) {
        e.preventDefault();
        $('#tracking_number').val('');
        $('#external_order_id').val('');
        $('#search-form').submit();
    });
JS;

$this->registerJs($script, View::POS_READY);
