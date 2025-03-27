<?php

use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\grid\ActionColumn;
use yii\grid\GridView;

echo $tags;
//$provider = new ActiveDataProvider(
//    [
//        'query' => (new Query())
//            ->select([
//                'zz_tags.id',
//                'tag',
//                'count(*) AS count',
//            ])
//            ->from(
//                'zz_tags'
//            )->innerJoin(
//                'zz_cache_tags', 'zz_tags.id = zz_cache_tags.zz_tags_id'
//            )->innerJoin(
//                'zz_cache', 'zz_cache_tags.zz_cache_id = zz_cache.id'
//            )->groupBy(['zz_tags.id'])
//            ->orderBy(['zz_tags.id' => SORT_DESC]),
////                ->column()
//        'pagination' =>
//            false,
////          [
////              'pageSize' => 15,
////          ],
//    ]
//);
?>
<?php //try{
////    throw new Exception('Oops!');
//    echo GridView::widget(
//        [
//            'dataProvider' => $provider,
//            'options' => ['style' => 'max-height: 30vh;',],
//            'columns' => [
//                [
//                    'class' => 'yii\grid\SerialColumn',
//                    'headerOptions' => ['style' => 'width: 30px; overflow: hidden;text-align: center;'],
//                    'contentOptions' => ['style' => 'width: 30px; overflow: hidden;'],
//                ],
////                'id',
////                [
////                    'attribute' => 'old_file_name',
////                    'header' => 'Name',
////                    'headerOptions' => ['style' => 'width: 20%; overflow: hidden;'],
////                    'contentOptions' => ['style' => 'width: 20%; overflow: hidden;'],
////                ],
//                [
//                    'attribute' => 'tag',
//                    'header' => 'Tag / Use case number',
//                    'headerOptions' => ['style' => 'width: 60%; overflow: hidden;text-align: center;'],
//                    'contentOptions' => ['style' => 'width: 60%; overflow: hidden;'],
//                ],
//                [
//                    'attribute' => 'count',
//                    'header' => 'Number of letters',
//                    'headerOptions' => ['style' => 'width: 35%; overflow: hidden;text-align: center;'],
//                    'contentOptions' => ['style' => 'width: 35%; overflow: hidden;'],
//                ],
//                [
//                    'class' => 'yii\grid\CheckboxColumn',
//                    'checkboxOptions' => function($model, $key, $index, $column) {
//                        return ['value' => $model['id']];
//                    },
//                    'header' => 'Action',
//                    'headerOptions' => ['style' => 'width: 20%; overflow: hidden;text-align: center;'],
//                    'contentOptions' => [
//                        'style' => 'width: 20%; overflow: hidden; text-align: center;',
//                    ],
//                ],
////                [
////                    'header' => 'Action',
////                    'value' => function ($model) { return ''; },
////                    'headerOptions' => ['style' => 'width: 20%; overflow: hidden;'],
////                    'contentOptions' => ['style' => 'width: 20%; overflow: hidden;'],
////                ],
////                [
////                    'class' => ActionColumn::class, 'header' => 'Action',
////                    'headerOptions' => ['style' => 'width: 20%; overflow: hidden;'],
////                    'contentOptions' => ['style' => 'width: 20%; overflow: hidden;'],
////                ],
//            ],
//            'headerRowOptions' => ['style' => 'display: flex; overflow: hidden; width: 100%'],
//            'rowOptions' => ['style' => 'display: flex; overflow: hidden; width: 100%'],
//        ]);
//    } catch(Exception $e){
////        echo 'Stored files table<br>';
//        echo $e->getMessage();
//    }
?>