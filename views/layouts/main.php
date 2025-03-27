<?php

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\web\View;

/** @var View $this */
/** @var string $content */

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>

<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header id="header">
<!--    <div class="wrap">-->
        <?php NavBar::begin([
            'id' => 'nav-admin-panel',
            'options' => [
                'class' => 'navbar-inverse navbar-fixed-top',
            ],
        ]) ?>
            <?= Nav::widget([
            'options' => ['class' => 'navbar-nav'],
            'items' => [
                [
                    'label' => 'Email-Parse',
                    'url' => ['/email-parse/index'],
                    'visible' => true,
                    'active' => strpos(Yii::$app->requestedRoute, 'email-parse/') === 0,
                ],
                [
                    'label' => 'Mass Orders History',
                    'url' => ['/mass-order-history/index'],
                    'visible' => true,
                    'active' => strpos(Yii::$app->requestedRoute, 'mass-order-history/') === 0,
                ],
                [
                    'label' => 'Request Replication',
                    'url' => ['/request-replication/orders'],
                    'visible' => true,
                    'active' => strpos(Yii::$app->requestedRoute, 'request-replication/orders') === 0,
                ],
                [
                    'label' => 'Comparator Service',
                    'url' => ['/compare'],
                    'visible' => true,
                    'active' => strpos(Yii::$app->requestedRoute, 'compare') === 0,
                ],
            ],
        ]) ?>
        <?php NavBar::end() ?>

        <div class="container<?= $this->blocks['fluid'] ?? '' ?>">
            <?= Breadcrumbs::widget([
                'homeLink' => false,
                'links' => array_merge(
                    [['label' => 'Home',
                        'url' => Yii::$app->homeUrl]],
                    isset($this->params['breadcrumbs'])
                        ? $this->params['breadcrumbs']
                        : []),
            ]) ?>
            <?= Alert::widget() ?>
<!--            --><?php //= $this->render('buttons') ?>
<!--            --><?php //= $content ?>
        </div>
<!--    </div>-->
</header>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container">
        <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
        <?php endif ?>
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</main>

<footer id="footer" class="mt-auto py-3 bg-light">
    <div class="container">
        <div class="row text-muted">
            <div class="col-md-6 text-center text-md-start">&copy; My Company <?= date('Y') ?></div>
            <div class="col-md-6 text-center text-md-end"><?= Yii::powered() ?></div>
        </div>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
