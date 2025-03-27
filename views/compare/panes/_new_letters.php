<?php
/**
 * @var string $defaultTag
 * @var array $files
 */
?>
<div
    style="border: 1px #ccc solid;
        border-bottom-left-radius: 5px;
        border-bottom-right-radius: 5px;
        margin: 0 5px 5px 0; padding: 5px;
        overflow: hidden;
    "
    class="pane-one"
    role="pane"
>
    <?=$this->render('sources/_from_prod.php')?>
<!--    --><?php //=$this->render('sources/_by_order_number.php')?>
<!--    --><?php //=$this->render('sources/_by_tracking_number.php')?>
<!--    --><?php //=$this->render('sources/_by_market_id.php')?>
    <?=$this->render('sources/_from_folder.php', ['files' => $files])?>

    <span style="font-size: 18px;">
        assign tag:
    </span>
    <input type="text" name="tag" value="<?= $defaultTag ?>" >
</div>
