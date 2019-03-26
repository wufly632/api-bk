<?php

namespace App\Modules\Products\Http\Controllers;

use App\Models\Currency;
use App\Models\Product\CodProduct;
use App\Modules\Coupon\Services\CouponService;
use App\Modules\Products\Services\AuditProductsService;
use App\Modules\Products\Services\ProductsService;
use App\Modules\Promotions\Services\PromotionsService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $result = ProductsService::getList($request);
        if ($result['status'] != 200) {
            return $result;
        }
        return ApiResponse::success($result['content']);
    }

    /**
     * Show the specified resource.
     * @return Response
     */
    public function show(Request $request)
    {
        $productId = $request->input('id', 0);
        // 判断是否为cod模式
        if ($request->input('cod', 0) == 1) {
            $cod_goods = CodProduct::query()->get()->pluck('good_id')->toArray();
            if ( !in_array($productId, $cod_goods)) {
                return ApiResponse::failure(g_API_URL_NOTFOUND, 'product does not exist');
            }
            return $this->codShow($productId);
        }
        $currency_code = isset($request['currency_code']) ? $request['currency_code'] : 'USD';
        $currency      = Currency::where('currency_code', $currency_code)->first();
        if ( !$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $productInfo = ProductsService::getProductInfo($productId);
        if ( !$productInfo) return ApiResponse::failure(g_API_URL_NOTFOUND, 'product does not exist');
        $productSkus   = ProductsService::getSKuInfo($productId);
        $promotions    = PromotionsService::getPromotionByProductId($productId, $currency_code);
        $promotionSkus = [];
        if ($promotions) $promotionSkus = PromotionsService::getPromotionSkusById($promotions->id, $productId);
        $coupon      = CouponService::getProductCoupons($currency_code);
        $token       = $request->input('token', '');
        $userCoupons = [];
        if ($token) {
            $userId     = UsersService::getUserId();
            $userCoupon = CouponService::userCouponCodeAll($userId);
            if ($userCoupon->count()) {
                $userCoupons = array_pluck($userCoupon->toArray(), 'coupon_id');
            }
        }
        $productAttrs = ProductsService::getProductAttrs($productId, $productInfo->category_id);
        // dd($productAttrs);
        $cateAttr = ProductsService::getCateDetailAttr($productInfo->category_id);
        $date     = ProductsService::detailCalculate($productInfo, $productSkus, $promotions, $promotionSkus, $coupon, $productAttrs, $cateAttr, $userCoupons, $currency);
        return ApiResponse::success($date);
    }

    /**
     * @function cod模式商品详情
     * @param $productId
     * @return mixed
     */
    private function codShow($productId)
    {
        $currency_code = \request()->input('currency_code', 'USD');
        $currency      = Currency::where('currency_code', $currency_code)->first();
        if ( !$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $productInfo = ProductsService::getCodProductInfo($productId);
        if ( !$productInfo) return ApiResponse::failure(g_API_URL_NOTFOUND, 'product does not exist!');
        $productSkus  = ProductsService::getSKuInfo($productId);
        $productAttrs = ProductsService::getProductAttrs($productId, $productInfo->category_id);
        $cateAttr     = ProductsService::getCateDetailAttr($productInfo->category_id);
        $data         = ProductsService::codDetailCalculate($productInfo, $productSkus, $productAttrs, $cateAttr, $currency);
        return ApiResponse::success($data);
    }
}
