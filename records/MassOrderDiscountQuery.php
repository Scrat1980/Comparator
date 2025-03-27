<?php


namespace app\records;


use yii\db\ActiveQuery;

class MassOrderDiscountQuery extends ActiveQuery
{
    public function init()
    {
        parent::init();
        $this->alias('mod');
    }

    /**
     * @param int $id
     * @return MassOrderDiscountQuery
     */
    public function byId(int $id)
    {
        return $this->andWhere(['mod.id' => $id]);
    }

    /**
     * @param int $id
     * @return MassOrderDiscountQuery
     */
    public function byExternalNumber($externalNumber)
    {
        return $this->andWhere(['mod.external_number' => $externalNumber]);
    }

    /**
     * @param $marketId
     * @return mixed
     */
    public function byMarket($marketId)
    {
        return $this->andWhere(['mod.market_id' => $marketId]);
    }
}
