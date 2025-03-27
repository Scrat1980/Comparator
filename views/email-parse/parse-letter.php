<?php

$this->title = 'Email-parse';
$this->params['breadcrumbs'][] = 'Парсинг письма';
$model = new \app\api\filters\EmailParseFilter();
?>

<?php $form = \yii\widgets\ActiveForm::begin([
    'method' => 'post',
    'action' => ['/email-parse/parse-letter'],
    'enableClientValidation' => false,
]); ?>
<?= $form->field($model, 'raw')->textArea(['rows' => 13]) ?>
<?= \yii\helpers\Html::submitButton('Проверить', ['class' => 'btn btn-primary']) ?>
<?php \yii\widgets\ActiveForm::end(); ?>
