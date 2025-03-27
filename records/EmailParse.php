<?php

namespace app\records;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "email_parse".
 *
 * @property int $id
 * @property string $hash
 * @property int $tracking_number
 * @property int $external_order_id
 * @property int $market_id
 * @property int $web
 * @property string $eml
 * @property string $result_data
 * @property string $validate_data
 * @property string $order_package_data
 * @property int $created_at
 * @property int $updated_at
 */
class EmailParse extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'email-parse';
    }

    /**
     * {@inheritdoc}
     * @return EmailParseQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new EmailParseQuery(get_called_class());
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['market_id', 'created_at', 'updated_at'], 'integer'],
            [['tracking_number', 'external_order_id'], 'string'],
            [['eml', 'result_data', 'validate_data', 'order_package_data'], 'string'],
            [['hash'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'hash' => 'Hash',
            'tracking_number' => 'Tracking Number',
            'external_order_id' => 'External Order ID',
            'market_id' => 'Market ID',
            'web' => 'Web',
            'eml' => 'Eml',
            'result_data' => 'Result Data',
            'validate_data' => 'Validate Data',
            'order_package_data' => 'order_package Data',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    self::EVENT_BEFORE_INSERT => 'created_at',
                    self::EVENT_BEFORE_UPDATE => 'updated_at',
                ],
            ],
        ];
    }

    public function getTotalResultData()
    {
        $resultData = json_decode($this->result_data, true);
        $totalResultData = 0;
        $totalQtyResultData = 0;
        if ($resultData !== null) {
            $totalResultData = count($resultData);
            $qtyResultData = ArrayHelper::getColumn($resultData, 'quantity');
            $totalQtyResultData = array_sum($qtyResultData);
        }

        return $totalResultData . ' (' . $totalQtyResultData . ')';
    }

    public function getTotalResultDataWeb()
    {
        $eml = json_decode($this->eml, true);
        $totalEmlResultData = 0;
        $totalEmlQtyResultData = 0;

        if ($eml !== null) {
            foreach ($eml as $order) {
                $totalEmlResultData += count($order['items']);
                $totalEmlQtyResultData += array_sum(array_column($order['items'], 'quantity'));
            }
        }

        return $totalEmlResultData . ' (' . $totalEmlQtyResultData . ')';
    }

    public function getTotalValidateData()
    {
        $validateData = json_decode($this->validate_data, true);
        $totalValidateData = count($validateData['data']);

        if (isset($validateData['multi_track']) && $validateData['multi_track'] === true) {
            $totalValidateData = 0;
            foreach ($validateData['data'] as $key => $item) {
                $totalValidateData += count($item);
            }
        }

        return $totalValidateData;
    }

    public function getTotalOrderProductData()
    {
        $orderProductData = json_decode($this->order_package_data, true);
        $totalOrderProductData = 0;
        $totalQtyOrderProductData = 0;
        if ($orderProductData !== null) {
            $orderPackageProductData = ArrayHelper::getColumn($orderProductData, 'packageProducts');
            $totalOrderProductData = count($orderPackageProductData);
            $totalQtyOrderProductData = 0;
            foreach ($orderPackageProductData as $orderProducts) {
                $qtyOrderProductData = ArrayHelper::getColumn($orderProducts, 'quantity');
                $totalQtyOrderProductData += array_sum($qtyOrderProductData);
            }
        }
        return $totalOrderProductData . ' (' . $totalQtyOrderProductData . ')';
    }

    public function getSplitPackageData()
    {
        $validateData = json_decode($this->validate_data, true);
        $splitPackage = [];
        if (isset($validateData['multi_track']) && $validateData['multi_track'] === true) {
            $trackingNumbers = array_keys($validateData['data']);
            $splitPackage = SplitPackage::find()->where(['IN', 'tracking_number', $trackingNumbers])->indexBy('tracking_number')->all();
        } else {
            if($this->tracking_number){
                $splitPackage = SplitPackage::find()->where(['IN', 'tracking_number', $this->tracking_number])->indexBy('tracking_number')->all();
            }
        }

        return $splitPackage;
    }

    public function getTrackingNumber()
    {
        $validateData = json_decode($this->validate_data, true);
        if (isset($validateData['multi_track']) && $validateData['multi_track'] === true) {
            $trackingNumbers = array_keys($validateData['data']);
        } else {
            $trackingNumbers = [$this->tracking_number];
        }

        return implode(' , ', $trackingNumbers);
    }
}
