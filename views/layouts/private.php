<?php
/**
 * @var View $this
 * @var string $content
 */

use app\Access;
use app\widgets\Alert;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\web\View;
use yii\widgets\Breadcrumbs;
use app\records\User;

/** @var \app\records\User $user */
$user = Yii::$app->user->identity;

$this->registerCss(<<<CSS
#nav-admin-panel .nav > li > a {
    position: relative;
    display: block;
    padding: 15px 14px;
}
CSS
);
?>
<?php $this->beginContent(__DIR__ . '/base.php') ?>

<div class="wrap">
    <?php NavBar::begin([
        'id' => 'nav-admin-panel',
//        'brandLabel' => preg_replace('/(.)/', '<span>$1</span>', Yii::$app->name),
//        'brandUrl' => Yii::$app->homeUrl,
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
    <?php
    $css = <<<CSS
        .js-form-search-navbar {
            margin-top: 8px;
            width: 160px;
        }
CSS;
    $this->registerCss($css);

    ?>
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
<!--        --><?php //= $this->render('buttons') ?>
        <?= $content ?>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p class="pull-left">&copy; MyCo <?= date('Y') ?></p>
        <p class="pull-right"><?= Yii::powered() ?></p>
    </div>
</footer>

<?php $this->endContent() ?>
