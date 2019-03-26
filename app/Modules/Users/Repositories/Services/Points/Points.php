<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/9
 * Time: 23:18
 */

namespace App\Modules\Users\Repositories\Services\Points;


use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Points
{
    const SIGNED = 'signed';
    const EXCHANGE_VOUCHER = 'exchange_voucher';
    const SHARE = 'share';
    const REGISTER = 'register';
    const PURCHASE_ORDER = 'purchase_order';
    const COMMENT = 'comment';

    protected static $ways = [
        self::SIGNED => 'Check In',
        self::EXCHANGE_VOUCHER => 'Exchange Voucher',
        self::SHARE => 'Share',
        self::REGISTER => 'Register',
        self::PURCHASE_ORDER => 'Order',
    ];

    public static function generatePointHistoryDisplay($operation)
    {
        if($operation == 'signed') {
            return 'Check In';
        } elseif ($operation == 'exchange_voucher') {
            return 'Exchange Voucher';
        } elseif ($operation == 'share') {
            return 'Share';
        }elseif ($operation == 'register') {
            return 'Register';
        }elseif ($operation == 'purchase_order') {
            return 'Order';
        } else {
            return 'Check in';
        }
    }

    /**
     * 触发用户注册事件相关业务逻辑
     * @param $user_id
     * @param $type
     */
    public static function userRegister($user_id, $type)
    {
        try {
            $register_point_rule = DB::table("oms_point_rules")->useWritePdo()->where("operation", $type)->where("enabled", 1)->first();
            if ($register_point_rule) {
                if ($register_point_rule->random) {
                    $random_point = self::generateRandomForPoint();
                } else {
                    $random_point = $register_point_rule->point;
                }
                $history_data = [
                    'point_rule_id' => $register_point_rule->id,
                    'operation' => $register_point_rule->operation,
                    'point' => $random_point,
                    'user_id' => $user_id,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ];
                $point_history_id = DB::table('oms_point_history')->insertGetId($history_data);
                $user_points = self::checkIsUserPoints($user_id);
                if ($point_history_id) {
                    $user_points->point = $user_points->point + $random_point;
                    self::updateUserPoint($user_points);
                    self::generatePointNotification($type, $user_id, 'oms_point_history', $point_history_id, 'system', $random_point);
                }
            }
        }catch (\Exception $e){
            Log::error("userRegister error:".$e->getMessage());
        }
    }

    /**
     * 生成随机积分
     * @return int
     */
    public static function generateRandomForPoint()
    {
        $random_a = mt_rand(1, 99);
        $random_point = self::generateRandomPoint($random_a);
        return $random_point;
    }

    /**
     * 根据数学期望生成随机积分
     * @param $random_a
     * @return int
     */
    private static function generateRandomPoint($random_a)
    {
        $random_b = 0;
        if ($random_a > 0 && $random_a <= 15) {
            $random_b = rand(10, 50);
        } elseif ($random_a > 15 && $random_a <= 85) {
            $random_b = rand(51, 90);
        } elseif ($random_a > 85 && $random_a <= 99) {
            $random_b = rand(91, 150);
        }
        return $random_b;
    }

    /**
     * 验证用户是否已经有积分，没有则生成积分为0的数据
     * @param $user_id
     * @return mixed
     */
    private static function checkIsUserPoints($user_id)
    {
        $user_points = DB::table("oms_user_points")->useWritePdo()->where("user_id", $user_id)->first();
        if (!$user_points) {
            $user_point_date = [
                'point' => 0,
                'user_id' => $user_id,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString()
            ];
            $point_history_id = DB::table('oms_user_points')->insertGetId($user_point_date);
            if ($point_history_id) {
                $user_points = DB::table('oms_user_points')->useWritePdo()->find($point_history_id);
            }
        }
        return $user_points;
    }

    /**
     * 更新用户积分
     * @param $user_points
     */
    private static function updateUserPoint($user_points)
    {
        DB::table('oms_user_points')
            ->where('id', $user_points->id)
            ->update(['point' => $user_points->point]);
    }

    /**
     * 生成积分获取推送
     * @param $type
     * @param $user_id
     * @param string $reference_table
     * @param $reference_id
     * @param string $notification_type
     * @param $point
     */
    public static function generatePointNotification($type, $user_id, $reference_table = 'oms_point_history', $reference_id, $notification_type = 'system', $point)
    {
        switch ($type) {
            case  self::SIGNED:
                $title = "Congrats! You've earned " . $point . " Pat Points by check in with your PatPat account.";
                break;
            case  self::REGISTER :
                $title = "Congrats! You've earned " . $point . " Pat Points by creating your PatPat account. ";
                break;
            case  self::COMMENT :
                $title = "Congrats! You've earned " . $point . " Pat Points by comment your PatPat order. ";
                break;
            case  self::SHARE :
                $title = "Congrats! You've earned " . $point . " Pat Points by share a PatPat product. ";
                break;
            case  self::PURCHASE_ORDER :
                $title = "Congrats! You've earned " . $point . "  Pat Points by placing an order. ";
                break;
            default :
                $title = "";
                break;
        }
        $notification = array(
            "title" => $title,
            "type" => $notification_type,
            "user_id" => $user_id,
            "reference_table" => $reference_table,
            "reference_id" => $reference_id,
            "created_at" => \Carbon\Carbon::now()->toDateTimeString(),
            "isread" => 0
        );
        DB::table('sys_notification')->insert($notification);
    }

    /**
     * 更新用户积分
     * @param $user_id
     * @param $point
     */
    public static function updateUserPoints($user_id, $point)
    {
        if (is_array($user_id)) {
            DB::table('oms_user_points')->whereIn('user_id', $user_id)->update(['point' => $point]);
        } else {
            DB::table('oms_user_points')->where('user_id', $user_id)->update(['point' => $point]);
        }
    }
}