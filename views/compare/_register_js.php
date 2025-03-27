<?php

use yii\web\View;

function registerFiles($fileNames, $that) {
    $position = ['position' => View::POS_END,];
    foreach ($fileNames as $fileName) {
        $that->registerJsFile('@web/js/comparator/' . $fileName, $position);
    }
}

registerFiles([
    'Form/Form.js',
    'Form/keepUpdated.js',
    'Form/getFormData.js',
    'Form/submit.js',
    'Form/initialize.js',
    'Panes.js',
    'App.js',
    'App/PageView.js',
    'App/pinger.js',
    'App/myFetch.js',
    'App/FilesUpdater.js',
], $this);