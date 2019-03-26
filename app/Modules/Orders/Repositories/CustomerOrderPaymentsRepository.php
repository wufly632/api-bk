<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/19
 * Time: 下午9:22
 */

namespace App\Modules\Orders\Repositories;


use App\Models\Customer\OrderPaymentAmount;

class CustomerOrderPaymentsRepository
{
    public static function addLog($log)
    {
        return OrderPaymentAmount::insert($log);
    }

    public static function deleteByOrderId($orderId)
    {
        return OrderPaymentAmount::where('order_id', $orderId)->delete();
    }

    public static function update($where, $customerPayment)
    {
        return OrderPaymentAmount::where($where)->update($customerPayment);
    }

    public static function getOrderPayment($paymentId)
    {
        return OrderPaymentAmount::where('pay_id', $paymentId)->first();
    }

    public static function getOrderBalancePayment($orderId)
    {
        return OrderPaymentAmount::where('order_id', $orderId)->where('payment', 1)->first();
    }

    /**
     * @function 获取paypal支付的金额
     * @param $orderId
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public static function getOrderPaypalPayment($orderId)
    {
        return OrderPaymentAmount::where('order_id', $orderId)->where('payment', 2)->first();
    }
}