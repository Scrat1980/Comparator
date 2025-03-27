<?php

namespace app\records;

/**
 * This is the ActiveQuery class for [[EmailParse]].
 *
 * @see EmailParse
 */
class EmailParseQuery extends \yii\db\ActiveQuery
{
    /**
     * @param $id
     * @return EmailParseQuery
     */
    public function byId($id)
    {
        return $this->andWhere(['id' => $id]);
    }

    /**
     * @param $hash
     * @return EmailParseQuery
     */
    public function byHash($hash)
    {
        return $this->andWhere(['hash' => $hash]);
    }

    /**
     * @return EmailParseQuery
     */
    public function last()
    {
        return $this->orderBy('created_at DESC')->limit(1);
    }

    /**
     * {@inheritdoc}
     * @return EmailParse[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return EmailParse|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
