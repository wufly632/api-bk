<?php

namespace App\Modules\Users\Repositories;

use App\Models\Customer\Income as CustomerIncome;

class CustomerIncomeRepository
{


    const TYPE_SHOPPING_REWARD = 1;
    const TYPE_FANS_SHOPPING = 2;
    const TYPE_FANS_REWARD = 3;
    const TYPE_INVITE_FANS = 4;

    public static function getWaitAccount($userId)
    {
        return CustomerIncome::where('income_user_id', $userId)
            ->where('status', 1)
            ->sum('amount');
    }

    public static function getList($userId, $status)
    {
        return CustomerIncome::where('income_user_id', $userId)
            ->where('status', $status)
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

//    public static function addWaitAccount($orderId)
//    {
//        $order = DB::table('customer_order')
//            ->where('order_id', $orderId)
//            ->first();
//        //获取订单明细
//        $order_goods = DB::table('customer_order_goods as cog')
//            ->selectRaw('cog.good_id,g.rebate_level_one,g.rebate_level_two,cog.total_price')
//            ->leftJoin('goods as g', 'g.id', '=', 'cog.good_id')
//            ->where('cog.order_id', $orderId)
//            ->get();
//        $currency = Currency::where('currency_code', $order->currency_code)->first();
//        $data = [];
//        $self_amount = 0;
//        $two_amount = 0;
//        $three_amount = 0;
//        foreach ($order_goods as $order_good) {
//            // 按促销价格计算
//            $amount_one = round(round(($order_good->total_price / $currency->rate)*($order_good->rebate_level_one), 2)/100, 3);
//            $amount_two = round(round($amount_one*$order_good->rebate_level_two, 2)/100, 3);
//            $amount_three = round(round($amount_two*$order_good->rebate_level_two, 2)/100, 3);
//            $self_amount += $amount_one;
//            $two_amount += $amount_two;
//            $three_amount += $amount_three;
//        }
//        $orderAmount = $currency->symbol . round($order->final_price + $order->fare, $currency->digit);
//        if ($self_amount > 0) {
//            $data[] = [
//                'from_user_id' => $order->customer_id,
//                'income_user_id' => $order->customer_id,
//                'type' => 1,//1本人购物返利
//                'status' => 1,//1待入账
//                'order_id' => $orderId,
//                'amount' => round($self_amount,2),
//                'order_amount' => $orderAmount,
//                'created_at' => Carbon::now()->toDateTimeString(),
//                'updated_at' => Carbon::now()->toDateTimeString(),
//            ];
//        }
//        CLogger::getLogger('customer_incomes', 'pay')->info('', $data);
//        $userParent = DB::table('customer_relationship')->where('user_id', $order->customer_id)->first();
//        $isThree = false;
//        // 当前用户有上级
//        if ($userParent) {
//            if ($userParent->status == 1) {
//                // 当前用户与上级关系已激活
//                $isThree = true;
//                if ($two_amount) {
//                    $data[] = [
//                        'from_user_id' => $order->customer_id,
//                        'income_user_id' => $userParent->parent_id,
//                        'type' => 2,//2fans购物
//                        'status' => 1,//1待入账
//                        'order_id' => $orderId,
//                        'amount' => round($two_amount,2),
//                        'order_amount' => $orderAmount,
//                        'created_at' => Carbon::now()->toDateTimeString(),
//                        'updated_at' => Carbon::now()->toDateTimeString(),
//                    ];
//                }
//            } else {
//                $parentUserInfo = (new UserRepository())->getUserinfo($userParent->parent_id);
//                if (self::checkActivityStatus()) {
//                    $updateItems = self::updateInviteRecord($order->customer_id, $userParent->parent_id);//修正狀態
//                    if ($updateItems) {
//                        UserRepository::addIncome($userParent->parent_id, config('thirdparty.inviteMoney', 10));//到账
//                        self::addMarquee($parentUserInfo, self::$gainStr);
//                        $financeLog = [
//                            'turnover_id' => client_finance_sn($userParent->parent_id, 6),
//                            'amount' => config('thirdparty.inviteMoney', 10),
//                            'user_id' => $userParent->parent_id,
//                            'from_user_id' => $order->customer_id,
//                            'created_at' => Carbon::now(),
//                            'operate_type' => 6,
//                            'remark' => '12月活动，粉丝支付'
//                        ];
//                        CustomerFinanceLogRepository::addLog($financeLog);
//                    }
//                }
//                // 当前用户与上级关系未激活
//                // 判断上级是否完成首单
//                if (DB::table('customer_order')->where('customer_id', $userParent->parent_id)->whereNotNull('pay_at')->count()) {
//
//                    // 上级用户完成首单，激活关系、添加返利
//                    DB::table('customer_relationship')->where('id', $userParent->id)->update(['status' => 1]);
//                    $isThree = true;
//                    if ($two_amount) {
//                        $data[] = [
//                            'from_user_id' => $order->customer_id,
//                            'income_user_id' => $userParent->parent_id,
//                            'type' => 2,//2fans购物
//                            'status' => 1,//1待入账
//                            'order_id' => $orderId,
//                            'amount' => round($two_amount,2),
//                            'order_amount' => $orderAmount,
//                            'created_at' => Carbon::now()->toDateTimeString(),
//                            'updated_at' => Carbon::now()->toDateTimeString(),
//                        ];
//                    }
//                }
//            }
//        }
//        if ($isThree) {
//            $userGranParent = DB::table('customer_relationship')->where('user_id', $userParent->parent_id)->first();
//            // 当前用户的父级有上级且关系激活
//            if ($userGranParent && $userGranParent->status == 1) {
//                if ($three_amount) {
//                    $data[] = [
//                        'from_user_id' => $order->customer_id,
//                        'income_user_id' => $userGranParent->parent_id,
//                        'type' => 3,//3fans返利
//                        'status' => 1,//1待入账
//                        'order_id' => $orderId,
//                        'amount' => round($three_amount,2),
//                        'order_amount' => $orderAmount,
//                        'created_at' => Carbon::now()->toDateTimeString(),
//                        'updated_at' => Carbon::now()->toDateTimeString(),
//                    ];
//                }
//            }
//        }
//        if ($data) {
//            return DB::table('customer_incomes')->insert($data);
//        }
//        return true;
//    }

    /**
     *
     * @param $orderId
     * @return mixed
     */
    public static function getWaitAccountByOrderId($orderId)
    {
        return CustomerIncome::where('order_id', $orderId)
            ->where('status', 1)
            ->get();
    }

    /**
     * 创建邀请收益记录
     * @param $whoId
     * @param $benefitId
     */
    public static function createInviteRecord($whoId, $benefitId)
    {
        CustomerIncome::create([
            'from_user_id' => $whoId,
            'income_user_id' => $benefitId,
            'type' => 4,//邀请用户所得;
            'status' => 1,
            'amount' => config('thirdparty.inviteMoney', 10),
            'order_amount' => 0
        ]);
    }

    /**
     * 更新邀请状态
     * @param $whoId
     * @param $benefitId
     * @return mixed
     */
    public static function updateInviteRecord($whoId, $benefitId)
    {
        return CustomerIncome::where('from_user_id', $whoId)->where('income_user_id', $benefitId)->where('type', 4)->where('status', 1)->update(['status' => 2]);
    }

    /**
     * 获取邀请所得
     * @param $userId
     * @return mixed
     */
    public static function getInviteGet($userId)
    {
        return CustomerIncome::where('income_user_id', $userId)->where('type', 4)->sum('amount');
    }

    public static function batchCreate($data)
    {
        CustomerIncome::insert($data);
    }
}
