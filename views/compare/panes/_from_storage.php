<div
    style="display: none;"
    class="pane-two"
    role="pane"
>
    <div
            style="border: 1px #ccc solid;
                border-bottom-left-radius: 5px;
                border-bottom-right-radius: 5px;
                margin: 0 5px 5px 0; padding: 5px;
            "
            id="menu-2"
            role="pane"
    >
        <?= $this->render('sources/_tab_one.php') ?>
        <?= $this->render('sources/_tab_two.php') ?>
        <div role="pane"
             class="pane-one"
             style="
                border: 1px #ccc solid;
                border-bottom-left-radius: 5px;
                border-bottom-right-radius: 5px;
                margin: 0 5px 5px 0; padding: 5px;
                overflow-y: scroll;
            "
        >
            <?= $this->render('../_cached_files_table.php', ['tags' => $tags]) ?>
        </div>
        <div role="pane"
             class="pane-two"
            style="display: none;
                border: 1px #ccc solid;
                border-bottom-left-radius: 5px;
                border-bottom-right-radius: 5px;
                margin: 0 5px 5px 0; padding: 5px;
            ">
            <?= $this->render('../_manage_tags.php') ?>
        </div>
    </div>
</div>
