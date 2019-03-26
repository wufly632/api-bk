<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/18
 * Time: 下午4:11
 */

namespace App\Modules\Orders\Services;


use App\Modules\Orders\Repositories\OrderGoodsRepository;

class OrderGoodsService
{
    /**
     * 查找订单商品
     * @param $orderId
     * @param $columns
     * @return OrderGoodsRepository|\Illuminate\Database\Eloquent\Model|null|object
     */
    public static function getGoodsByOrderId($orderId, $columns = ['*'])
    {
        return OrderGoodsRepository::getOneGoodByOrderId($orderId, $columns);
    }
}