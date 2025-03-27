<?php

namespace app\models;

use app\records\MassOrderDiscount;
use Yii;
use yii\base\Model;

/**
 * Mass Order Discount Filter
 */
class MassOrderDiscountFilter extends Model
{
    public $external_order_id;
    public $market_id;
    public $order_product_id;
    public $account;
    /**
     * @return MassOrderDiscountFilter
     */
    public static function ensure()
    {
        $filter = new static();
        $filter->load(Yii::$app->request->queryParams);
        $filter->validate();
        return $filter;
    }

    /**
     * @inheritdoc
     */
    public function formName()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['external_order_id', 'account'], 'string'],
            ['market_id', 'integer'],
            ['order_product_id', 'integer'],
        ];
    }


    /**
     * @return \app\records\MassOrderDiscountQuery
     */
    public function search($queryParams)
    {
        $query = MassOrderDiscount::find();

        if ($this->hasErrors()) {
            return $query->andWhere('1 = 0');
        }

        if ($queryParams) {
            $query->andWhere(['id' => $queryParams])->orderBy(['created_at' => SORT_DESC]);
        } else {
            if ($this->order_product_id) {
                return $query->andWhere('1 = 0');
            }
    
            if ($this->external_order_id) {
                $query->andFilterWhere(['like', 'external_number', $this->external_order_id]);
                $query->orderBy(['created_at' => SORT_DESC]);
            } else {
                $query->orderBy(['created_at' => SORT_DESC]);
            }

            if($this->account) {
                $query->andFilterWhere(['like', 'account', $this->account]);
                $query->orderBy(['created_at' => SORT_DESC]);
            }

            if (empty($this->external_order_id)) {
                $query->andFilterWhere(['market_id' => $this->market_id]);
            }
        }
    
        return $query;
    }
}
