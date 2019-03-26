<?php

namespace App\Modules\Carts\Http\Controllers;

use App\Models\Currency;
use App\Modules\Carts\Services\CartsService;
use App\Modules\Coupon\Repositories\CouponRepository;
use App\Modules\Coupon\Services\CouponService;
use App\Modules\Products\Services\ProductsService;
use App\Modules\Promotions\Services\PromotionsService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class CartsController extends Controller
{
    public function __construct()
    {
        // header("Access-Control-Allow-Origin: *"); // 允许任意域名发起的跨域请求
    }

    /**
     * Display a listing of the resource.
     * @param string $token
     * @param string $currency_code
     * @return Response
     */
    public function index($token = '', $currency_code = '')
    {
        if (!$currency_code) {
            $currency_code = \request()->input('currency_code', 'USD');
        }
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        if (!$token) {
            $token = \request()->input('token');
        }
        $isLogin = Cache::get($token) ? true : false;
        $user_id = UsersService::getUserId($token);
        $carts = CartsService::getCartDetails($user_id);
        $coupons = $isLogin ? CouponService::getActivityCode($user_id, $currency_code) : [];
        $integral = 0;
        // 查询所有正在进行的促销活动
        $promotions = PromotionsService::getActivePromotion($currency_code);
        $data = CartsService::calculate($carts, $coupons, $integral, $promotions, $user_id, $currency);
        $data['token'] = $token;
        $data['currency_integral_money'] = 0;
        $data['usd_price'] = ProductsService::getAddPrice();
        return ApiResponse::success($data);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function add(Request $request)
    {
        $token = $request->input('token', '');
        $currency_code = \request()->input('currency_code', '');
        $userId = 0;
        if (!$token) {
            $tokenResult = UsersService::generateGuestUser();
            $userId = $tokenResult['user_id'];
            $token = $tokenResult['token'];
        }
        $result = CartsService::addGoods($request, $userId, 'add');
        if (!$result) {
            return ApiResponse::failure(g_API_ERROR, 'Cart update failed');
        }
        return $this->index($token, $currency_code);
    }

    /**
     * @function 购物车商品数量加减
     * @param Request $request
     * @return Response|mixed
     */
    public function changeNum(Request $request)
    {
        $token = $request->input('token', '');
        $currency_code = \request()->input('currency_code', '');
        $userId = 0;
        if (!$token) {
            $tokenResult = UsersService::generateGuestUser();
            $userId = $tokenResult['user_id'];
            $token = $tokenResult['token'];
        }
        $result = CartsService::addGoods($request, $userId, 'update');
        if (!$result) {
            return ApiResponse::failure(g_API_ERROR, 'Cart update failed');
        }
        $result = $this->index($token, $currency_code);
        if ($result['status'] == 200) {
            $result['content']['update_num'] = $request->input('update_num', 1);
        }
        return $result;
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function delete(Request $request)
    {
        $currency_code = \request()->input('currency_code', '');
        $userId = UsersService::getUserId();
        $cartId = $request->input('cart_id', 0);
        if (!$cartId) {
            return ApiResponse::failure(g_API_ERROR, 'Cart id can not nul null');
        }
        $cartInfo = CartsService::getCartById($cartId);
        if (!$cartInfo || $cartInfo->user_id != $userId) {
            return ApiResponse::failure(g_API_ERROR, 'Cart info dose not exists');
        }
        $result = CartsService::cartDelete($cartId);
        if (!$result) {
            return ApiResponse::failure(g_API_ERROR, 'Cart delete failed');
        }
        return $this->index('', $currency_code);
    }

    public function cartInfo(Request $request)
    {
        $currency_code = \request()->input('currency_code') ? \request()->input('currency_code') : 'USD';
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $goodInfo = json_decode(json_encode($request->input('good_info', [])));
        $promotions = '';
        $carts = [];
        if (!empty($goodInfo)) {
            $promotions = PromotionsService::getActivePromotion($currency_code);
            $carts = CartsService::getCartDetailsByRequest($goodInfo);
        }
        $data = CartsService::calculate($carts, [], 0, $promotions, 0, $currency);
        return ApiResponse::success($data);
    }

    public function sync(Request $request)
    {
        $userId = UsersService::getUserId();
        $goodInfo = $request->input('good_info', []);
        if ($goodInfo) {
            CartsService::cartSync($userId, $goodInfo);
        }
        return self::index();
    }

    /**
     * 购物车提交
     * @param Request $request
     * @return mixed
     */
    public function checkout(Request $request)
    {
        $userId = UsersService::getUserId();
        $carts = CartsService::getCartInfo($userId);
        $sku_num = $carts->pluck('num', 'sku_id')->sum();
        if (!$sku_num) {
            return ApiResponse::failure(g_API_ERROR, 'There are currently no items in your Shopping Cart');
        }
        $currency_code = isset($request->currency_code) ? $request->currency_code : 'USD';
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $couponId = $request->input('coupon_id', 0);
        $carts = self::index('', $currency_code);
        $goods = $carts['content']['goods'];
        $promotions = collect($carts['content']['promotion'])->pluck('goods')->collapse()->toArray();
        $result['ordergoods'] = array_merge($goods, $promotions);
        $finalAccount = array_sum(array_map(function ($val) {
            return $val['num'] * $val['price'];
        }, $result['ordergoods']));
        // 促销活动优惠金额
        $finalAccount -= $carts['content']['specialoffer'];
        $address = UsersService::getDefaultAddress($userId);
        $cards = UsersService::getCards($userId);
        $addressData = [];
        $cardData = [];
        $cartGoodsPreferAmount = 0.00;
        $has_default_address = collect($address)->where('is_default', 1)->first() ? true : false;
        if ($address->count()) {
            foreach ($address as $addressItem) {
                $addressTmp = [
                    'id'         => $addressItem->id,
                    'recipients' => "{$addressItem->firstname} {$addressItem->lastname}",
                    'address'    => "{$addressItem->country} {$addressItem->state} {$addressItem->city} {$addressItem->street_address} {$addressItem->suburb}",
                    'iphone'     => $addressItem->phone,
                    'is_default' => $addressItem->is_default
                ];
                $addressData[] = $addressTmp;
            }
            if (!$has_default_address && isset($addressData[0]['is_default'])) {
                $addressData[0]['is_default'] = 1;
            }
        }
        if ($cards->count()) {
            foreach ($cards as $card) {
                $cardTmp = [
                    'id'     => $card->id,
                    'number' => $card->hidden_card_number
                ];
                $cardData[] = $cardTmp;
            }
        }
        //使用优惠券
        if ($couponId) {
            $couponCodeInfo = CouponRepository::getCouponCodeInfoById($couponId);
            if ($couponCodeInfo) {
                //优惠金额
                $couponPrice = $couponCodeInfo->coupon->coupon_price;
                //使用条件
                $couponUsePrice = $couponCodeInfo->coupon->coupon_use_price;
                //如果成交价大于等于使用条件,可以使用优惠券
                if ($finalAccount >= $couponUsePrice) {
                    $orderPost['code_price'] = $couponPrice;
                    switch ($couponCodeInfo->coupon->rebate_type) {
                        case 1:// 面额券
                            if ($couponCodeInfo->coupon->currency_code == $currency_code) {
                                //优惠价
                                $cartGoodsPreferAmount += round($finalAccount + $couponPrice, $currency->digit);
                                //成交价
                                $finalAccount = round($finalAccount - $couponPrice, $currency->digit);
                                $finalAccount = $finalAccount > 0 ? $finalAccount : 0.01;
                            }
                            break;
                        case 2: //折扣券
                            //优惠价
                            $cartGoodsPreferAmount += round($finalAccount * $couponPrice / 100, $currency->digit);
                            //成交价
                            $finalAccount = round($finalAccount * (1 - $couponPrice / 100), $currency->digit);
                            $finalAccount = $finalAccount > 0 ? $finalAccount : 0.01;
                            break;
                    }
                } else {
                    $couponId = 0;
                }
            }

        }
        // 判断是否包邮
        if ($sku_num >= env('THRESHOLD_NUM', 3)) {
            $fare = 0;
        } else {
            $fare = $currency->fare;
        }
        $result['price'] = round($finalAccount, $currency->digit);
        $result['shipping'] = $fare;
        $result['user_address'] = $addressData;
        $result['money'] = UsersService::getUserInfo($userId)->amount_money ?? 0;
        $result['money'] = round($result['money'] * $currency->rate, $currency->digit);
        $result['cards'] = $cardData;
        $result['currency_symbol'] = $currency->symbol;
        $result['session_id'] = strtoupper(\Session::getId());
        $result['org_id'] = env('CYBS_ORG_ID', '');
        \Cache::set(CYBS_PAY_SESSION_ID . '_' . $userId, $result['session_id'], 30);
        $result['session_id'] = env('CYBS_MERCHANT_ID') . $result['session_id'];
        $result['usd_price'] = round($result['price'] / $currency->rate, $currency->digit);
        return ApiResponse::success($result);
    }


    /**
     * 请求购物车商品数量
     * @return mixed
     */
    public function counts()
    {
        $userId = UsersService::getUserId();
        $counts = CartsService::getCartProductsCounts($userId);
        return ApiResponse::success($counts);
    }

}
