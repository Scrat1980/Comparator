<?php
/**
 * @var array $files
 * @var string $defaultTag
 */

//use app\web\Comparator\ComparatorForm;

$this->title = 'Bulk getStructure()/getProductVariantExecute() checker';
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="col-sm-6">
    <button class="btn btn-primary" id='submit_compare'>
        Submit
    </button>
    <div style="border: 1px #ccc solid; border-radius: 5px; margin: 5px; padding: 10px; min-height: 50vh;"
         id="menu"
    >
        <?=$this->render('panes/_tab_one.php')?>
        <?=$this->render('panes/_tab_two.php')?>
<!--        --><?php //=$this->render('panes/_new_letters.php', [
//            'defaultTag' => $defaultTag,
//            'files' => $files,
//        ])?>
<!--        --><?php //=$this->render('panes/_from_storage.php', ['tags' => $tags])?>
    </div>
</div>
<div class="col-sm-6">
    <?= $this->render('_status.php') ?>
    <?= $this->render('_results.php') ?>
</div>
<div class="col-sm-12" style="
    border: 1px #ccc solid;
    border-radius: 5px;
    margin: 15px;
    min-height: 30px;
    overflow: scroll;
    "
    id="my-errors"
>
</div>
<?= $this->render('_register_js.php') ?>