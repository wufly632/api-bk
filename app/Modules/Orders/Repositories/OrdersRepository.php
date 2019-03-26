<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/15
 * Time: 下午3:46
 */

namespace App\Modules\Orders\Repositories;

use App\Models\Customer\Order;
use App\Models\Customer\OrderGood;

class OrdersRepository
{
    public static function getUserBuyGoodNum($promotionId, $userId, $goodId)
    {
        return OrderGood::join('customer_order', 'customer_order.id', '=', 'customer_order_goods.order_id')
            ->where('customer_order_goods.good_id', $goodId)
            ->where('customer_order.customer_id', $userId)
            ->where('customer_order_goods.activity_id', $promotionId)
            ->sum('num');
    }

    public static function createOrder($orders)
    {
        return Order::create($orders);
    }

    public static function createOrderGoods($orderGoods)
    {
        foreach ($orderGoods as $orderGood) {
            OrderGood::create($orderGood);
        }
    }

    public static function getOrderInfoByOrderId($orderId)
    {
        return Order::where('order_id', $orderId)->first();
    }

    public static function orderUpdate($orderId, $data)
    {
        return Order::where('order_id', $orderId)->update($data);
    }

    public static function getList($userId, $status)
    {
        $model = Order::where('is_del', 0)->where('customer_id', $userId);
        if ($status) {
            if ($status == 4) {
                $model = $model->whereIn('status', [3, 4]);
            } else {
                $model = $model->where('status', $status);
            }
        }
        return $model->orderBy('id', 'desc')->paginate(10);
    }

    public static function deleteOrder($id)
    {
        return Order::where('id', $id)->update(['is_del' => 1, 'deleted_at' => date('Y-m-d H:i:s')]);
    }

    public static function getUserOrdersCounts($userId)
    {
        return Order::where('customer_id', $userId)
            ->where('is_del', 0)
            ->selectRaw('count(id) as orders, status')
            ->groupBy('status')
            ->get()->toArray();
    }

    public static function getOrderByOrderId($orderId)
    {
        return Order::where('order_id', $orderId)->first();
    }

    public static function getOrderPayCount($userId)
    {
        return Order::where('customer_id', $userId)->whereNotNull('pay_at')->count();
    }

    public static function getOrderGoodsInfoByOrderId($orderId)
    {
        return OrderGood::join('goods', 'customer_order_goods.good_id', '=', 'goods.id')
            ->where('customer_order_goods.order_id', $orderId)
            ->get();
    }

    public static function isHasSuccessOrderInHalfYear($userId)
    {
        return Order::where('customer_id', $userId)
            ->whereIn('status', [3, 4, 5])
            ->count('id');
    }
}