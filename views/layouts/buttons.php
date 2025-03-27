<?php

/** @var View $this */

use yii\bootstrap5\ButtonDropdown;
use yii\bootstrap5\Html;
use yii\web\View;


$buttons = $this->params['buttons'] ;
//$buttons = isset($this->params['buttons']) ? $this->params['buttons'] : [];
echo Html::beginTag('p');
foreach ($buttons as $button) {
    if (isset($button['visible']) && !$button['visible']) {
        continue;
    }
    $label = Html::encode($button['label']);
    if (isset($button['icon'])) {
        $label = Html::icon($button['icon']) . ' ' . $label;
    }
    $options = isset($button['options']) ? $button['options'] : [];
    $type = isset($button['type']) ? $button['type'] : 'default';
    Html::addCssClass($options, "btn btn-$type");
    if (empty($button['items'])) {
        echo Html::a($label, $button['url'], $options), ' ';
    } else {
        echo ButtonDropdown::widget([
            'label' => $label,
            'encodeLabel' => false,
            'options' => $options,
            'dropdown' => [
                'items' => $button['items'],
            ],
        ]);
    }
}
echo Html::endTag('p');
