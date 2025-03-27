<?php
/**
 * @var array $block
 */
?>
<div style="border: 1px #ccc solid; border-radius: 5px; margin: 5px; padding: 5px;">
    <?=
    $block['form']
        ->field(
            $block['model'],
            'type',
            [
                'options' => ['style' => 'display: inline'],
                'errorOptions' => ['tag' => null],
            ]
        )
        ->radio(
            [
                'id' => $block['radio']['id'],
                'value' => $block['radio']['value'],
                'uncheck' => false,
            ],
            ['style' => 'display: inline']
        )
    ?>
    <?php
    echo isset($block['text'][0])
        ?
        $block['form']
            ->field(
                $block['model'],
                $block['text'][0]['field']
    //            ['options' => ['style' => 'display: inline']]
            )
            ->dropDownList($block['text'][0]['markets'])
            ->label($block['text'][0]['label'])
        : ''
    ?>
    <?=
    $block['form']
        ->field(
            $block['model'],
            $block['text'][1]['field'],
            ['options' => ['style' => 'display: inline'],]
        )->textInput([
            'placeholder' => $block['text'][1]['placeholder'],
            'value' => $block['text'][1]['value'] ?? '',
            'style' => '/*width: 300px;*/ display: inline;',
        ])->label($block['text'][1]['label'])
    ?>
</div>