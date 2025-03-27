<?php

use yii\widgets\ActiveForm;
use app\api\filters\EmailParseFilter;

/**
 * @var EmailParseFilter $model
 */
?>
<?php $form = ActiveForm::begin([
    'method' => 'post',
    'action' => ['/api/email-parse'],
//    'action' => ['/api/email-parse/check-email-parser'],
    'enableClientValidation' => false,
]); ?>
<?= $form->field($model, 'from') ?>
<?= $form->field($model, 'to') ?>
<?= $form->field($model, 'date') ?>
<?= $form->field($model, 'subject') ?>
<?= $form->field($model, 'body')->textArea(['rows' => 1]) ?>
<?= $form->field($model, 'raw')->textArea(['rows' => 13]) ?>
<div class="btn-group">
<?= \yii\helpers\Html::submitButton('Проверить', ['class' => 'btn btn-primary']) ?>
<?= \yii\helpers\Html::submitButton('Распарсить', ['class' => 'btn btn-secondary', 'formaction' => \yii\helpers\Url::current()]) ?>
<?= \yii\helpers\Html::submitButton('Посмотреть', ['class' => 'btn btn-secondary', 'formaction' => \yii\helpers\Url::to('/email-parse/view-html')]) ?>
</div>
<?php ActiveForm::end(); ?>

<?php

function getConfigKors()
{
    return [
        'from' => '?UTF-8?B?TWljaGFlbCBLb3Jz?= <MichaelKors@michaelkorsmail.com>',
        'to' => 'imaplib@shopfans.ru',
        'date' => 'test_message',
        'subject' => 'Thu, 27 Feb 2020 14:52:40 +0300',
        'body' => '',
        'raw' => getRaw(),
    ];
}
function getRaw() {
    return '';
}

