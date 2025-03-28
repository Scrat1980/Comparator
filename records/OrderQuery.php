<?php

namespace app\records;

use yii\db\ActiveQuery;

/**
 * Order Query
 */
class OrderQuery extends ActiveQuery
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
     * @param array $ids
     * @return $this
     */
    public function byIds(array $ids)
    {
        return $this->andWhere(['IN', 'id', $ids]);
    }

    /**
     * @param int $customerId
     * @return $this
     */
    public function byCustomerId(int $customerId)
    {
        return $this->andWhere(['customer_id' => $customerId]);
    }

    /**
     * @return $this
     */
    public function nextOrder()
    {
        return $this
            ->where(['status' => Order::STATUS_NEW])
            ->orderBy(['created_at' => SORT_ASC]);
    }

    /**
     * @inheritdoc
     * @return Order[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Order|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
