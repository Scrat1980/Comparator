<?php

namespace app\models;

use app\records\EmailParse;
use Yii;
use yii\base\Model;

/**
 * Email Parse Filter
 */
class EmailParseFilter extends Model
{
    public $tracking_number;
    public $external_order_id;
    public $market_id;
    /**
     * @return EmailParseFilter
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
            ['tracking_number', 'string'],
            ['external_order_id', 'string'],
            ['market_id', 'integer'],
        ];
    }


    /**
     * @return \app\records\EmailParseQuery
     */
    public function search()
    {
        $query = EmailParse::find();
        if ($this->hasErrors()) {
            return $query->andWhere('1 = 0');
        }

        $query->andFilterWhere(['like', 'tracking_number', $this->tracking_number]);
        $query->andFilterWhere(['like', 'external_order_id', $this->external_order_id]);

        if($this->tracking_number == '' && $this->external_order_id == ''){
            $query->andFilterWhere(['market_id' => $this->market_id]);
        }

        return $query;
    }
}
