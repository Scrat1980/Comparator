<?php
use yii\bootstrap5\Nav;

/* @var $navItems array */
?>
<?= Nav::widget([
    'options' => ['class' => 'nav-tabs'],
    'items' => $navItems,
]) ?>
