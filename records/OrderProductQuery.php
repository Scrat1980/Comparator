<?php

namespace app\records;

use yii\db\ActiveQuery;

/**
 * Order Product Query
 */
class OrderProductQuery extends ActiveQuery
{
    /**
     * @param int $id
     * @return $this
     */
    public function byId(int $id)
    {
        return $this->andWhere(['id' => $id]);
    }

    /**
     * @param int $orderId
     * @return $this
     */
    public function byOrderId(int $orderId)
    {
        return $this->andWhere(['order_id' => $orderId]);
    }

    /**
     * @param int $status
     * @return $this
     */
    public function byStatus(int $status)
    {
        return $this->andWhere(['status' => $status]);
    }

    /**
     * @param int[] $ids
     * @param int $packageId
     * @return $this
     */
    public function byIdsAndPackageId($ids, $packageId)
    {
        return $this
            ->andWhere(['op.id' => $ids])
            ->joinWith('orderPackage')
            ->andWhere(['order_package.id' => $packageId]);
    }

    /**
     * @param int $sfPackageId
     * @return $this
     */
    public function bySfPackageId($sfPackageId)
    {
        return $this
            ->joinWith('orderPackage')
            ->andWhere(['op.sf_package_id' => $sfPackageId]);
    }

    /**
     * @param $productId
     * @return $this
     */
    public function byProductId($productId)
    {
        return $this->andWhere(['product_id' => $productId]);
    }

    /**
     * @param $productVariantId
     * @return OrderProductQuery
     */
    public function byProductVariantId($productVariantId)
    {
        return $this->andWhere(['product_variant_id' => $productVariantId]);
    }

    /**
     * @param array $productVariantIds
     * @return OrderProductQuery
     */
    public function byProductVariantIds($productVariantIds)
    {
        return $this->andWhere(['in', 'product_variant_id', $productVariantIds]);
    }

    /**
     * @inheritdoc
     * @return OrderProduct[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return OrderProduct|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
