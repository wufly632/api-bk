<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/5
 * Time: 下午7:49
 */

namespace App\Modules\Promotions\Services;

use App\Models\Currency;
use App\Modules\Coupon\Repositories\CouponRepository;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Promotions\Repositories\PromotionsRepository;
use App\Services\ApiResponse;
use Carbon\Carbon;


class PromotionsService
{
    public static function getActivePromotion($currency_code)
    {
        return PromotionsRepository::getActivePromotion($currency_code);
    }

    public static function checkSkusInPromotion($promotionId, $skuIds)
    {
        return PromotionsRepository::checkSkusInPromotion($promotionId, $skuIds);
    }

    public static function getPromotionByProductId($productId, $currency_code)
    {
        return PromotionsRepository::getPromotionByProductId($productId, $currency_code);
    }

    public static function getPromotionSkusById($promotionsId, $productId)
    {
        return PromotionsRepository::getPromotionSkusById($promotionsId, $productId);
    }

    public static function getPromotionInfo($promotionId)
    {
        return PromotionsRepository::getPromotionInfo($promotionId);
    }

    public static function currency($currency_code)
    {
        return Currency::where('currency_code', $currency_code)->first();
    }

    public static function promotionShow($promotionInfo, $currency)
    {
        $result = [];
        $promotionData = [];
        $couponData = [];
        $goodData = [];
        $currency = Currency::where('currency_code', $promotionInfo->currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'the promotion dose not exists');
        }
        $promotionGoods = PromotionsRepository::getPromotionProductList($promotionInfo->id);
        $promotionMsg = self::getPromotionMsg($promotionInfo);
        $promotionData['promotion_start'] = $promotionInfo->start_at;
        $end_at = $promotionInfo->end_at;
        if ($promotionInfo->activity_cycle != 0) {
            $end_at_time = strtotime($promotionInfo->end_at);
            if (($end_at_time - time()) > $promotionInfo->activity_cycle * 3600) {
                $end_at_timestamp = ($end_at_time - time()) % ($promotionInfo->activity_cycle * 3600);
                $end_at = date('Y-m-d H:i:s', time() + $end_at_timestamp);
            }
        }

        $promotionData['promotion_end'] = $end_at;
        $promotionData['promotion_msg'] = $promotionMsg;
        $promotionData['promotion_h5_banner'] = $promotionInfo->h5_poster_pic;
        $promotionData['promotion_pc_banner'] = $promotionInfo->poster_pic;
        $promotionData['promotion_title'] = $promotionInfo->title;
        $goodIds = array_pluck($promotionGoods->items(), 'goods_id');
        $goods = ProductsRepository::getProductByIds($goodIds);
        $promotionGoodSkus = PromotionsRepository::getPromotionSkusByProductIds($promotionInfo->id, $goodIds);
        if ($goods->count()) {
            $goodData = self::matchGoodSku($goods, $promotionGoodSkus, $currency);
        }
        if ($promotionInfo->activity_type == 'return') {
            $role = json_decode($promotionInfo->rule, true);
            $coupons = CouponRepository::getCouponByIds(explode(',', $role['ids']));
            foreach ($coupons as $coupon) {
                $couponTmp = [
                    'id'              => $coupon->id,
                    'currency_symbol' => Currency::getSymbolByCode($coupon->currency_code),
                    'price'           => round($coupon->coupon_price, $currency->digit),
                    'use_price'       => round($coupon->coupon_use_price, $currency->digit),
                    'type'            => $coupon->rebate_type
                ];
                if ($coupon->use_type == 2) {
                    $couponTmp['startdate'] = $coupon->coupon_use_startdate;
                    $couponTmp['enddate'] = $coupon->coupon_use_enddate;
                } else {
                    $couponTmp['startdate'] = date('Y-m-d H:i:s');
                    $couponTmp['enddate'] = date('Y-m-d H:i:s', strtotime("{$coupon->use_days} day"));
                }
                $couponData[] = $couponTmp;
            }
        }
        $result['goods'] = $goodData;
        $result['coupons'] = $couponData;
        $result['promotions'] = $promotionData;
        $result['activityType'] = $promotionInfo->activity_type;
        $result['total_page'] = $promotionGoods->lastPage();
        return $result;
    }

    public static function matchGoodSku($goods, $promotionGoodSkus, $currency)
    {
        $goodData = [];
        foreach ($goods as $good) {
            $goodTmp = [
                'id'              => $good->id,
                'img'             => cdnUrl($good->main_pic),
                'img_type'        => cdnUrl($good->pic_type),
                'name'            => $good->good_en_title,
                'currency_symbol' => $currency->symbol,
                'origin_price'    => round($good->origin_price * $currency->rate, $currency->digit),
                'price'           => round($good->price * $currency->rate, $currency->digit),
                'stock'           => $good->good_stock,
                'discount'        => round((1 - round($good->price / $good->origin_price,
                                4)) * 100) . '%',
            ];
            foreach ($promotionGoodSkus as $promotionGoodSku) {
                if ($good->id == $promotionGoodSku->goods_id) {
                    if ($goodTmp['price'] > round($promotionGoodSku->price * $currency->rate, $currency->digit)) {
                        $goodTmp['origin_price'] = $goodTmp['price'];
                        $goodTmp['price'] = round($promotionGoodSku->price * $currency->rate, $currency->digit);
                    }
                }
            }
            $goodTmp['discount'] = strval(abs(floor(($goodTmp['price'] - $goodTmp['origin_price']) / $goodTmp['origin_price'] * 100))) . '%';
            $goodTmp['rebate'] = round($good->rebate_level_one * $goodTmp['price'] / 100, $currency->digit);
            $goodData[] = $goodTmp;
        }
        return $goodData;
    }

    /**
     * 获取活动商品
     * @param $promotionId
     * @param $currencyCode
     * @return array
     * @throws \Exception
     */
    public static function getPromotionGood($promotionId, $currencyCode)
    {
        $promotionGoods = PromotionsRepository::getLimitPromotionProduct($promotionId);
        $goodIds = array_slice(array_pluck($promotionGoods->toArray(), 'goods_id'), 0, 10);
        $goods = ProductsRepository::getProductByIds($goodIds);
        $promotionGoodSkus = PromotionsRepository::getPromotionSkusByProductIds($promotionId, $goodIds);
        $currency = self::currency($currencyCode);
        if (!$currency) {
            throw new \Exception('the promotion dose not exists');
        }
        $goodData = self::matchGoodSku($goods, $promotionGoodSkus, $currency);
        return $goodData;
    }

    public static function getPromotion($currencyCode)
    {
        $now = Carbon::now()->toDateTimeString();
        $option = [
            ['show_at', '<=', $now],
            ['end_at', '>', $now],
            ['currency_code', $currencyCode],
            ['activity_type', 'limit']
        ];

        return PromotionsRepository::getPromotion($option)->first();
    }

    /**
     * @function 获取所有促销商品信息
     * @param $ids
     * @return \Illuminate\Support\Collection
     */
    public static function getProductInfoByIds($ids)
    {
        return PromotionsRepository::getPromotionProductsInfo($ids);
    }

    /**
     * @function 获取所有促销商品sku信息
     * @param $ids
     * @return \Illuminate\Support\Collection
     */
    public static function getProductSkuInfoByIds($ids)
    {
        return PromotionsRepository::getPromotionProductSkusInfo($ids);
    }

    public static function getPromotionMsg($promotionInfo)
    {
        $promotionMsg = '';
        $activityType = $promotionInfo->activity_type;
        //促销规则
        $rule = array();
        if ($promotionInfo->rule) {
            switch ($activityType) {
                case 'give':
                case 'limit':
                case 'quantity':
                    $rule = $promotionInfo->rule;
                    break;
                case 'reduce': //满减
                case 'return': //满返
                case 'discount': //多件多折
                case 'wholesale': //X元n件
                    $rule = json_decode($promotionInfo->rule);
                    break;
            }
        }
        switch ($activityType) {
            case 'reduce': //满减
                if ($rule) {
                    $promotionMsg = '';
                    foreach ($rule as $reduceValue) {
                        $promotionMsg .= 'BUY $' . $reduceValue->money . ' GET $' . $reduceValue->reduce . ' OFF,';
                    }
                    if ($promotionMsg) {
                        $promotionMsg = substr($promotionMsg, 0, -1);
                    }
                }
                break;
            case 'return': //满返
                $consumePrice = $promotionInfo->consume;
                if ($consumePrice) {
                    $consumePrice = ceil($consumePrice);
                    if ($rule) {
                        $promotionMsg = 'Receive $' . $consumePrice . ' Back up to $' . $rule->value;
                    }
                }
                break;
            case 'discount': //多件多折
                if ($rule) {
                    $promotionMsg = '';
                    foreach ($rule as $discountValue) {
                        $promotionMsg .= 'BUY ' . $discountValue->num . ' GET ' . bcmul(bcsub(10,
                                $discountValue->discount, 2), 10, 0) . '% OFF,';
                    }
                    if ($promotionMsg) {
                        $promotionMsg = substr($promotionMsg, 0, -1);
                    }
                }
                break;
            case 'wholesale': //X元n件
                if ($rule) {
                    $promotionMsg = '';
                    foreach ($rule as $wholesaleValue) {
                        $promotionMsg .= "ANY " . $wholesaleValue->wholesale . ' FOR $' . $wholesaleValue->money . ",";
                    }
                    if ($promotionMsg) {
                        $promotionMsg = substr($promotionMsg, 0, -1);
                    }
                }
                break;
            case 'give': //买n免1
                if ($rule) {
                    $promotionMsg = 'BUY ' . $rule . ' GET 1 FREE';
                }
                break;
            case 'limit': //限时特价
                $promotionMsg = 'FLASH SALE';
                if (isset($promotionInfo->limitMaxMinusPrice)) {
                    $promotionMsg = "FLASH SALE";
                }
                break;
            case 'quantity': //限量秒杀
                $promotionMsg = 'LIMIT';
                if ($promotionInfo->quantityMinSeckillPrice) {
                    $promotionMsg = "LIMIT FOR $ {$promotionInfo->quantityMinSeckillPrice}";
                }
                break;
            case '':
                $promotionMsg = $promotionInfo->title;
                break;
        }
        return $promotionMsg;
    }
}