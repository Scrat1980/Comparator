<?php

namespace app\records;

use yii\db\ActiveQuery;

/**
 * Customer Query
 */
class CustomerQuery extends ActiveQuery
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
     * @param string $email
     * @return $this
     */
    public function byEmail(string $email)
    {
        return $this->andWhere(['email' => $email]);
    }

    /**
     * @param string $email_shopfans
     * @return CustomerQuery
     */
    public function byEmailShopfans(string $email_shopfans)
    {
        return $this->andWhere(['email_shopfans' => $email_shopfans]);
    }

    /**
     * @param string $phone
     *
     * @return $this
     */
    public function byPhone(string $phone)
    {
        return $this->andWhere(['phone' => $phone]);
    }

    /**
     * @param string $key
     * @return $this
     */
    public function byApiKey(string $key)
    {
        return $this->andWhere(['api_key' => $key]);
    }

    /**
     * @return $this
     */
    public function active()
    {
        return $this->andWhere(['locked_at' => null]);
    }

    /**
     * @inheritdoc
     * @return Customer[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Customer|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
