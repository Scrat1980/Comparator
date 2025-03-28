<?php
/**
 * @var View $this
 * @var string $labelEntity
 * @var RequestReplicationEditor $model
 */

use yii\web\View;
use app\models\RequestReplicationEditor;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

?>
<div class="request-email-parsing-form">
    <?php $form = ActiveForm::begin() ?>

    <?= $form
        ->field($model, 'mass_order_discount_id')
        ->label($labelEntity)
        ->textInput()
    ?>
    <?=
        Html::submitButton('Получить запрос', ['class' => 'btn btn-primary'])
    ?>
<!--    --><?php //=
//        $form
//            ->field($model, 'show_order_ids')
//            ->checkbox(['checked ' => false])
//            ->label('Подготовить список id заказов, связанных с массордером по таблице');
//    ?>
<!--    --><?php //=
//        $form
//            ->field($model, 'add_order_queries')
//            ->checkbox(['checked ' => false])
//            ->label('Добавить в запрос выборку данных по связанным заказам')
//    ?>
    <?php ActiveForm::end() ?>
</div>
