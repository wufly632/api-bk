<?php


namespace App\Modules\Promotions\Repositories;


use App\Models\Promotion\Activity;
use App\Models\Promotion\ActivityGood;
use App\Models\Promotion\ActivityGoodsSku;
use Illuminate\Support\Carbon;

class PromotionsRepository
{
    /**
     * 获取活动
     * @param $currency_code
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getActivePromotion($currency_code)
    {
        $now = Carbon::now();
        return Activity::where('start_at', '<=', $now)
            ->where('end_at', '>', $now)
            ->where('currency_code', $currency_code)
            ->get();
    }

    public static function checkSkusInPromotion($promotionIds, $skuIds)
    {
        return ActivityGoodsSku::whereIn('activity_id', $promotionIds)
            ->whereIn('sku_id', $skuIds)
            ->get();
    }

    /**
     * 获取商品参与的活动
     * @param $productId
     * @param $currency_code
     * @return mixed
     */
    public static function getPromotionByProductId($productId, $currency_code)
    {
        $now = Carbon::now();
        return Activity::where('promotions_activity.start_at', '<=', $now)
            ->where('promotions_activity.end_at', '>', $now)
            ->whereHas('promotionGoods', function ($query) use ($productId) {
                $query->where('goods_id', $productId);
            })
            ->where('promotions_activity.currency_code', $currency_code)
            ->first();
    }

    /**
     * 获取参加活动的sku
     * @param $promotionsId
     * @param $productId
     * @return mixed
     */
    public static function getPromotionSkusById($promotionsId, $productId)
    {
        return ActivityGoodsSku::where('activity_id', $promotionsId)
            ->where('goods_id', $productId)
            ->get();
    }

    /**
     *
     * @param $promotionsId
     * @param $productIds
     * @return mixed
     */
    public static function getPromotionSkusByProductIds($promotionsId, $productIds)
    {
        return ActivityGoodsSku::where('activity_id', $promotionsId)
            ->whereIn('goods_id', $productIds)
            ->get();
    }

    /**
     * 获取活动商品详情
     * @param $promotionId
     * @param $productIds
     * @return mixed
     */
    public static function getPromotionProductInfo($promotionId, $productIds)
    {
        return ActivityGood::where('activity_id', $promotionId)
            ->whereIn('goods_id', $productIds)
            ->get();
    }


    /**
     * 获取活动详情
     * @param $promotionId
     * @return mixed
     */
    public static function getPromotionInfo($promotionId)
    {
        return Activity::find($promotionId);
    }

    public static function getPromotionProductList($promotionId)
    {
        return ActivityGood::leftJoin('goods as g', 'g.id', '=', 'promotions_activity_goods.goods_id')
            ->where('promotions_activity_goods.activity_id', $promotionId)
            ->where('g.status', 1)
            ->orderByDesc('promotions_activity_goods.sort')
            ->paginate(20);
    }

    public static function getLimitPromotionProduct($promotionId)
    {
        return ActivityGood::where('activity_id', $promotionId)
            ->limit(10)->get();
    }

    public static function addProductBuyNum($promotionId, $promotionDatas)
    {
        foreach ($promotionDatas as $key => $promotionData) {
            ActivityGood::where('activity_id', $promotionId)
                ->where('goods_id', $key)
                ->increment('buy_num', $promotionData);
        }
    }

    public static function getReturnPromotionByIds($promotionIds)
    {
        return Activity::whereIn('id', $promotionIds)->where('activity_type', 'return')->get();
    }

    public static function subProductBuyNum($promotionId, $promotionDatas)
    {
        foreach ($promotionDatas as $key => $promotionData) {
            ActivityGood::where('activity_id', $promotionId)
                ->where('goods_id', $key)
                ->increment('buy_num', $promotionData);
        }
    }

    /**
     * 根据条件获取活动
     * @param $options
     * @param $inOption
     * @param $betweenOption
     * @return \Illuminate\Support\Collection
     */
    public static function getPromotion($options, $inOption = [], $betweenOption = [])
    {
        $query = Activity::select(['*']);
        if ($options) {
            foreach ($options as $option) {
                if (count($option) == 2) {
                    $query = $query->where($option[0], $option[1]);
                } else {
                    $query = $query->where($option[0], $option[1], $option[2]);
                }
            }
        }
        if ($inOption) {
            $query = $query->whereIn($inOption[0], [$inOption[1], $inOption[2]]);
        }
        if ($betweenOption) {
            $query = $query->whereBetween($betweenOption[0], [$betweenOption[1], $betweenOption[2]]);
        }
        return $query->get();
    }

    /**
     * @function 获取所有的促销活动商品
     * @param $promotionIds
     * @return \Illuminate\Support\Collection
     */
    public static function getPromotionProductsInfo($promotionIds)
    {
        return ActivityGood::whereIn('activity_id', $promotionIds)
            ->get();
    }

    /**
     * @function 获取所有的促销活动商品sku
     * @param $promotionIds
     * @return \Illuminate\Support\Collection
     */
    public static function getPromotionProductSkusInfo($promotionIds)
    {
        return ActivityGoodsSku::whereIn('activity_id', $promotionIds)
            ->get();
    }
}