<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/5
 * Time: 下午4:10
 */

namespace App\Modules\Coupon\Services;

use App\Modules\Coupon\Repositories\CouponRepository;
use App\Services\ApiResponse;
use App\Assistants\CLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public static function getActivityCode($userId, $currency_code = '')
    {
        return CouponRepository::getCouponCode($userId, $currency_code);
    }

    public static function getProductCoupons($currency_code)
    {
        return CouponRepository::getProductCoupons($currency_code);
    }

    public static function getCouponCount($userId)
    {
        return CouponRepository::getCouponCount($userId);
    }

    public static function couponCodeList($userId)
    {
        return CouponRepository::couponCodeList($userId);
    }

    public static function getCouponInfo($couponId)
    {
        return CouponRepository::getCouponInfo($couponId);
    }

    public static function couponValidator($couponInfo, $userId)
    {
        $new = Carbon::now()->toDateTimeString();
        if ($couponInfo->coupon_grant_startdate > $new) return ApiResponse::failure(g_API_ERROR, 'The coupon receive has not started');
        if ($couponInfo->coupon_grant_enddate <= $new) return ApiResponse::failure(g_API_ERROR, 'The coupon receive has ended');
        if ($couponInfo->coupon_number && $couponInfo->coupon_number <= $couponInfo->receive_number) {
            return ApiResponse::failure(g_API_ERROR, 'The coupon has been received');
        }
        if (CouponRepository::getUserCouponCodeInfo($couponInfo->id, $userId)) {
            return ApiResponse::failure(g_API_ERROR, 'You have received this coupon');
        }
        return false;
    }

    public static function couponReceive($couponInfo, $userId)
    {
        $now = Carbon::now()->toDateTimeString();
        try {
            DB::beginTransaction();
            $couponCode = [
                'user_id' => $userId,
                'code_code' => makeCouponCode($couponInfo->id) . $userId,
                'coupon_id' => $couponInfo->id,
                'code_receive_status' => 2,
                'code_use_status' => 1,
                'code_received_at' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ];
            if ($couponInfo->use_type == 1) {
                $couponCode['code_used_start_date'] = $now;
                $couponCode['code_used_end_date'] = date('Y-m-d H:i:s', strtotime("{$couponInfo->use_days} day"));
            } else {
                $couponCode['code_used_start_date'] = $couponInfo->coupon_use_startdate;
                $couponCode['code_used_end_date'] = $couponInfo->coupon_use_enddate;
            }
            CouponRepository::couponCodeInsert($couponCode);
            if ($couponInfo->coupon_number) {
                CouponRepository::addCouponReceiveNumber($couponInfo->id);
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            // dd($exception->getMessage());
            CLogger::getLogger('coupon', 'coupons')->info($exception->getMessage());
            return false;
        }
    }

    public static function getCouponInfoByKey($couponKey)
    {
        return CouponRepository::getCouponInfoByKey($couponKey);
    }

    public static function userCouponCodeAll($userId)
    {
        return CouponRepository::couponCodeAll($userId);
    }
}