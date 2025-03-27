<?php
use yii\web\View;

$this->registerJsFile(
    '@web/js/outOfStock.js',
    ['position' => View::POS_END,]
);
