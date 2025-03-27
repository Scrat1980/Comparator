<?php


namespace app\records;


use yii\db\ActiveQuery;

class MassOrderProductQuery extends ActiveQuery
{
    public function init()
    {
        parent::init();
        $this->alias('mop');
    }

    /**
     * @param int $id
     * @return MassOrderProductQuery
     */
    public function byId(int $id)
    {
        return $this->andWhere(['mop.id' => $id]);
    }
}
