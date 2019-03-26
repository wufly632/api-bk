<?php
// +----------------------------------------------------------------------
// | CustomerOrderAddressRepository.php
// +----------------------------------------------------------------------
// | Description:
// +----------------------------------------------------------------------
// | Time: 2018/12/7 下午2:43
// +----------------------------------------------------------------------
// | Author: wufly <wfxykzd@163.com>
// +----------------------------------------------------------------------

namespace App\Modules\Orders\Repositories;

use App\Models\Order\CustomerOrderAddress;
use App\Modules\Users\Repositories\AddressRepository;

class CustomerOrderAddressRepository
{
    /**
     * @function 更新或插入订单地址
     * @param $order_id
     * @param $addressInfo
     * @return mixed
     */
    public static function updateOrInsert($order_id, $addressInfo)
    {
        $addressInfo['order_id'] = $order_id;
        return CustomerOrderAddress::updateorcreate(['order_id' => $order_id], $addressInfo);
    }

    /**
     * @function 将用户传入的地址转为订单地址
     * @param $orderId
     * @param $addressId
     */
    public static function transformOrderAddress($orderId,$addressId)
    {
        $addressInfo = AddressRepository::getAddressInfo($addressId);
        $order_address_data = array_only(collect($addressInfo)->toArray(), ['firstname','lastname','country','state','city','street_address','suburb','postcode','phone']);
        self::updateOrInsert($orderId, $order_address_data);
    }

    /**
     * @function 获取订单地址
     * @param $orderId
     * @return mixed
     */
    public static function getAddressByOrderId($orderId)
    {
        return CustomerOrderAddress::where('order_id', $orderId)->first();
    }
}
