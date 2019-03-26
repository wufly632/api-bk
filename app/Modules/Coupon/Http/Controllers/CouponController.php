<?php

namespace App\Modules\Coupon\Http\Controllers;

use App\Models\Currency;
use App\Modules\Coupon\Services\CouponService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function receive(Request $request)
    {
        $couponId = $request->input('id', 0);
        if ($couponId) {
            $couponInfo = CouponService::getCouponInfo($couponId);
            if (!$couponInfo) return ApiResponse::failure(g_API_ERROR, 'The Coupon dose not exists');
            $userId = UsersService::getUserId();
            $result = CouponService::couponValidator($couponInfo, $userId);
            if ($result) return $result;
            if (!CouponService::couponReceive($couponInfo, $userId)) {
                return ApiResponse::failure(g_API_ERROR, 'Coupon receive failed');
            }
            return ApiResponse::success('');
        }
        return ApiResponse::failure(g_API_ERROR, 'The Coupon dose not exists');
    }

    public function apply(Request $request)
    {
        $couponKey = $request->input('key', 0);
        if ($couponKey) {
            $couponInfo = CouponService::getCouponInfoByKey($couponKey);
            if (!$couponInfo) return ApiResponse::failure(g_API_ERROR, 'The Coupon dose not exists');
            $userId = UsersService::getUserId();
            $result = CouponService::couponValidator($couponInfo, $userId);
            if ($result) return $result;
            if (!CouponService::couponReceive($couponInfo, $userId)) {
                return ApiResponse::failure(g_API_ERROR, 'Coupon receive failed');
            }
            $coupons = CouponService::getActivityCode($userId, $request->input('currency_code', ''));
            $couponData = [];
            foreach ($coupons as $coupon) {
                $coupon = (object)$coupon;
                $couponTmp = [
                    'id' => $coupon->id,
                    'currency_symbol' => Currency::getSymbolByCode($coupon->currency_code),
                    'price' => $coupon->coupon_price,
                    'type' => $coupon->rebate_type,
                    'use_price' => $coupon->coupon_use_price,
                    'startdata' => date('M,d,Y', strtotime($coupon->code_used_start_date)),
                    'enddate' => date('M,d,Y', strtotime($coupon->code_used_end_date))
                ];
                $couponData[] = $couponTmp;
            }
            return ApiResponse::success(['coupons' => $couponData]);
        }
        return ApiResponse::failure(g_API_ERROR, 'The key can not be null');
    }

}
