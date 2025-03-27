<?php
/**
 * @var array $files
 */
?>
<p style="font-size: 18px;">
    & From default folder
    <span style="font-size: 14px;" id="files-count">
            (currently contains <?= count($files) ?> file(s))
    </span>
    <input type="text" style="display: none;" value="<?= count($files) ?>" name="files" id="files">
</p>
<div style="border: 1px grey solid; padding: 10px; margin: 0 0 10px; width: 425px; height: 70px; overflow-y: scroll;
    "
    id="files-container"
    >
    <?php
        foreach ($files as $file) {
            echo '<p>' . $file . '</p>';
        }
    ?>
</div>
