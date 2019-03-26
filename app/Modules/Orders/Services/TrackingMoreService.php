<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Repositories\CustomerOrderTrackingmoreRepository;

class TrackingMoreService
{

    protected $streamService;

    public function __construct(StreamService $streamService)
    {
        $this->streamService = $streamService;
    }

    public function addTrackinfo($option)
    {
        return CustomerOrderTrackingmoreRepository::addTableGetId($option);
    }

    public function updateTrackinfo($orderId, $option)
    {
        return CustomerOrderTrackingmoreRepository::updateTable($orderId, $option);
    }

    /**
     * @param $order
     * @return CustomerOrderTrackingmoreRepository|\Illuminate\Database\Eloquent\Model|null|object
     * @throws \Exception
     */
    public function getTrackinfo($order)
    {
        $trackInfo = CustomerOrderTrackingmoreRepository::getTable($order->order_id);
        if (in_array($order->status, [4, 5])) {
            if ($trackInfo) {
                if (strval($trackInfo->status) != 'delivered') {
                    $getApiInfo = $this->getStreamByApi($order->shipper_code, $order->waybill_id);
                    $trackInfo->trackinfo = $getApiInfo['trackinfo'];
                    $trackInfo->status = $getApiInfo['status'];
                    $this->updateTrackinfo($trackInfo->id, $getApiInfo);
                }
            } else {
                $getApiInfo = $this->getStreamByApi($order->shipper_code, $order->waybill_id);
                $trackInfo->trackinfo = $getApiInfo['trackinfo'];
                $trackInfo->status = $getApiInfo['status'];
                $getApiInfo['order_id'] = $order->order_id;
                $this->addTrackinfo($getApiInfo);
            }
        } else {
            throw new \Exception('This order has no logistics information');
        }
        return $trackInfo;
    }

    /**
     * 获取物流信息接口
     * @param $shipper_code
     * @param $waybill_id
     * @return array
     * @throws \Exception
     */
    public function getStreamByApi($shipper_code, $waybill_id)
    {
        $orderTrackInfo = $this->streamService->getSingleTrackingResult($shipper_code, $waybill_id);
        if ($orderTrackInfo['meta']['code'] == 200) {
            $getApiInfo = [
                'status' => $orderTrackInfo['data']['status'],
                'trackinfo' => json_encode($orderTrackInfo['data']['origin_info']['trackinfo'])
            ];
            return $getApiInfo;
        } else {
            throw new \Exception('获取信息失败');
        }
    }
}