<?php

/**
 * @var View $this
 * @var string $labelEntity
 * @var string $queryProduct
 * @var string $queryOrder
 * @var string $initialQuery
 * @var RequestReplicationEditor $model
 */

use app\models\RequestReplicationEditor;
use yii\bootstrap5\Html;
use yii\web\View;

$this->registerJsFile(Yii::getAlias('@web/js/request-replication.js'), ['position' => View::POS_END, 'depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile(Yii::getAlias('@web/css/request-replication.css'));

$this->title = "Request order data";
?>
<h3>Request orders' data</h3>

<div class="request-email-parsing-data">
    <?= $this->render('_form', ['model' => $model, 'labelEntity' => $labelEntity]) ?>
</div>
<?php if ($queryProduct || $queryOrder || $initialQuery) : ?>
    <div id="content" role="tablist" aria-multiselectable="true">
        <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="queryAll">
                <div class="query-block">
                    <a class="collapsed" data-toggle="collapse" href="#queryAllBody" aria-expanded="true" aria-controls="queryAllBody">
                        <div class="query-block-title">
                            <h5 class="panel-title">
                                All Query
                            </h5>
                            <i class="glyphicon glyphicon-chevron-down"></i>
                        </div>
                    </a>
                    <?= Html::button('Скопировать запрос', ['class' => 'btn btn-info js-button-order-all']) ?>
                </div>
            </div>
            <div id="queryAllBody" class="panel-collapse collapse" role="tabpanel" aria-labelledby="queryAll">
                <div class="panel-body">
                    <label for="js-textarea-request-query"></label><textarea id="js-textarea-request-query" class="form-control" rows="30"><?= ($initialQuery ? $initialQuery . "\n\n" : '') . $queryProduct . "\n\n" . $queryOrder ?></textarea>
                </div>
            </div>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="queryProduct">
                <div class="query-block">
                    <a class="collapsed" data-toggle="collapse" href="#queryProductBody" aria-expanded="false" aria-controls="queryProductBody">
                        <div class="query-block-title">
                            <h5 class="panel-title">
                                Product Query
                            </h5>
                            <i class="glyphicon glyphicon-chevron-down"></i>
                        </div>
                    </a>
                    <?= Html::button('Скопировать запрос', ['class' => 'btn btn-info js-button-product']) ?>
                </div>
            </div>
            <div id="queryProductBody" class="panel-collapse collapse" role="tabpanel" aria-labelledby="queryProduct">
                <div class="panel-body">
                    <div class="request-email-parsing-query-detail">
                        <p id="js-request-product"><?= $queryProduct ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="queryOrder">
                <div class="query-block">
                    <a class="collapsed" data-toggle="collapse" href="#queryOrderBody" aria-expanded="false" aria-controls="queryOrderBody">
                        <div class="query-block-title">
                            <h5 class="panel-title">
                                Order Query
                            </h5>
                            <i class="glyphicon glyphicon-chevron-down"></i>
                        </div>
                    </a>
                    <?= Html::button('Скопировать запрос', ['class' => 'btn btn-info js-button-order']) ?>
                </div>
            </div>
            <div id="queryOrderBody" class="panel-collapse collapse" role="tabpanel" aria-labelledby="queryOrder">
                <div class="panel-body">
                    <div class="request-email-parsing-query-detail">
                        <p id="js-request-order"><?= $queryOrder ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
endif;
