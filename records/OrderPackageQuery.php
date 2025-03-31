<?php

namespace app\records;

use yii\db\ActiveQuery;

/**
 * Order Package Query
 */
class OrderPackageQuery extends ActiveQuery
{
    public function init()
    {
        parent::init();
        $this->alias('op');
    }

    /**
     * @param int $id
     * @return OrderPackageQuery
     */
    public function byId(int $id)
    {
        return $this->andWhere(['op.id' => $id]);
    }

    /**
     * @param $customerId
     * @return OrderPackageQuery
     */
    public function byCustomerId($customerId)
    {
        return $this->joinWith('order')->andWhere(['customer_id' => $customerId]);
    }

    /**
     * @param $trackingNumber
     * @return OrderPackageQuery
     */
    public function byTrackingNumber($trackingNumber)
    {
        return $this->andwhere(['tracking_number' => $trackingNumber]);
    }

    /**
     * @param $id
     * @return OrderPackageQuery
     */
    public function notId($id)
    {
        return $this->andWhere(['!=', 'id', $id]);
    }

    /**
     * @param $packageId
     * @return OrderPackageQuery
     */
    public function bySfPackageId($packageId)
    {
        return $this->andWhere(['sf_package_id' => $packageId]);
    }

    /**
     * @param $packageId
     * @return OrderPackageQuery
     */
    public function bySfShipmentId($shipmentId)
    {
        return $this->andWhere(['sf_shipment_id' => $shipmentId]);
    }

    /**
     * @param $externalOrderId
     * @return OrderPackageQuery
     */
    public function byExternalOrderId($externalOrderId)
    {
        return $this->andWhere(['external_order_id' => $externalOrderId]);
    }

    /**
     * @param $externalOrderId
     * @return OrderPackageQuery
     */
    public function byStatus($status)
    {
        return $this->andWhere(['status' => $status]);
    }

    /**
     * @inheritdoc
     * @return OrderPackage[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return OrderPackage|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
