<?php

namespace App\Modules\Users\Services;

use App\Assistants\CLogger;
use App\Modules\Orders\Repositories\CustomerFinanceLogRepository;
use App\Modules\Orders\Repositories\OrderGoodsRepository;
use App\Modules\Orders\Repositories\OrdersRepository;
use App\Modules\Users\Repositories\CurrencyRepository;
use App\Modules\Users\Repositories\CustomerIncomeRepository;
use App\Modules\Users\Repositories\CustomerRelationshipRepository;
use App\Modules\Users\Repositories\UserRepository;
use App\Traits\ActivityInviteTrait;
use Carbon\Carbon;

class CustomerIncomeService
{
    use ActivityInviteTrait;

    /**
     * 获取待入账金额
     * @param $userId
     * @return mixed
     */
    public static function getWaitAccount($userId)
    {
        return CustomerIncomeRepository::getWaitAccount($userId);
    }

    /**
     * 获取列表
     * @param $userId
     * @param $status
     * @return mixed
     */
    public static function getList($userId, $status)
    {
        return CustomerIncomeRepository::getList($userId, $status);
    }

    /**
     * 增加待入账数量
     * @param $orderId
     */
    public static function addWaitAccount($orderId)
    {
        $now = Carbon::now()->toDateTimeString();
        $order = OrdersRepository::getOrderByOrderId($orderId);
        //获取订单明细
        $order_goods = OrderGoodsRepository::getOneGoodByOrderId($orderId);
        $currency = CurrencyRepository::getByCurrencyCode($order->currency_code);
        $data = [];
        $self_amount = 0;
        $two_amount = 0;
        $three_amount = 0;
        foreach ($order_goods as $order_good) {
            // 按促销价格计算
            $amount_one = bcdiv(bcmul($order_good->total_price / $currency->rate, $order_good->good->rebate_level_one, 2), 100, $currency->digit);
            $amount_two = bcdiv(bcmul($amount_one, $order_good->good->rebate_level_two, 2), 100, $currency->digit);
            $amount_three = bcdiv(bcmul($amount_two, $order_good->good->rebate_level_two, 2), 100, $currency->digit);
            $self_amount += $amount_one;
            $two_amount += $amount_two;
            $three_amount += $amount_three;
        }
        $orderAmount = $currency->symbol . bcadd($order->final_price, $order->fare, $currency->digit);
        if ($self_amount > 0) {
            $data[] = [
                'from_user_id' => $order->customer_id,
                'income_user_id' => $order->customer_id,
                'type' => 1,//1本人购物返利
                'status' => 1,//1待入账
                'order_id' => $orderId,
                'amount' => $self_amount,
                'order_amount' => $orderAmount,
                'created_at' => $now
            ];
        }
        CLogger::getLogger('customer_incomes', 'pay')->info('', $data);
        $userParent = CustomerRelationshipRepository::getParentByUserId($order->customer_id);
        $isThree = false;
        // 当前用户有上级
        if ($userParent) {
            if ($userParent->status == 1) {
                // 当前用户与上级关系已激活
                $isThree = true;
                if ($two_amount) {
                    $data[] = [
                        'from_user_id' => $order->customer_id,
                        'income_user_id' => $userParent->parent_id,
                        'type' => 2,//2fans购物
                        'status' => 1,//1待入账
                        'order_id' => $orderId,
                        'amount' => $two_amount,
                        'order_amount' => $orderAmount,
                        'created_at' => $now
                    ];
                }
            } else {
                $parentUserInfo = (new UserRepository())->getUserinfo($userParent->parent_id);
                if (self::checkActivityStatus()) {
                    $updateItems = CustomerIncomeRepository::updateInviteRecord($order->customer_id, $userParent->parent_id);//修正狀態
                    if ($updateItems) {
                        UserRepository::addIncome($userParent->parent_id, config('thirdparty.inviteMoney', 10));//到账
                        self::addMarquee($parentUserInfo, self::$gainStr);
                        $financeLog = [
                            'turnover_id' => client_finance_sn($userParent->parent_id, 6),
                            'amount' => config('thirdparty.inviteMoney', 10),
                            'user_id' => $userParent->parent_id,
                            'from_user_id' => $order->customer_id,
                            'operate_type' => 6,
                            'remark' => '12月活动，粉丝支付',
                            'created_at' => $now
                        ];
                        CustomerFinanceLogRepository::addLog($financeLog);
                    }
                }
                // 当前用户与上级关系未激活
                // 判断上级是否完成首单
                if (OrdersRepository::getOrderPayCount($userParent->parent_id)) {
                    // 上级用户完成首单，激活关系、添加返利
                    CustomerRelationshipRepository::updateStatusById($userParent->id);
                    $isThree = true;
                    if ($two_amount) {
                        $data[] = [
                            'from_user_id' => $order->customer_id,
                            'income_user_id' => $userParent->parent_id,
                            'type' => 2,//2fans购物
                            'status' => 1,//1待入账
                            'order_id' => $orderId,
                            'amount' => $two_amount,
                            'order_amount' => $orderAmount,
                            'created_at' => $now
                        ];
                    }
                }
            }
        }
        if ($isThree) {
            $userGranParent = CustomerRelationshipRepository::getParentByUserId($userParent->parent_id);
            // 当前用户的父级有上级且关系激活
            if ($userGranParent && $userGranParent->status == 1) {
                if ($three_amount) {
                    $data[] = [
                        'from_user_id' => $order->customer_id,
                        'income_user_id' => $userGranParent->parent_id,
                        'type' => 3,//3fans返利
                        'status' => 1,//1待入账
                        'order_id' => $orderId,
                        'amount' => $three_amount,
                        'order_amount' => $orderAmount,
                        'created_at' => $now
                    ];
                }
            }
        }
        if ($data) {
            //批量更新;
            CustomerIncomeRepository::batchCreate($data);
        }
    }
}