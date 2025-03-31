<?php

namespace app\records;

use yii\db\ActiveQuery;

/**
 * Product Variant Query
 */
class ProductVariantQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->alias('pv');
    }

    /**
     * @param int $id
     * @return $this
     */
    public function byId(int $id)
    {
        return $this->andWhere(['pv.id' => $id]);
    }

    /**
     * @param $ids
     * @return ProductVariantQuery
     */
    public function byIds($ids)
    {
        return $this->andWhere(['in', 'pv.id', $ids]);
    }

    /**
     * @param $productIds
     * @return ProductVariantQuery
     */
    public function byProductIds($productIds)
    {
        return $this->andWhere(['in', 'pv.product_id', $productIds]);
    }

    /**
     * @return $this
     */
    public function active()
    {
        return $this->andWhere(['>', 'pv.stock_count', 0]);
    }

    /**
     * @return $this
     */
    public function lost()
    {
        return $this->andWhere(['pv.stock_count' => 0]);
    }

    /**
     * @return $this
     */
    public function first()
    {
        return $this->orderBy(['pv.id' => SORT_ASC])->limit(1);
    }

    /**
     * Метод для консольного тестового создания order
     *
     * @param int $marketId
     * @return $this
     */
    public function randomIdByMarketId(int $marketId)
    {
        return $this
            ->from('product_variant as pv')
            ->leftJoin('product p', 'pv.product_id = p.id')
            ->andWhere(['p.market_id' => $marketId])
            ->select('pv.id')
            ->andWhere(['p.is_lost' => false])
            //Да я знаю что так нельзя
            ->orderBy('rand()');
    }

    /**
     * Метод для консольного тестового создания order
     *
     * @return ProductVariantQuery
     */
    public function random()
    {
        return $this
            ->from('product_variant as pv')
            ->leftJoin('product p', 'pv.product_id = p.id')
            ->select('pv.id')
            ->andWhere(['p.is_lost' => false])
            //Да я знаю что так нельзя
            ->orderBy('rand()');
    }

    /**
     * @inheritdoc
     * @return ProductVariant[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return ProductVariant|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }

    public function byCode(int $market_id, $variantCode)
    {
        return $this
            ->leftJoin('product p', 'pv.product_id = p.id')
            ->andWhere(['p.market_id' => $market_id, 'pv.remote_code' => $variantCode]);
    }
}
