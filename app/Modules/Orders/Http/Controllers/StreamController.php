<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Services\GoodSkuService;
use App\Modules\Orders\Services\OrderGoodsService;
use App\Modules\Orders\Services\OrdersService;
use App\Modules\Orders\Services\StreamService;
use App\Modules\Orders\Services\TrackingMoreService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Routing\Controller;

class StreamController extends Controller
{

    protected $trackingMoreService;

    /**
     * 注入物流api
     * StreamController constructor.
     * @param TrackingMoreService $trackingMoreService
     */
    public function __construct(TrackingMoreService $trackingMoreService)
    {
        $this->trackingMoreService = $trackingMoreService;
    }

    /**
     * 获取物流信息
     * @return mixed
     */
    public function getStream()
    {
        $order_id = request()->post('order_id');
        if (!$order_id) {
            return ApiResponse::failure(g_API_ERROR, 'Order Id can not be null');
        }
        $order = OrdersService::getOrderInfoByOrderId($order_id);
        if (!$order || $order->customer_id != UsersService::getUserId()) {
            return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        }
        try {
            $trackInfo = $this->trackingMoreService->getTrackinfo($order);
            foreach (json_decode($trackInfo->trackinfo, true) as $k => $v) {
                $content['detail'][$k]['date'] = $v['Date'];
                $content['detail'][$k]['description'] = $v['StatusDescription'];
            }
            $orderGoods = OrderGoodsService::getGoodsByOrderId($order_id);
            $orderFirstSkuId = $orderGoods[0]->sku_id;
            $content['img'] = GoodSkuService::getSkuInfoBySkuId($orderFirstSkuId)->icon;
            $content['item'] = count($orderGoods);
            $content['status'] = $trackInfo->status;
            $content['number'] = $order->waybill_id;
            //TODO 物流商信息对接；
            $content['tel'] = $order->waybill_id;
            $content['type'] = $order->waybill_id;
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, $e->getMessage());
        }
        return ApiResponse::success($content);
    }
}
