<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8
 * Time: 17:42
 */

namespace App\Modules\Carts\Services;


use App\Assistants\CLogger;
use App\Models\Currency;
use App\Models\Customer\Carts;
use App\Models\User\Token;
use App\Modules\Carts\Repositories\CartsRepository;
use App\Modules\Orders\Repositories\OrdersRepository;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Products\Repositories\ProductsSkuRepository;
use App\Modules\Products\Repositories\ProductsStockRepository;
use App\Modules\Promotions\Services\PromotionsService;
use App\Modules\Users\Services\UsersService;
use App\User;
use Illuminate\Support\Facades\DB;

class CartsService
{
    /**
     * 获取购物车商品信息
     * @param $user_id
     * @return array
     */
    public static function getCartDetails($user_id)
    {
        $carts = self::getCartInfo($user_id);//obj
        if ($carts->count()) {
            $goodIds = array_pluck($carts, 'good_id');
            $skuIds = array_pluck($carts, 'sku_id');
            $goodInfos = ProductsStockRepository::getProductsByIds($goodIds);
            $skuInfos = ProductsStockRepository::getSkuStockBySkuIds($skuIds);
            foreach ($carts as $key => $cart) {
                foreach ($skuInfos as $skuInfo) {
                    if ($skuInfo->id == $cart->sku_id) {
                        if ($skuInfo->good_stock > 0) {
                            $carts[$key]->is_sold_out = 0;
                        } else {
                            $carts[$key]->is_sold_out = 1;
                        }
                        $carts[$key]->good_img = $skuInfo->icon;
                        $carts[$key]->good_stock = $skuInfo->good_stock;
                        break;
                    }
                }
                foreach ($goodInfos as $goodInfo) {
                    if ($goodInfo->id == $cart->good_id) {
                        $carts[$key]->good_name = $goodInfo->good_en_title;
                        $carts[$key]->return_ratio = $goodInfo->rebate_level_one . '%';
                        if ($goodInfo->status != 1) {
                            $carts[$key]->is_sold_out = 1;
                        }
                        break;
                    }
                }
            }
        }
        return $carts ? $carts : [];
    }

    /**
     * @param $carts
     * @param $coupons
     * @param $integral
     * @param $promotions
     * @param $userId
     * @return array
     */
    public static function calculate($carts, $coupons, $integral, $promotions, $userId, $currency)
    {
        $cartInfo = [];
        $cartInfo['integral'] = $integral;
        $goodData = [];
        $couponData = [];
        //活动优惠金额
        $promotionsTotalPrice = '0.00';
        //促销活动商品总价
        $promotionsGoodTotalPrice = [];
        //促销活动商品总数
        $promotionsGoodTotal = [];
        //促销商品数组(商品+购买数量)
        $promotionsGood = [];
        $promotionSkuNumPrice = [];
        //促销活动
        $promotion = [];
        $cartGoodIds = [];
        if ($carts) {
            $cartGoodIds = array_pluck($carts, 'good_id');
            $cartSkuIds = array_pluck($carts, 'sku_id');
            $goodSkuInfos = ProductsSkuRepository::getSkuByIds($cartSkuIds);
            $goodSkuOriginPrices = array_pluck($goodSkuInfos, 'origin_price', 'id');
            $goodSkuInfos = array_pluck($goodSkuInfos, 'price', 'id');
            $promotion_ids = array_pluck($promotions, 'id');
            //获取促销活动的所有商品及sku信息
            $promotionProducts = PromotionsService::getProductInfoByIds($promotion_ids);
            $promotionProductSkus = PromotionsService::getProductSkuInfoByIds($promotion_ids);
            $promotion_goods = array_pluck($promotionProducts, null, 'goods_id');
            $promotion_good_skus = array_pluck($promotionProductSkus, null, 'sku_id');
            // 获取每个商品的购买数量
            $buy_num = [];
            foreach ($carts as $cart) {
                $buy_num[$cart->good_id] = $buy_num[$cart->good_id] ?? 0;
                $buy_num[$cart->good_id] += $cart->num;
            }
            foreach ($carts as $cart) {
                $setPicType = $cart->goodDetailInfo->pic_type;
                //判断是否在促销活动sku里
                if (isset($promotion_good_skus[$cart->sku_id])) {
                    // 判断是否超出限购
                    //查询当前用户、当前活动、当前商品已购物的数量
                    $promotionBuyGoods = OrdersRepository::getUserBuyGoodNum($promotion_good_skus[$cart->sku_id]->activity_id,
                        $userId, $cart->good_id);
                    if ($promotion_goods[$cart->good_id]->per_num > 0 && $promotion_goods[$cart->good_id]->per_num < ($promotionBuyGoods + $buy_num[$cart->good_id])) {
                        // 全部按售价
                        $goodData[] = [
                            'cart_id'         => isset($cart->id) ? $cart->id : 0,
                            'id'              => $cart->good_id,
                            'name'            => $cart->good_name,
                            'img'             => $cart->good_img,
                            'stock'           => $cart->good_stock,
                            'sku_id'          => $cart->sku_id,
                            'pic_type'        => $setPicType,
                            'is_sold_out'     => $cart->is_sold_out,
                            'currency_symbol' => $currency->symbol,
                            'price'           => round($goodSkuInfos[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'origin_price'    => round($goodSkuOriginPrices[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'num'             => $cart->num,
                            'props'           => json_decode($cart->attr_value, true),
                            'return_ratio'    => $cart->return_ratio,
                        ];
                        continue;
                    }

                    if (!isset($promotion[$promotion_good_skus[$cart->sku_id]->activity_id])) {
                        $promotion_tmp = [];
                        $promotion_tmp['promotion_id'] = $promotion_good_skus[$cart->sku_id]->activity_id;
                        $promotion_tmp['promotion_msg'] = '';
                        // 以促销sku价格为准
                        $promotion_tmp['goods'][] = [
                            'cart_id'         => isset($cart->id) ? $cart->id : 0,
                            'id'              => $cart->good_id,
                            'name'            => $cart->good_name,
                            'img'             => $cart->good_img,
                            'pic_type'        => $setPicType,
                            'stock'           => $cart->good_stock,
                            'sku_id'          => $cart->sku_id,
                            'is_sold_out'     => $cart->is_sold_out,
                            'currency_symbol' => $currency->symbol,
                            'price'           => round($promotion_good_skus[$cart->sku_id]->price * $currency->rate,
                                $currency->digit),
                            'origin_price'    => round($goodSkuOriginPrices[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'num'             => $cart->num,
                            'props'           => json_decode($cart->attr_value, true),
                            'return_ratio'    => $cart->return_ratio,
                        ];
                        $promotion[$promotion_good_skus[$cart->sku_id]->activity_id] = $promotion_tmp;
                    } else {
                        $promotion_goods_tmp = [
                            'cart_id'         => isset($cart->id) ? $cart->id : 0,
                            'id'              => $cart->good_id,
                            'name'            => $cart->good_name,
                            'img'             => $cart->good_img,
                            'pic_type'        => $setPicType,
                            'stock'           => $cart->good_stock,
                            'sku_id'          => $cart->sku_id,
                            'is_sold_out'     => $cart->is_sold_out,
                            'currency_symbol' => $currency->symbol,
                            'price'           => round($goodSkuInfos[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'origin_price'    => round($goodSkuOriginPrices[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'num'             => $cart->num,
                            'props'           => json_decode($cart->attr_value, true),
                            'return_ratio'    => $cart->return_ratio,
                        ];
                        $promotion[$promotion_good_skus[$cart->sku_id]->activity_id]['goods'][] = $promotion_goods_tmp;
                    }
                    $promotionsGoodTotalPrice[$promotion_good_skus[$cart->sku_id]->activity_id] = $promotionsGoodTotalPrice[$promotion_good_skus[$cart->sku_id]->activity_id] ?? 0;
                    $promotionsGoodTotalPrice[$promotion_good_skus[$cart->sku_id]->activity_id] += round($goodSkuInfos[$cart->sku_id] * $currency->rate,
                            $currency->digit) * $cart->num;
                    $promotionsGoodTotal[$promotion_good_skus[$cart->sku_id]->activity_id] = $promotionsGoodTotal[$promotion_good_skus[$cart->sku_id]->activity_id] ?? 0;
                    $promotionsGoodTotal[$promotion_good_skus[$cart->sku_id]->activity_id] += $cart->num;
                    if (isset($promotionSkuNumPrice[$cart->sku_id])) {
                        $promotionSkuNumPrice[$cart->sku_id]['num'] += $cart->num;
                    } else {
                        $promotionSkuNumPrice[$cart->sku_id]['num'] = $cart->num;
                        $promotionSkuNumPrice[$cart->sku_id]['price'] = $cart->unit_price;
                    }
                } elseif (isset($promotion_goods[$cart->good_id])) {
                    // 判断是否超出限购
                    //查询当前用户、当前活动、当前商品已购物的数量
                    $promotionBuyGoods = OrdersRepository::getUserBuyGoodNum($promotion_goods[$cart->good_id]->activity_id,
                        $userId, $cart->good_id);
                    if ($promotion_goods[$cart->good_id]->per_num > 0 && $promotion_goods[$cart->good_id]->per_num < ($promotionBuyGoods + $buy_num[$cart->good_id])) {
                        // 全部按售价
                        $goodData[] = [
                            'cart_id'         => isset($cart->id) ? $cart->id : 0,
                            'id'              => $cart->good_id,
                            'name'            => $cart->good_name,
                            'img'             => $cart->good_img,
                            'pic_type'        => $setPicType,
                            'stock'           => $cart->good_stock,
                            'sku_id'          => $cart->sku_id,
                            'is_sold_out'     => $cart->is_sold_out,
                            'currency_symbol' => $currency->symbol,
                            'price'           => round($goodSkuInfos[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'origin_price'    => round($goodSkuOriginPrices[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'num'             => $cart->num,
                            'props'           => json_decode($cart->attr_value, true),
                            'return_ratio'    => $cart->return_ratio,
                        ];
                        continue;
                    }
                    if (!isset($promotion[$promotion_goods[$cart->good_id]->activity_id])) {
                        $promotion_tmp = [];
                        $promotion_tmp['promotion_id'] = $promotion_goods[$cart->good_id]->activity_id;
                        $promotion_tmp['promotion_msg'] = '';
                        // 先保存商品原价
                        $promotion_tmp['goods'][] = [
                            'cart_id'         => isset($cart->id) ? $cart->id : 0,
                            'id'              => $cart->good_id,
                            'name'            => $cart->good_name,
                            'img'             => $cart->good_img,
                            'pic_type'        => $setPicType,
                            'stock'           => $cart->good_stock,
                            'sku_id'          => $cart->sku_id,
                            'is_sold_out'     => $cart->is_sold_out,
                            'currency_symbol' => $currency->symbol,
                            'price'           => round($goodSkuInfos[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'origin_price'    => round($goodSkuOriginPrices[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'num'             => $cart->num,
                            'props'           => json_decode($cart->attr_value, true),
                            'return_ratio'    => $cart->return_ratio,
                        ];
                        $promotion[$promotion_goods[$cart->good_id]->activity_id] = $promotion_tmp;
                    } else {
                        $promotion_goods_tmp = [
                            'cart_id'         => isset($cart->id) ? $cart->id : 0,
                            'id'              => $cart->good_id,
                            'name'            => $cart->good_name,
                            'img'             => $cart->good_img,
                            'pic_type'        => $setPicType,
                            'stock'           => $cart->good_stock,
                            'sku_id'          => $cart->sku_id,
                            'is_sold_out'     => $cart->is_sold_out,
                            'currency_symbol' => $currency->symbol,
                            'price'           => round($goodSkuInfos[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'origin_price'    => round($goodSkuOriginPrices[$cart->sku_id] * $currency->rate,
                                $currency->digit),
                            'num'             => $cart->num,
                            'props'           => json_decode($cart->attr_value, true),
                            'return_ratio'    => $cart->return_ratio,
                        ];
                        $promotion[$promotion_goods[$cart->good_id]->activity_id]['goods'][] = $promotion_goods_tmp;
                    }
                    $promotionsGoodTotalPrice[$promotion_goods[$cart->good_id]->activity_id] = $promotionsGoodTotalPrice[$promotion_goods[$cart->good_id]->activity_id] ?? 0;
                    $promotionsGoodTotalPrice[$promotion_goods[$cart->good_id]->activity_id] += round($goodSkuInfos[$cart->sku_id] * $currency->rate,
                            $currency->digit) * $cart->num;
                    $promotionsGoodTotal[$promotion_goods[$cart->good_id]->activity_id] = $promotionsGoodTotal[$promotion_goods[$cart->good_id]->activity_id] ?? 0;
                    $promotionsGoodTotal[$promotion_goods[$cart->good_id]->activity_id] += $cart->num;
                    if (isset($promotionSkuNumPrice[$cart->sku_id])) {
                        $promotionSkuNumPrice[$cart->sku_id]['num'] += $cart->num;
                    } else {
                        $promotionSkuNumPrice[$cart->sku_id]['num'] = $cart->num;
                        $promotionSkuNumPrice[$cart->sku_id]['price'] = $cart->unit_price;
                    }
                } else {
                    // 没有促销活动
                    $goodData[] = [
                        'cart_id'         => isset($cart->id) ? $cart->id : 0,
                        'id'              => $cart->good_id,
                        'name'            => $cart->good_name,
                        'img'             => $cart->good_img,
                        'stock'           => $cart->good_stock,
                        'pic_type'        => $setPicType,
                        'sku_id'          => $cart->sku_id,
                        'is_sold_out'     => $cart->is_sold_out,
                        'currency_symbol' => $currency->symbol,
                        'price'           => round($goodSkuInfos[$cart->sku_id] * $currency->rate, $currency->digit),
                        'origin_price'    => round($goodSkuOriginPrices[$cart->sku_id] * $currency->rate,
                            $currency->digit),
                        'num'             => $cart->num,
                        'props'           => json_decode($cart->attr_value, true),
                        'return_ratio'    => $cart->return_ratio,
                    ];
                }
            }
            $promotionPromitionIds = array_keys($promotion);
            foreach ($promotionPromitionIds as $promotionPromitionId) {
                $promotion_activity = array_pluck($promotions, null, 'id')[$promotionPromitionId];
                switch ($promotion_activity->activity_type) {
                    case 'reduce': //满减
                        $reduceInfo = getReduce($promotionsGoodTotalPrice[$promotionPromitionId], $promotion_activity);
                        $reducePrice = isset($reduceInfo['reduce']) ? $reduceInfo['reduce'] : '0.00';//满减金额
                        $promotion[$promotionPromitionId]['promotion_msg'] = isset($reduceInfo['reduceMsg']) ? $reduceInfo['reduceMsg'] : '';//满减说明
                        //活动优惠总金额
                        $promotionsTotalPrice += round($promotionsTotalPrice + $reducePrice, $currency->digit);
                        break;
                    case 'return': //满返
                        $returnInfo = getReturn($promotionsGoodTotalPrice[$promotionPromitionId], $promotion_activity);
                        $promotion[$promotionPromitionId]['promotion_msg'] = isset($returnInfo['returnMsg']) ? $returnInfo['returnMsg'] : '';//满减说明
                        break;
                    case 'discount': //多件多折
                        $discountInfo = getDiscount($promotionsGoodTotal[$promotionPromitionId],
                            $promotionsGoodTotalPrice[$promotionPromitionId], $promotion_activity);
                        $discountPrice = isset($discountInfo['discount']) ? $discountInfo['discount'] : '0.00';//多件多折优惠金额
                        $promotion[$promotionPromitionId]['promotion_msg'] = isset($discountInfo['discountMsg']) ? $discountInfo['discountMsg'] : '';//多件多折活动说明
                        //活动优惠总金额
                        $promotionsTotalPrice = round(($promotionsTotalPrice + $discountPrice), $currency->digit);
                        break;
                    case 'wholesale': //X元n件
                        $wholesaleInfo = getWholesale($promotionsGoodTotal[$promotionPromitionId],
                            $promotionsGoodTotalPrice[$promotionPromitionId], $promotionSkuNumPrice,
                            $promotion_activity);
                        $wholesalePrice = isset($wholesaleInfo['wholesale']) ? $wholesaleInfo['wholesale'] : '0.00';//X元n件优惠金额
                        $promotion[$promotionPromitionId]['promotion_msg'] = isset($wholesaleInfo['wholesaleMsg']) ? $wholesaleInfo['wholesaleMsg'] : '';//X元n件活动说明
                        //活动优惠总金额
                        $promotionsTotalPrice = round($promotionsTotalPrice + $wholesalePrice, $currency->digit);
                        break;
                    case 'give': //买n免1
                        $giveInfo = getGive($promotionsGoodTotal[$promotionPromitionId], $promotionSkuNumPrice,
                            $promotion_activity);
                        $givePrice = isset($giveInfo['give']) ? $giveInfo['give'] : '0.00';//买n免1优惠金额
                        $promotion[$promotionPromitionId]['promotion_msg'] = isset($giveInfo['giveMsg']) ? $giveInfo['giveMsg'] : '';//买n免1活动说明
                        //活动优惠总金额
                        $promotionsTotalPrice = round($promotionsTotalPrice + $givePrice, $currency->digit);
                        break;
                    case 'limit': // 限时特价
                        $promotion[$promotionPromitionId]['promotion_msg'] = 'FLASH SALE';
                        break;
                    case 'quantity':
                        $promotion[$promotionPromitionId]['promotion_msg'] = 'LIMIT FOR $' . $promotions->rule;
                        break;
                    case '':
                        $promotion[$promotionPromitionId]['promotion_msg'] = $promotion_activity->title;
                        break;
                }
            }

        }

        if ($coupons) {
            foreach ($coupons as $coupon) {
                $coupon = (object)$coupon;
                $couponTmp = [
                    'id'              => $coupon->id,
                    'type'            => $coupon->rebate_type,
                    'currency_symbol' => Currency::getSymbolByCode($coupon->currency_code),
                    'price'           => $coupon->coupon_price,
                    'use_price'       => $coupon->coupon_use_price,
                    'startdate'       => date('M,d,Y', strtotime($coupon->code_used_start_date)),
                    'enddate'         => date('M,d,Y', strtotime($coupon->code_used_end_date))
                ];
                $couponData[] = $couponTmp;
            }
        }
        $cartInfo['goods'] = $goodData;
        $cartInfo['promotion'] = array_values($promotion);
        $cartInfo['integral'] = $integral;
        $cartInfo['specialoffer'] = $promotionsTotalPrice;
        $cartInfo['coupon'] = $couponData;
        return $cartInfo;
    }

    public static function addGoods($request, $userId = 0, $type = 'update')
    {
        $goodId = $request->input('good_id', 0);
        $skuId = $request->input('sku_id', 0);
        $num = $request->input('num');
        $localTime = $request->input('date', null);
        if (!isset($num)) {
            return false;
        }
        if (ProductsRepository::getProductInfo($goodId)) {
            if (ProductsStockRepository::getSkuStock($skuId) >= $num) {
                if (!$userId) {
                    $userId = UsersService::getUserId();
                }
                try {
                    DB::beginTransaction();
                    $result = CartsRepository::updateOrCreate($userId, $goodId, $skuId, $num, $localTime, $type);
                    if ($result) {
                        if ($result !== true) {
                            $skuInfo = ProductsSkuRepository::getSkuById($skuId);
                            $cartsData = [
                                'value_ids'   => $skuInfo->value_ids,
                                'unit_price'  => $skuInfo->price,
                                'total_price' => $skuInfo->price * $num
                            ];
                            $attrs = ProductsRepository::getAttrAndValuesByIds(explode(',', $skuInfo->value_ids));
                            $attrValues = [];
                            foreach ($attrs as $attr) {
                                $attrValues[$attr->attr_name] = $attr->value_name;
                            }
                            $cartsData['attr_value'] = json_encode($attrValues);
                            CartsRepository::updateAttr($result, $cartsData);
                        }
                        DB::commit();
                        return true;
                    }
                } catch (\Exception $exception) {
                    ding($userId . '加入购物车失败-' . $exception->getMessage());
                    DB::rollBack();
                    return false;
                }
            }
        }
        return false;
    }

    public static function getCartInfo($userId)
    {
        return CartsRepository::getCarts($userId);
    }

    public static function getCartDetailsByRequest($carts)
    {
        if ($carts) {
            $goodIds = array_pluck($carts, 'good_id');
            $skuIds = array_pluck($carts, 'sku_id');
            $goodInfos = ProductsStockRepository::getProductsByIds($goodIds);
            $skuInfos = ProductsStockRepository::getSkuStockBySkuIds($skuIds);
            foreach ($carts as $key => $cart) {
                foreach ($skuInfos as $skuInfo) {
                    if ($skuInfo->id == $cart->sku_id) {
                        if ($skuInfo->good_stock > 0) {
                            $carts[$key]->is_sold_out = 0;
                        } else {
                            $carts[$key]->is_sold_out = 1;
                        }
                        $carts[$key]->good_img = $skuInfo->icon;
                        $carts[$key]->good_stock = $skuInfo->good_stock;
                        $carts[$key]->unit_price = $skuInfo->price;
                        $attrs = ProductsRepository::getAttrAndValuesByIds(explode(',', $skuInfo->value_ids));
                        $attrValues = [];
                        foreach ($attrs as $attr) {
                            $attrValues[$attr->attr_name] = $attr->value_name;
                        }
                        $carts[$key]->attr_value = json_encode($attrValues);
                        break;
                    }
                }
                foreach ($goodInfos as $goodInfo) {
                    if ($goodInfo->id == $cart->good_id) {
                        $carts[$key]->good_name = $goodInfo->good_en_title;
                        $carts[$key]->return_ratio = $goodInfo->rebate_level_one . '%';
                        if ($goodInfo->status != 1) {
                            $carts[$key]->is_sold_out = 1;
                        }
                        break;
                    }
                }
            }
        }
        return $carts ? $carts : [];
    }

    public static function cartSync($userId, $goodInfo)
    {
        try {
            DB::beginTransaction();
            foreach ($goodInfo as $good) {
                $productInfo = ProductsRepository::getProductInfo($good['good_id']);
                if (!$productInfo) {
                    continue;
                }
                $skuInfo = ProductsSkuRepository::getSkuById($good['sku_id']);
                if (!$skuInfo) {
                    continue;
                }
                if ($skuInfo->good_id != $good['good_id']) {
                    continue;
                }
                if ($good['num'] <= 0) {
                    continue;
                }
                $num = $good['num'] > $skuInfo->good_stock ? $skuInfo->good_stock : $good['num'];
                $result = CartsRepository::updateOrCreate($userId, $good['good_id'], $good['sku_id'], $num, null);
                if ($result) {
                    if ($result !== true) {
                        $cartsData = [
                            'value_ids'   => $skuInfo->value_ids,
                            'unit_price'  => $skuInfo->supply_price,
                            'total_price' => $skuInfo->price * $num
                        ];
                        $attrs = ProductsRepository::getAttrAndValuesByIds(explode(',', $skuInfo->value_ids));
                        $attrValues = [];
                        foreach ($attrs as $attr) {
                            $attrValues[$attr->attr_name] = $attr->value_name;
                        }
                        $cartsData['attr_value'] = json_encode($attrValues);
                        CartsRepository::updateAttr($result, $cartsData);
                    }
                }
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            CLogger::getLogger('cart-sync', 'carts')->info($exception->getMessage());
            return false;
        }
    }

    /**
     * if guest login,transfer guest cart
     * @param $old_user_id
     * @param $user_id
     */
    public static function transferCarts($old_user_id, $user_id)
    {
        //justify old_user_id is guest?
        $oldUserInfo = UsersService::getUserInfo($old_user_id);
        if ($oldUserInfo->registered) {
            return;
        }
        //cart
        $guest_cart = Carts::where("user_id", $old_user_id)->where('valid_status', \CartStatus::NOTPAY)->count();
        if ($guest_cart) {
            $user_cart = Carts::where("user_id", $user_id)->where('valid_status', \CartStatus::NOTPAY)->count();
            if ($user_cart) {
                $user_cart_records = Carts::where("user_id", $user_id)
                    ->where("valid_status", \CartStatus::NOTPAY)->get();
                //删除游客用户和已登录用户购物车中完全相同的sku
                foreach ($user_cart_records as $user_cart_record) {
                    $sku_id = $user_cart_record->sku_id;
                    $product_id = $user_cart_record->good_id;
                    Carts::where("user_id", $old_user_id)->where("valid_status", \CartStatus::NOTPAY)
                        ->where('sku_id', $sku_id)->where('good_id', $product_id)
                        ->delete();
                }
            }
        } else {
            Carts::where('user_id', $old_user_id)->delete();
        }
        Carts::where('user_id', $old_user_id)
            ->update(["user_id" => $user_id]);
        User::destroy($old_user_id);
        Token::where('user_id', $old_user_id)->delete();
    }

    public static function getCartById($cartId)
    {
        return CartsRepository::getCartById($cartId);
    }

    public static function cartDelete($cartId)
    {
        return CartsRepository::destroy([$cartId]);
    }

    public static function getCartProductsCounts($userId)
    {
        return CartsRepository::getCartProductsCounts($userId);
    }
}
