<?php

namespace app\records;

use yii\db\ActiveQuery;

/**
 * Order Package Product Query
 */
class OrderPackageProductQuery extends ActiveQuery
{
    /**
     * @param int $packageId
     * @return $this
     */
    public function byPackage(int $packageId)
    {
        return $this->andWhere(['order_package_id' => $packageId]);
    }

    /**
     * @inheritdoc
     * @return OrderPackageProduct[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return OrderPackageProduct|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
