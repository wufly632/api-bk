<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/15
 * Time: 下午3:46
 */

namespace App\Modules\Orders\Repositories;

use App\Models\Customer\OrderGood;
use Illuminate\Support\Facades\DB;

class OrderGoodsRepository
{
    public static function getOrderGoodInfoByOrderIds($orderIds)
    {
        return OrderGood::whereIn('order_id', $orderIds)->get();
    }

    public static function getOneGoodByOrderId($orderId, $columns = ['*'])
    {
        return OrderGood::where('order_id', $orderId)->get($columns);
    }
}