<?php

namespace app\records;

use yii\db\ActiveQuery;

/**
 * User Query
 */
class UserQuery extends ActiveQuery
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
     * @param string $key
     * @return $this
     */
    public function byAuthKey(string $key)
    {
        return $this->andWhere(['auth_key' => $key]);
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
     * @return User[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return User|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
