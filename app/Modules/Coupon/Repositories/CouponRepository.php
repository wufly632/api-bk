<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/5
 * Time: 下午4:11
 */

namespace App\Modules\Coupon\Repositories;

use App\Models\Coupon\Code;
use App\Models\Coupon\Coupon;
use Illuminate\Support\Carbon;


class CouponRepository
{
    public static function getCouponCode($userId, $currency_code)
    {
        $now = Carbon::now()->toDateTimeString();
        $query = Code::with('coupon')
            ->where('coupon_code.user_id', $userId)
            ->where('coupon_code.code_use_status', 1)
            ->where('coupon_code.code_used_start_date', '<=', $now)
            ->where('coupon_code.code_used_end_date', '>', $now);
        if ($currency_code) {
            $query = $query->whereHas('coupon', function ($item) use ($currency_code) {
                return $item->where('coupon.currency_code', $currency_code)
                    ->orWhere('coupon.rebate_type', 2);
            });
        }
        $result = $query->get();
        $data = [];
        foreach ($result as $key => $item) {
            $data[$key] = $item->toArray();
            $data[$key]['coupon_name'] = $item->coupon->coupon_name;
            $data[$key]['coupon_price'] = $item->coupon->coupon_price;
            $data[$key]['coupon_use_price'] = $item->coupon->coupon_use_price;
            $data[$key]['currency_code'] = $item->coupon->currency_code;
            $data[$key]['rebate_type'] = $item->coupon->rebate_type;
        }
        return $data;
    }

    /**
     * @function 获取正在发放的页面领取优惠券
     * @param $currency_code
     * @return array
     */
    public static function getProductCoupons($currency_code)
    {
        $now = Carbon::now();
        return Coupon::where('coupon_purpose', 1)
            ->where('coupon_grant_startdate', '<=', $now)
            ->where('coupon_grant_enddate', '>', $now)
            ->where(function ($query) use ($currency_code) {
                return $query->where('currency_code', $currency_code)
                    ->orWhere('rebate_type', 2);
            })
            ->get();
    }

    public static function getCouponByIds($couponIds)
    {
        return Coupon::whereIn('id', $couponIds)
            ->get();
    }

    public static function getCouponCodeInfo($couponId, $userId)
    {
        $now = Carbon::now()->toDateTimeString();
        return Code::where('id', $couponId)
            ->where('user_id', $userId)
            ->where('code_use_status', 1)
            ->where('code_used_start_date', '<=', $now)
            ->where('code_used_end_date', '>=', $now)
            ->first();
    }

    public static function getUserCouponCodeInfo($couponId, $userId)
    {
        return Code::where('coupon_id', $couponId)
            ->where('user_id', $userId)
            ->first();
    }

    public static function getCouponCodeInfoById($couponId)
    {
        return Code::with('coupon')
            ->where('coupon_code.id', $couponId)
            ->where('coupon_code.code_use_status', 1)
            ->first();
    }

    public static function changCouponCodeStatus($couponCodeId)
    {
        return Code::where('id', $couponCodeId)
            ->update(['code_use_status' => 2, 'code_used_at' => Carbon::now()->toDateTimeString()]);
    }

    public static function couponCodeReset($couponCodeId)
    {
        return Code::where('id', $couponCodeId)
            ->update(['code_use_status' => 1, 'code_used_at' => null]);
    }

    public static function getCouponCount($userId)
    {
        $now = Carbon::now();
        return Code::where('user_id', $userId)
            ->where('code_use_status', 1)
            ->where('code_used_start_date', '<=', $now)
            ->where('code_used_end_date', '>', $now)
            ->count(['id']);
    }

    /**
     * @function 获取用户未使用的所有优惠券
     * @param $userId
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function couponCodeList($userId)
    {
        $now = Carbon::now();
        return Code::with('coupon')
            ->where('coupon_code.user_id', $userId)
            ->where('coupon_code.code_use_status', 1)
            ->where('coupon_code.code_used_start_date', '<=', $now)
            ->where('coupon_code.code_used_end_date', '>', $now)
            ->orderByDesc('coupon_code.created_at')
            ->paginate(10);
    }

    public static function getCouponInfo($couponId)
    {
        return Coupon::where('id', $couponId)->first();
    }

    public static function couponCodeInsert($couponCode)
    {
        return Code::create($couponCode);
    }

    public static function addCouponReceiveNumber($couponId)
    {
        return Coupon::where('id', $couponId)->increment('receive_number');
    }

    public static function getCouponInfoByKey($couponKey)
    {
        return Coupon::where('coupon_key', $couponKey)->first();
    }

    public static function couponCodeAll($userId)
    {
        return Code::where('user_id', $userId)->get();
    }

    public static function getNewbeeCoupon()
    {
        $now = Carbon::now()->toDateTimeString();
        return Coupon::where('coupon_purpose', 3)
            ->where(function ($query) use ($now) {
                $query->where('use_type', 1)
                    ->orWhere([['coupon_grant_startdate', '<=', $now], ['coupon_grant_enddate', '>=', $now]]);
            })->get();
    }
}