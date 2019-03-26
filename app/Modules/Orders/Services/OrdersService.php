<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/18
 * Time: 下午4:11
 */

namespace App\Modules\Orders\Services;


use App\Assistants\CLogger;
use App\Models\Currency;
use App\Models\Order\CodOrder;
use App\Models\Order\CodOrderAddress;
use App\Models\Order\CodOrderGood;
use App\Modules\Carts\Repositories\CartsRepository;
use App\Modules\Carts\Services\CartsService;
use App\Modules\Coupon\Repositories\CouponRepository;
use App\Modules\Orders\Repositories\CustomerFinanceLogRepository;
use App\Modules\Orders\Repositories\CustomerIntegralLogRepository;
use App\Modules\Orders\Repositories\CustomerOrderAddressRepository;
use App\Modules\Orders\Repositories\CustomerOrderPaymentsRepository;
use App\Modules\Orders\Repositories\OrderGoodsRepository;
use App\Modules\Orders\Repositories\OrdersRepository;
use App\Modules\Orders\Repositories\RequirementsRepository;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Products\Repositories\ProductsSkuRepository;
use App\Modules\Promotions\Repositories\PromotionsRepository;
use App\Modules\Promotions\Services\PromotionsService;
use App\Modules\Users\Repositories\AddressRepository;
use App\Modules\Users\Repositories\CurrencyRepository;
use App\Modules\Users\Repositories\CustomerIncomeRepository;
use App\Modules\Users\Repositories\UserCardsAddressRepository;
use App\Modules\Users\Repositories\UserCardsRepository;
use App\Modules\Users\Repositories\UserRepository;
use App\Modules\Users\Services\CustomerIncomeService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Support\Carbon;
use function GuzzleHttp\Psr7\parse_query;
use Illuminate\Support\Facades\DB;

class OrdersService
{
    /**
     * 订单生成前数据过滤（购物车是否为空、优惠券是否可用）
     * @param $request
     * @param $carts
     * @return bool
     */
    public static function checkoutValidator($request, $carts)
    {
        $userId = UsersService::getUserId();
        $couponId = $request->input('coupon_id', 0);
        $goodIds = array_pluck($carts, 'good_id');
        $skuIds = array_pluck($carts, 'sku_id');
        if ($couponId && !$couponInfo = CouponRepository::getCouponCodeInfo($couponId, $userId)) {
            return false;
        }
        $goodInfos = ProductsRepository::getProductByIds($goodIds);
        $skuInfos = ProductsSkuRepository::getSkuByIds($skuIds);
        if (count(array_unique($goodIds)) != count($goodInfos)) {
            return false;
        }
        if (count(array_unique($skuIds)) != count($skuInfos)) {
            return false;
        }
        return true;
    }

    public static function orderPayValidator($request)
    {
        $userId = UsersService::getUserId();
        $orderId = $request->input('order_id', '');
        $addressId = $request->input('address_id', 0);
        $balance = $request->input('balance', false);
        $payType = $request->input('pay_type');
        $source = $request->input('source', '');
        $orderInfo = OrdersRepository::getOrderInfoByOrderId($orderId);
        if (!$orderId || !$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->is_del) {
            return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        }

        if ($orderInfo->status != 1 || $orderInfo->created_at <= date('Y-m-d H:i:s', strtotime('-30 minute'))) {
            return ApiResponse::failure(g_API_ERROR, 'This order can not be pay');
        }
        $addressInfo = AddressRepository::getAddressInfo($addressId);
        if (!$addressId || !$addressInfo || $addressInfo->user_id != $userId) {
            return ApiResponse::failure(g_API_ERROR, 'Address dose not exists');
        }
        if (!in_array($payType, [2, 3])) {
            return ApiResponse::failure(g_API_ERROR, 'Please choose the Payment Method');
        }
        if ($payType == 3) {
            if ($source) {
                $cardInfo = UserCardsRepository::getCardInfoByCardToken($userId, $source);
                if (!$cardInfo) {
                    return ApiResponse::failure(g_API_ERROR, 'Card dose not exists');
                }
                $number = $cardInfo->card_number;
            } else {
                $number = $request->input('number', '');
            }
            $cvc = $request->input('cvc', '');
            $exp = $request->input('exp', '/');
            $expArr = explode('/', $exp);
            $exp_year = isset($expArr[1]) ? $expArr[1] : '';
            $exp_month = isset($expArr[0]) ? $expArr[0] : '';
            if (!$number || !$exp_month || !$exp_year || !$cvc) {
                return ApiResponse::failure(g_API_ERROR, 'Card info error');
            }

        }
        return false;
    }

    /**
     * @function 购物车支付信息验证
     * @param $request
     * @return bool|mixed
     */
    public static function cartPayValidator($request)
    {
        $userId = UsersService::getUserId();
        $carts = CartsService::getCartInfo($userId);
        if (!$carts->count()) {
            return ApiResponse::failure(g_API_ERROR, 'There are currently no items in your Shopping Cart');
        }
        $currency_code = isset($request->currency_code) ? $request->currency_code : 'USD';
        $currency = CurrencyRepository::getByCurrencyCode($currency_code);
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        if (!OrdersService::checkoutValidator($request, $carts)) {
            return ApiResponse::failure(g_API_ERROR, 'Information error, order failed');
        }
        if (!OrdersService::productStockValidator($carts)) {
            return ApiResponse::failure(g_API_ERROR, 'Insufficient inventory, order failed');
        }
        $addressId = $request->input('address_id', 0);
        $payType = $request->input('pay_type');
        $source = $request->input('source', '');
        $addressInfo = AddressRepository::getAddressInfo($addressId);
        if (!$addressId || !$addressInfo || $addressInfo->user_id != $userId) {
            return ApiResponse::failure(g_API_ERROR, 'Address dose not exists');
        }
        if (!in_array($payType, [2, 3])) {
            return ApiResponse::failure(g_API_ERROR, 'Please choose the Payment Method');
        }
        if ($payType == 3) {
            if ($source) {
                $cardInfo = UserCardsRepository::getCardInfoByCardToken($userId, $source);
                if (!$cardInfo) {
                    return ApiResponse::failure(g_API_ERROR, 'Card dose not exists');
                }
                $number = $cardInfo->card_number;
            } else {
                $number = $request->input('number', '');
            }
            $cvc = $request->input('cvc', '');
            $exp = $request->input('exp', '/');
            $expArr = explode('/', $exp);
            $exp_year = isset($expArr[1]) ? $expArr[1] : '';
            $exp_month = isset($expArr[0]) ? $expArr[0] : '';
            if (!$number || !$exp_month || !$exp_year || !$cvc) {
                return ApiResponse::failure(g_API_ERROR, 'Card info error');
            }

        }
        return false;
    }

    /**
     * 商品库存过滤
     * @param $carts
     * @return bool
     */
    public static function productStockValidator($carts)
    {
        $skuIds = array_pluck($carts, 'sku_id');
        $skuInfo = ProductsSkuRepository::getSkuByIds($skuIds);
        $skuStock = array_pluck($skuInfo, 'good_stock', 'id');
        foreach ($carts as $cart) {
            if ($cart->num > $skuStock[$cart->sku_id]) {
                return false;
            }
        }
        return true;
    }

    /**
     * 订单列表
     * @param $userId
     * @param $request
     * @return array
     */
    public static function orderList($userId, $request)
    {
        $request = $request->all();
        $status = isset($request['type']) ? $request['type'] : 0;
        $result = OrdersRepository::getList($userId, $status);
        $orders = $result->items();
        $orderIds = array_pluck($orders, 'order_id');
        $orderGoodInfo = OrderGoodsRepository::getOrderGoodInfoByOrderIds($orderIds);
        $skuIds = array_pluck($orderGoodInfo, 'sku_id');
        $skuInfos = ProductsRepository::getProductSkuInfoBySkus($skuIds);
        $orderData = [];
        foreach ($orders as $order) {
            $currency = CurrencyRepository::getByCurrencyCode($order->currency_code);
            $orderTmp = [];
            $orderTmp['orderid'] = $order->order_id;
            $orderTmp['orderstatus'] = $order->status == 3 ? 4 : $order->status;
            $orderTmp['ordertime'] = $order->created_at;
            $paytime = (time() - strtotime($order->created_at));
            $orderTmp['paytime'] = $paytime > 30 * 60 ? 0 : (30 * 60 - $paytime);
            $orderTmp['currency_symbol'] = $currency->symbol;
            $orderTmp['total_amount'] = round($order->final_price + $order->fare, $currency->digit);
            $orderTmp['integral'] = round(($order->final_price / $currency->rate) + ($order->fare / $currency->rate),
                0);
            $orderTmp['ordergoods'] = [];
            foreach ($orderGoodInfo as $orderGood) {
                $orderGoodTmp = [];
                if ($orderGood->order_id == $order->order_id) {
                    $orderGoodTmp['id'] = $orderGood->good_id;
                    $orderGoodTmp['price'] = $orderGood->unit_price;
                    $orderGoodTmp['num'] = $orderGood->num;
                    $orderGoodTmp['sku_value_ids'] = $orderGood->value_ids;
                    $orderGoodTmp['sku_value'] = json_decode($orderGood->attr_value, true);
                    foreach ($skuInfos as $skuInfo) {
                        if ($skuInfo->id == $orderGood->sku_id) {
                            $orderGoodTmp['name'] = $skuInfo->good_en_title;
                            $orderGoodTmp['img'] = $skuInfo->icon;
                            $orderGoodTmp['pic_type'] = $skuInfo->pic_type;
                            break;
                        }
                    }
                    $orderTmp['ordergoods'][] = $orderGoodTmp;
                }
            }
            $orderData[] = $orderTmp;
        }

        return array('orderData' => $orderData, 'total_page' => $result->lastPage());
    }

    /**
     * 订单详情
     * @param $orderInfo
     * @return array
     */
    public static function orderDetail($orderInfo)
    {
        $orderGoodInfo = OrderGoodsRepository::getOrderGoodInfoByOrderIds([$orderInfo->order_id]);
        $skuIds = array_pluck($orderGoodInfo, 'sku_id');
        $skuInfos = ProductsRepository::getProductSkuInfoBySkus($skuIds);
        // $addressInfo = AddressRepository::getAddressInfo($orderInfo->address_id);
        $addressInfo = CustomerOrderAddressRepository::getAddressByOrderId($orderInfo->order_id);
        $orderData = [];
        $orderData['orderid'] = $orderInfo->order_id;
        $orderData['orderstatus'] = $orderInfo->status;
        $orderData['ordertime'] = $orderInfo->created_at;
        $paytime = (time() - strtotime($orderInfo->created_at));
        $orderData['paytime'] = $paytime > 30 * 60 ? 0 : (30 * 60 - $paytime);
        $orderData['final_amount'] = $orderInfo->final_price;
        $orderData['shipping'] = $orderInfo->fare;
        $orderData['name'] = $addressInfo ? "{$addressInfo->firstname} {$addressInfo->lastname}" : '';
        $orderData['telephone'] = $addressInfo ? substr_replace($addressInfo->phone, '****', 3, 4) : "";
        $orderData['address'] = $addressInfo ? "{$addressInfo->country} {$addressInfo->state} {$addressInfo->city} {$addressInfo->street_address} {$addressInfo->suburb}" : '';
        $orderData['currency_symbol'] = Currency::getSymbolByCode($orderInfo->currency_code);
        $orderData['ordergoods'] = [];
        foreach ($orderGoodInfo as $orderGood) {
            $orderGoodTmp = [];
            if ($orderGood->order_id == $orderInfo->order_id) {
                $orderGoodTmp['id'] = $orderGood->good_id;
                $orderGoodTmp['price'] = $orderGood->unit_price;
                $orderGoodTmp['num'] = $orderGood->num;
                $orderGoodTmp['sku_value_ids'] = $orderGood->value_ids;
                $orderGoodTmp['sku_value'] = json_decode($orderGood->attr_value, true);
                foreach ($skuInfos as $skuInfo) {
                    if ($skuInfo->id == $orderGood->sku_id) {
                        $orderGoodTmp['name'] = $skuInfo->good_en_title;
                        $orderGoodTmp['img'] = $skuInfo->icon;
                        $orderGoodTmp['pic_type'] = $skuInfo->pic_type;
                        break;
                    }
                }
                $orderData['ordergoods'][] = $orderGoodTmp;
            }
        }
        return $orderData;
    }

    public static function deleteOrder($id)
    {
        return OrdersRepository::deleteOrder($id);
    }

    /**
     * @function 生成订单
     * @param $carts
     * @param $type
     * @param $couponId
     * @param $integral
     * @param $localDate
     * @param $currency
     * @param $address_id
     * @return bool|mixed
     */
    public static function createOrder($carts, $type, $couponId, $integral, $localDate, $currency, $address_id)
    {
        $now = Carbon::now()->toDateTimeString();
        //要修改
        $promotions = PromotionsRepository::getActivePromotion($currency->currency_code);
        $cartSkuIds = array_pluck($carts, 'sku_id');
        $goodSkuInfos = ProductsSkuRepository::getSkuByIds($cartSkuIds);
        $goodSkuInfos = array_pluck($goodSkuInfos, 'price', 'id');
        $orderData = self::orderTransfer($carts, $promotions, $goodSkuInfos, $type, $couponId, $integral, $currency);
        $userId = UsersService::getUserId();

        try {
            DB::beginTransaction();
            $orderData['order']['user_local_time'] = $localDate;
            $orderData['order']['currency_code'] = $currency->currency_code;
            CartsRepository::delete($orderData['carts']);
            OrdersRepository::createOrder($orderData['order']);
            OrdersRepository::createOrderGoods($orderData['orderGoods']);
            ProductsRepository::subProductStock($orderData['goodData']);
            ProductsRepository::subProductSkuStock($orderData['cartSKu']);
            ProductsRepository::subAuditProductStock($orderData['goodData']);
            ProductsRepository::subAuditProductSkuStock($orderData['cartSKu']);
            // 订单地址添加
            CustomerOrderAddressRepository::transformOrderAddress($orderData['order']['order_id'], $address_id);
            if ($orderData['promotionData']) {
                PromotionsRepository::addProductBuyNum($orderData['promotion'], $orderData['promotionData']);
            }
            if ($orderData['couponId']) {
                CouponRepository::changCouponCodeStatus($orderData['couponId']);
            }
            if ($orderData['integral']) {
                UserRepository::subIntegral($userId, $orderData['integral']);
                /* $integralLog = [
                    'notes_id' => account_sn($userId),
                    'integral' => $orderData['integral'],
                    'user_id' => $userId,
                    'order_id' => $orderData['order']['order_id'],
                    'created_at' => $now,
                    'operate_type' => 2
                ];
                CustomerIntegralLogRepository::addLog($integralLog);*/
            }
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            ding('用户' . $userId . '生成订单失败,' . $exception->getMessage());
            CLogger::getLogger('create-order', 'order')->info($exception->getMessage());
            return false;
        }
        return $orderData['order'];
    }

    /**
     * 订单数据拼装
     * @param $carts
     * @param $promotions
     * @param $goodSkus
     * @param $type
     * @param $couponId
     * @param $integral
     * @return array
     */
    private static function orderTransfer($carts, $promotions, $goodSkus, $type, $couponId, $integral, $currency)
    {
        $result = [];
        $userId = UsersService::getUserId();
        //订单信息
        $orderPost = array();
        //订单商品信息
        $orderGoodsPost = array();
        //商品信息
        $goodsData = array_pluck($carts, 'num', 'good_id');
        //商品sku信息
        $goodsSkuData = array_pluck($carts, 'num', 'sku_id');
        //购物车ID
        $cartIds = array_pluck($carts, 'id');
        //商品总价
        $cartGoodsAmount = '0.00';
        //商品优惠金额
        $cartGoodsPreferAmount = '0.00';
        //商品成交价
        $cartGoodsFinalAmount = '0.00';
        //运费
        $cartGoodsFareAmount = $currency->fare;
        //购物车商品ID
        $cartGoodIds = array_pluck($carts, 'good_id');
        //生成订单号
        $orderId = order_sn($userId, 1);
        //促销活动商品总价
        $promotionsGoodTotalPrices = [];
        //促销活动商品总数
        $promotionsGoodTotals = [];
        //促销商品的数量及价格
        $promotionGoodsNumPrices = [];
        //参加活动的促销商品数
        $promotionGoodData = [];
        $promotionGoodNum = [];
        $cartPromotionIds = [];
        $skuIds = array_pluck($carts, 'sku_id');
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
            //判断是否在促销活动sku里
            if (isset($promotion_good_skus[$cart->sku_id])) {
                // 判断是否超出限购
                //查询当前用户、当前活动、当前商品已购物的数量
                $promotionBuyGoods = OrdersRepository::getUserBuyGoodNum($promotion_good_skus[$cart->sku_id]->activity_id,
                    $userId, $cart->good_id);
                if ($promotion_goods[$cart->good_id]->per_num > 0 && $promotion_goods[$cart->good_id]->per_num < ($promotionBuyGoods + $buy_num[$cart->good_id])) {
                    // 全部按售价
                    $orderGoodsPost[] = [
                        'order_id'    => $orderId,
                        'good_id'     => $cart->good_id,
                        'sku_id'      => $cart->sku_id,
                        'value_ids'   => $cart->value_ids,
                        'attr_value'  => $cart->attr_value,
                        'unit_price'  => round($cart->unit_price * $currency->rate, $currency->digit),
                        'num'         => $cart->num,
                        'total_price' => round($cart->num * round($cart->unit_price * $currency->rate,
                                $currency->digit), $currency->digit),
                        'created_at'  => Carbon::now()->toDateTimeString()
                    ];
                    $cartGoodsAmount += round($cart->num * round($cart->unit_price * $currency->rate, $currency->digit),
                        $currency->digit);
                    continue;
                }
                $promotion_id = $promotion_good_skus[$cart->sku_id]->activity_id;
                $unit_price = round($promotion_good_skus[$cart->sku_id]->price * $currency->rate, $currency->digit);
                // 按促销价格计算
                $orderGoodsPost[] = [
                    'order_id'    => $orderId,
                    'good_id'     => $cart->good_id,
                    'sku_id'      => $cart->sku_id,
                    'value_ids'   => $cart->value_ids,
                    'attr_value'  => $cart->attr_value,
                    'unit_price'  => $unit_price,
                    'num'         => $cart->num,
                    'total_price' => round($cart->num * $unit_price, $currency->digit),
                    'activity_id' => $promotion_id,
                    'created_at'  => Carbon::now()->toDateTimeString()
                ];
                $promotionsGoodTotalPrices[$promotion_id] = $promotionsGoodTotalPrices[$promotion_id] ?? 0;
                $promotionsGoodTotalPrices[$promotion_id] += round($cart->num * $unit_price, $currency->digit);
                $promotionsGoodTotals[$promotion_id] = ($promotionsGoodTotals[$promotion_id] ?? 0) + $cart->num;
                $numPrice = ['num' => $cart->num, 'price' => $unit_price];
                $promotionGoodsNumPrices[$promotion_id][] = $numPrice;
                $cartGoodsAmount += round($cart->num * $unit_price, $currency->digit);
                continue;
            } elseif (isset($promotion_goods[$cart->good_id])) {
                // 判断是否超出限购
                //查询当前用户、当前活动、当前商品已购物的数量
                $promotionBuyGoods = OrdersRepository::getUserBuyGoodNum($promotion_goods[$cart->good_id]->activity_id,
                    $userId, $cart->good_id);
                if ($promotion_goods[$cart->good_id]->per_num > 0 && $promotion_goods[$cart->good_id]->per_num < ($promotionBuyGoods + $buy_num[$cart->good_id])) {
                    // 全部按售价
                    $orderGoodsPost[] = [
                        'order_id'    => $orderId,
                        'good_id'     => $cart->good_id,
                        'sku_id'      => $cart->sku_id,
                        'value_ids'   => $cart->value_ids,
                        'attr_value'  => $cart->attr_value,
                        'unit_price'  => round($cart->unit_price * $currency->rate, $currency->digit),
                        'num'         => $cart->num,
                        'total_price' => round($cart->num * round($cart->unit_price * $currency->rate,
                                $currency->digit), $currency->digit),
                        'created_at'  => Carbon::now()->toDateTimeString()
                    ];
                    $cartGoodsAmount += round($cart->num * round($cart->unit_price * $currency->rate, $currency->digit),
                        $currency->digit);
                    continue;
                }
                $promotion_id = $promotion_goods[$cart->good_id]->activity_id;
                $orderGoodsPost[] = [
                    'order_id'    => $orderId,
                    'good_id'     => $cart->good_id,
                    'sku_id'      => $cart->sku_id,
                    'value_ids'   => $cart->value_ids,
                    'attr_value'  => $cart->attr_value,
                    'unit_price'  => round($cart->unit_price * $currency->rate, $currency->digit),
                    'num'         => $cart->num,
                    'total_price' => round($cart->num * round($cart->unit_price * $currency->rate, $currency->digit),
                        $currency->digit),
                    'activity_id' => $promotion_id,
                    'created_at'  => Carbon::now()->toDateTimeString()
                ];
                $promotionsGoodTotalPrices[$promotion_id] = $promotionsGoodTotalPrices[$promotion_id] ?? 0;
                $promotionsGoodTotalPrices[$promotion_id] += round($cart->num * round($cart->unit_price * $currency->rate,
                        $currency->digit), $currency->digit);
                $promotionsGoodTotals[$promotion_id] = ($promotionsGoodTotals[$promotion_id] ?? 0) + $cart->num;
                $numPrice = ['num' => $cart->num, 'price' => $cart->unit_price];
                $promotionGoodsNumPrices[$promotion_id][] = $numPrice;
                $cartGoodsAmount += round($cart->num * round($cart->unit_price * $currency->rate, $currency->digit),
                    $currency->digit);
                continue;
            } else {
                // 没有促销活动
                // 全部按售价
                $orderGoodsPost[] = [
                    'order_id'    => $orderId,
                    'good_id'     => $cart->good_id,
                    'sku_id'      => $cart->sku_id,
                    'value_ids'   => $cart->value_ids,
                    'attr_value'  => $cart->attr_value,
                    'unit_price'  => round($cart->unit_price * $currency->rate, $currency->digit),
                    'num'         => $cart->num,
                    'total_price' => round($cart->num * round($cart->unit_price * $currency->rate, $currency->digit),
                        $currency->digit),
                    'created_at'  => Carbon::now()->toDateTimeString()
                ];
                $cartGoodsAmount += round($cart->num * round($cart->unit_price * $currency->rate, $currency->digit),
                    $currency->digit);
                continue;
            }
        }
        $promotions = array_pluck($promotions, null, 'id');
        // 计算优惠的金额
        foreach ($promotionsGoodTotalPrices as $promotion_id => $promotionsGoodTotalPrice) {
            $promotion = $promotions[$promotion_id];
            $promotionsGoodTotal = $promotionsGoodTotals[$promotion_id];
            $promotionGoodsNumPrice = $promotionGoodsNumPrices[$promotion_id];
            $cartGoodsPreferAmount += self::calculationPromotionPrice($promotion, $promotionsGoodTotalPrice,
                $promotionsGoodTotal, $promotionGoodsNumPrice);
        }

        $cartGoodsFinalAmount = round($cartGoodsAmount - $cartGoodsPreferAmount, $currency->digit);
        $cartGoodsFinalAmount = $cartGoodsFinalAmount > 0 ? $cartGoodsFinalAmount : 0;
        //使用优惠券
        if ($couponId) {
            $couponCodeInfo = CouponRepository::getCouponCodeInfoById($couponId)->toArray();
            if ($couponCodeInfo) {
                //优惠金额
                $couponPrice = $couponCodeInfo['coupon']['coupon_price'];
                //使用条件
                $couponUsePrice = $couponCodeInfo['coupon']['coupon_use_price'];
                //如果成交价大于等于使用条件,可以使用优惠券
                if ($cartGoodsFinalAmount >= $couponUsePrice) {
                    switch ($couponCodeInfo['coupon']['rebate_type']) {
                        case 1:// 面额券
                            if ($couponCodeInfo['coupon']['currency_code'] == $currency->currency_code) {
                                //优惠价
                                $cartGoodsPreferAmount += round($cartGoodsPreferAmount + $couponPrice,
                                    $currency->digit);
                                //成交价
                                $cartGoodsFinalAmount = round($cartGoodsFinalAmount - $couponPrice, $currency->digit);
                                $cartGoodsFinalAmount = $cartGoodsFinalAmount > 0 ? $cartGoodsFinalAmount : 0.01;
                                $orderPost['code_price'] = $couponPrice;
                            }
                            break;
                        case 2: //折扣券
                            //优惠价
                            $cartGoodsPreferAmount += round($cartGoodsFinalAmount * ($couponPrice / 100),
                                $currency->digit);
                            //成交价
                            $cartGoodsFinalAmount = round($cartGoodsFinalAmount * (1 - $couponPrice / 100),
                                $currency->digit);
                            $cartGoodsFinalAmount = $cartGoodsFinalAmount > 0 ? $cartGoodsFinalAmount : 0.01;
                            $orderPost['code_price'] = round($cartGoodsFinalAmount * ($couponPrice / 100),
                                $currency->digit);
                            break;
                    }
                } else {
                    $couponId = 0;
                }
            }
        }
        //使用积分(用户流水表)
        $useIntegral = 0;
        $useIntegralPrice = 0;
        if ($integral) {
            $integral = UsersService::getUserInfo($userId)->integral;
            if ($integral > 0) {
                $useIntegral = $integral;
                //比率
                $integralRatio = SYS_INTEGRAL_RATIO;
                //积分抵换金额
                $integralPrice = round($integral * $currency->rate * $integralRatio, $currency->digit);

                //成交价
                $cartGoodsFinalAmountBefore = $cartGoodsFinalAmount;
                $cartGoodsFinalAmount = round($cartGoodsFinalAmount - $integralPrice, $currency->digit);
                $cartGoodsFinalAmount = $cartGoodsFinalAmount > 0 ? $cartGoodsFinalAmount : 0.01;
                //如果积分抵换的金额小于等于成交价,扣掉所有积分;否则扣掉成交价对等的积分
                if ($integralPrice > $cartGoodsFinalAmountBefore) {
                    $useIntegral = round(($cartGoodsFinalAmountBefore / $currency->rate) / $integralRatio, 0);
                }
                $useIntegralPrice = round(($useIntegral * $currency->rate) * $integralRatio, $currency->digit);
                //优惠价
                $cartGoodsPreferAmount = round($cartGoodsPreferAmount + $useIntegralPrice, $currency->digit);
            }
        }
        if (array_sum($goodsSkuData) >= env('THRESHOLD_NUM', 3)) {
            $cartGoodsFareAmount = 0;
        }
        //订单信息
        $orderPost['order_id'] = $orderId; //订单号
        $orderPost['customer_id'] = $userId; //当前账号ID
        $orderPost['total_price'] = $cartGoodsAmount; //订单总额
        $orderPost['prefer_price'] = $cartGoodsPreferAmount; //订单优惠价格
        $orderPost['iso_code'] = $currency->currency_code;
        $orderPost['fare'] = $cartGoodsFareAmount; //订单运费
        $orderPost['final_price'] = $cartGoodsFinalAmount > 0 ? $cartGoodsFinalAmount : 0.01; //成交价
        $orderPost['pay_type'] = 0; //支付方式
        $orderPost['address_id'] = 0; //收货地址ID
        $orderPost['from_type'] = $type; //单订来源，1：PC端，2：H5
        $orderPost['status'] = 1; //订单状态 1-待付款 3-待发货 4-待收货 5-交易完成 6-交易取消
        $orderPost['code_id'] = $couponId;//使用的券码表ID
        $orderPost['integral'] = $useIntegral;//使用的积分
        $orderPost['shipper_code'] = ''; //快递公司编码
        $orderPost['waybill_id'] = ''; //运单号
        $orderPost['message'] = ''; //买家留言
        $orderPost['created_at'] = date('Y-m-d H:i:s', time()); //下单时间
        $orderPost['updated_at'] = date('Y-m-d H:i:s', time()); //修改时间
        //添加订单信息
        $result['order'] = $orderPost;
        //添加订单商品信息
        $result['orderGoods'] = $orderGoodsPost;
        //修改商品库存
        $result['goodData'] = $goodsData;
        //修改商品sku库存
        $result['cartSKu'] = $goodsSkuData;
        //修改促销商品库存
        $result['promotionData'] = $promotionGoodData;
        //修改优惠券码信息
        $result['couponId'] = $couponId;
        //修改用户附加信息
        $result['integral'] = $useIntegral;
        //删除购物车记录
        $result['carts'] = $cartIds;
        $result['promotion'] = isset($promotions->id) ? $promotions->id : 0;

        return $result;
    }

    public static function getOrderInfoByOrderId($orderId)
    {
        return OrdersRepository::getOrderInfoByOrderId($orderId);
    }

    public static function orderPay($request)
    {
        $orderId = $request->input('order_id');
        $addressId = $request->input('address_id');
        $balance = $request->input('balance', false) == 'true';
        $payType = $request->input('pay_type');
        $source = '';
        $orderInfo = OrdersRepository::getOrderInfoByOrderId($orderId);
        $currency = CurrencyRepository::getByCurrencyCode($orderInfo->currency_code);
        $finalAmount = round($orderInfo->final_price + $orderInfo->fare, $currency->digit);
        $orderData = self::payTransfer($orderInfo, $finalAmount, $payType, $balance);
        try {
            if ($orderData['finalAmount'] <= 0.6 && $orderData['payType'] == 3) {
                return ApiResponse::failure(g_API_ERROR, 'Please change the Payment Method');
            }
            if ($payType == 3) {
                $source = self::getStripePaySource($request);
                // dd($source);
                if (!$source) {
                    return ApiResponse::failure(g_API_ERROR, 'Card information error');
                }
                if (is_array($source)) {
                    $cardInfo = $source['card'];
                    $source = $source['token'];
                }
                $addressInfo = AddressRepository::getAddressInfo($addressId);
                try {
                    $afsResult = self::getCyberSourceResult($orderId, $request, $finalAmount, $addressInfo, $currency,
                        $cardInfo);
                    if (is_production() && $afsResult['cyberSource']->afsReply->reasonCode == 100 && strtoupper($afsResult['cyberSource']->decision) == 'REJECT') {
                        return ApiResponse::failure(g_API_ERROR,
                            'Risk control tips: There was en error completing your payment, please change your payment method and try again');
                    }
                    CLogger::getLogger('cybs-success', 'cybs')->info(json_encode($afsResult));
                } catch (\Exception $exception) {
                    CLogger::getLogger('cybs-error', 'cybs')->info(json_encode($exception));
                }
                // dd($afsResult);
            }
            DB::beginTransaction();
            // 订单地址添加
            CustomerOrderAddressRepository::transformOrderAddress($orderId, $addressId);
            OrdersRepository::orderUpdate($orderId, ['pay_type' => $payType]);
            // 添加支付信息
            if ($orderData['paymentData']) {
                CustomerOrderPaymentsRepository::deleteByOrderId($orderId);
                CustomerOrderPaymentsRepository::addLog($orderData['paymentData']);
            }
            $finalAmount = round($orderData['finalAmount'] * 100, 0);
            $result = [];
            $result['orderId'] = $orderId;
            $result['orderType'] = 'order';
            switch ($payType) {
                case 2: // paypal
                    $payPalResult = self::paypalPay($orderData['finalAmount'], $orderInfo->order_id,
                        $orderInfo->from_type, $orderInfo->currency_code);
                    if (!$payPalResult) {
                        return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later');
                    }
                    $payId = $payPalResult->getId();
                    $approvalUrl = $payPalResult->getApprovalLink();
                    CustomerOrderPaymentsRepository::update(['order_id' => $orderId, 'payment' => $payType],
                        ['pay_id' => $payId]);
                    $result['payUrl'] = $approvalUrl;
                    $result['paypal_token'] = parse_query(parse_url($approvalUrl)['query'])['token'] ?? '';
                    break;
                case 3: // stripe
                    $stripeResult = StripeService::pay($finalAmount, $source, $orderInfo->currency_code);
                    if (!$stripeResult) {
                        return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later');
                    }
                    if ($stripeResult->status == 'succeeded') {
                        CustomerOrderPaymentsRepository::update(['order_id' => $orderId, 'payment' => $payType],
                            ['pay_id' => $stripeResult->id]);
                        $userId = $orderInfo->customer_id;
                        $userInfo = UsersService::getUserInfo($userId);
                        if (empty($request->input('source', '')) && $request->input('is_default') == 'true') {
                            $cardData['user_id'] = UsersService::getUserId();
                            $cardData['card_id'] = $cardInfo->id;
                            $cardData['brand'] = $cardInfo->brand;
                            $cardData['card_number'] = $request->input('number');
                            $cardData['created_at'] = date('Y-m-d H:i:s');
                            $cardId = UserCardsRepository::insert($cardData);
                            $cardAddressData = [
                                'card_id' => $cardId,
                                'user_id' => $userId
                            ];
                            $firstname = $request->input('firstname', '');
                            $cardAddressData['created_at'] = date('Y-m-d H:i:s');
                            if ($firstname) {
                                $lastname = $request->input('lastname', '');
                                $phone = $request->input('iphone', '');
                                $country = $request->input('country', '');
                                $state = $request->input('state', '');
                                $city = $request->input('city', '');
                                $postCode = $request->input('postalcode', '');
                                $street = $request->input('street', '');
                                $suburb = $request->input('suburb', '');
                                $email = $request->input('email', '');
                                $cardAddressData['firstname'] = $firstname;
                                $cardAddressData['lastname'] = $lastname;
                                $cardAddressData['country'] = $country;
                                $cardAddressData['state'] = $state;
                                $cardAddressData['city'] = $city;
                                $cardAddressData['street_address'] = $street;
                                $cardAddressData['suburb'] = $suburb;
                                $cardAddressData['postcode'] = $postCode;
                                $cardAddressData['phone'] = $phone;
                                $cardAddressData['email'] = $email;
                            } else {
                                $addressId = $request->input('address_id');
                                $address = AddressRepository::getAddressInfo($addressId);
                                $userInfo = UsersService::getUserInfo($userId);
                                $cardAddressData['firstname'] = $address->firstname;
                                $cardAddressData['lastname'] = $address->lastname;
                                $cardAddressData['country'] = $address->country;
                                $cardAddressData['state'] = $address->state;
                                $cardAddressData['city'] = $address->city;
                                $cardAddressData['street_address'] = $address->street_address;
                                $cardAddressData['suburb'] = $address->suburb;
                                $cardAddressData['postcode'] = $address->postcode;
                                $cardAddressData['phone'] = $address->phone;
                                $cardAddressData['email'] = $userInfo->email;
                            }
                            UserCardsAddressRepository::insert($cardAddressData);
                        }
                        OrdersRepository::orderUpdate($orderId, ['status' => 3, 'pay_at' => date('Y-m-d H:i:s')]);
                        if ($orderInfo->integral) {
                            $integralLog = [
                                'notes_id'     => account_sn($userId),
                                'integral'     => $orderInfo->integral,
                                'user_id'      => $userId,
                                'order_id'     => $orderInfo->order_id,
                                'created_at'   => date('Y-m-d H:i:s'),
                                'operate_type' => 2
                            ];
                            CustomerIntegralLogRepository::addLog($integralLog);
                        }
                        // 添加交易流水
                        if ($orderData['financeData']) {
                            CustomerFinanceLogRepository::deleteByOrderId($orderId);
                            CustomerFinanceLogRepository::addLog($orderData['financeData']);
                        }
                        if (isset($orderData['financeData']['amount']) && $orderData['financeData']['amount']) {
                            UserRepository::subAmountMoney($userId, $orderData['financeData']['amount']);
                        }
                        $require = self::getRequirements($orderId);
                        RequirementsRepository::addRequirement($require);
                        if ($userInfo->phone) {
                            // 生成待入账返现余额
                            CustomerIncomeService::addWaitAccount($orderId);
                            self::userCouponApply($userId, $orderInfo->order_id);
                        }
                        $result['price'] = round($orderInfo->final_price + $orderInfo->fare, $currency->digit);
                    } else {
                        return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later');
                    }
                    break;
            }
            DB::commit();
            return ApiResponse::success($result);
        } catch (\Exception $exception) {
            CLogger::getLogger('order_payment', 'orders')->info($exception->getMessage());
            // dd($exception);
            ding('订单' . $orderId . '支付失败-' . $exception->getMessage());
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later');
        }
    }

    /**
     * @function 订单paypal支付
     * @param $request
     * @return mixed
     */
    public static function orderPayPal($request)
    {
        $orderId = $request->input('order_id');
        $addressId = $request->input('address_id');
        $balance = $request->input('balance', false) == 'true';
        $payType = 2; //paypal支付
        $orderInfo = OrdersRepository::getOrderInfoByOrderId($orderId);
        $currency = Currency::where(['currency_code' => $orderInfo->currency_code])->first();
        $finalAmount = round($orderInfo->final_price + $orderInfo->fare, $currency->digit);
        $orderData = self::payTransfer($orderInfo, $finalAmount, $payType, $balance);
        try {
            DB::beginTransaction();
            // 订单地址添加
            CustomerOrderAddressRepository::transformOrderAddress($orderId, $addressId);
            OrdersRepository::orderUpdate($orderId, ['pay_type' => $payType]);
            // 添加支付信息
            if ($orderData['paymentData']) {
                CustomerOrderPaymentsRepository::deleteByOrderId($orderId);
                CustomerOrderPaymentsRepository::addLog($orderData['paymentData']);
            }
            $finalAmount = $orderData['finalAmount'];
            $result = [];
            $result['orderId'] = $orderId;
            $result['currency_code'] = $currency->currency_code;
            $result['currency_symbol'] = $currency->symbol;
            $result['price'] = $finalAmount;
            DB::commit();
            return ApiResponse::success($result);
        } catch (\Exception $exception) {
            CLogger::getLogger('order_payment', 'orders')->info($exception->getMessage());
            // dd($exception);
            ding('订单' . $orderId . '支付失败-' . $exception->getMessage());
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later');
        }
    }

    /**
     * @function 购物车支付处理
     * @param $request
     * @return mixed
     */
    public static function cartPay($request)
    {
        $userId = UsersService::getUserId();
        $userInfo = UsersService::getUserInfo($userId);
        $addressId = $request->input('address_id');
        $balance = $request->input('balance', false) == 'true';
        $payType = $request->input('pay_type'); //支付方式
        $type = $request->input('type', 1);//订单来源
        $couponId = $request->input('coupon_id', 0);//优惠券ID
        $integral = false;//是否使用积分
        $localDate = $request->input('date', '');
        $currency_code = isset($request['currency_code']) ? $request['currency_code'] : 'USD';
        $currency = CurrencyRepository::getByCurrencyCode($currency_code);
        $source = '';
        $cartInfo = CartsService::getCartInfo($userId);
        //生成订单，清空购物车
        $orderInfo = OrdersService::createOrder($cartInfo, $type, $couponId, $integral, $localDate, $currency,
            $addressId);
        if (!$orderInfo) {
            return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later');
        }
        $orderInfo = json_decode(json_encode($orderInfo));
        $finalAmount = round($orderInfo->final_price + $orderInfo->fare, $currency->digit);
        $orderData = self::payTransfer($orderInfo, $finalAmount, $payType, $balance);
        $orderId = $orderInfo->order_id;
        $errData = [
            'orderId' => $orderId
        ];
        try {
            if ($orderData['finalAmount'] <= 0.6 && $orderData['payType'] == 3) {
                return ApiResponse::failure(g_API_ERROR, 'Please change the Payment Method', $errData);
            }
            if ($payType == 3) {
                $source = self::getStripePaySource($request);
                if (!$source) {
                    return ApiResponse::failure(g_API_ERROR, 'Card information error', $errData);
                }
                if (is_array($source)) {
                    $cardInfo = $source['card'];
                    $source = $source['token'];
                }
                $addressInfo = AddressRepository::getAddressInfo($addressId);
                try {
                    $afsResult = self::getCyberSourceResult($orderId, $request, $finalAmount, $addressInfo, $currency,
                        $cardInfo);
                    if (is_production() && $afsResult['cyberSource']->afsReply->reasonCode == 100 && strtoupper($afsResult['cyberSource']->decision) == 'REJECT') {
                        return ApiResponse::failure(g_API_ERROR,
                            'Risk control tips: There was en error completing your payment, please change your payment method and try again');
                    }
                    CLogger::getLogger('cybs-success', 'cybs')->info(json_encode($afsResult));
                } catch (\Exception $exception) {
                    CLogger::getLogger('cybs-error', 'cybs')->info(json_encode($exception));
                }
                // dd($afsResult);
            }
            DB::beginTransaction();
            OrdersRepository::orderUpdate($orderId, ['pay_type' => $payType]);
            // 添加支付信息
            if ($orderData['paymentData']) {
                CustomerOrderPaymentsRepository::deleteByOrderId($orderId);
                CustomerOrderPaymentsRepository::addLog($orderData['paymentData']);
            }
            $finalAmount = round($orderData['finalAmount'] * 100, 0);
            $result = [];
            $result['orderId'] = $orderId;
            $result['orderType'] = 'order';
            switch ($payType) {
                case 2: // paypal
                    $payPalResult = self::paypalPay($orderData['finalAmount'], $orderInfo->order_id,
                        $orderInfo->from_type, $orderInfo->currency_code);
                    if (!$payPalResult) {
                        return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later', $errData);
                    }
                    $payId = $payPalResult->getId();
                    $approvalUrl = $payPalResult->getApprovalLink();
                    CustomerOrderPaymentsRepository::update(['order_id' => $orderId, 'payment' => $payType],
                        ['pay_id' => $payId]);
                    $result['payUrl'] = $approvalUrl;
                    $result['paypal_token'] = parse_query(parse_url($approvalUrl)['query'])['token'] ?? '';
                    break;
                case 3: // stripe
                    $stripeResult = StripeService::pay($finalAmount, $source, $orderInfo->currency_code);
                    if (!$stripeResult) {
                        return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later', $errData);
                    }
                    if ($stripeResult->status == 'succeeded') {
                        CustomerOrderPaymentsRepository::update(['order_id' => $orderId, 'payment' => $payType],
                            ['pay_id' => $stripeResult->id]);
                        $userId = $orderInfo->customer_id;
                        if (empty($request->input('source', '')) && $request->input('is_default') == 'true') {
                            $cardData['user_id'] = UsersService::getUserId();
                            $cardData['card_id'] = $cardInfo->id;
                            $cardData['brand'] = $cardInfo->brand;
                            $cardData['card_number'] = $request->input('number');
                            $cardData['created_at'] = date('Y-m-d H:i:s');
                            $cardId = UserCardsRepository::insert($cardData);
                            $cardAddressData = [
                                'card_id' => $cardId,
                                'user_id' => $userId
                            ];
                            $firstname = $request->input('firstname', '');
                            $cardAddressData['created_at'] = date('Y-m-d H:i:s');
                            if ($firstname) {
                                $lastname = $request->input('lastname', '');
                                $phone = $request->input('iphone', '');
                                $country = $request->input('country', '');
                                $state = $request->input('state', '');
                                $city = $request->input('city', '');
                                $postCode = $request->input('postalcode', '');
                                $street = $request->input('street', '');
                                $suburb = $request->input('suburb', '');
                                $email = $request->input('email', '');
                                $cardAddressData['firstname'] = $firstname;
                                $cardAddressData['lastname'] = $lastname;
                                $cardAddressData['country'] = $country;
                                $cardAddressData['state'] = $state;
                                $cardAddressData['city'] = $city;
                                $cardAddressData['street_address'] = $street;
                                $cardAddressData['suburb'] = $suburb;
                                $cardAddressData['postcode'] = $postCode;
                                $cardAddressData['phone'] = $phone;
                                $cardAddressData['email'] = $email;
                            } else {
                                $addressId = $request->input('address_id');
                                $address = AddressRepository::getAddressInfo($addressId);
                                $userInfo = UsersService::getUserInfo($userId);
                                $cardAddressData['firstname'] = $address->firstname;
                                $cardAddressData['lastname'] = $address->lastname;
                                $cardAddressData['country'] = $address->country;
                                $cardAddressData['state'] = $address->state;
                                $cardAddressData['city'] = $address->city;
                                $cardAddressData['street_address'] = $address->street_address;
                                $cardAddressData['suburb'] = $address->suburb;
                                $cardAddressData['postcode'] = $address->postcode;
                                $cardAddressData['phone'] = $address->phone;
                                $cardAddressData['email'] = $userInfo->email;
                            }
                            UserCardsAddressRepository::insert($cardAddressData);
                        }
                        OrdersRepository::orderUpdate($orderId, ['status' => 3, 'pay_at' => date('Y-m-d H:i:s')]);
                        if ($orderInfo->integral) {
                            $integralLog = [
                                'notes_id'     => account_sn($userId),
                                'integral'     => $orderInfo->integral,
                                'user_id'      => $userId,
                                'order_id'     => $orderInfo->order_id,
                                'created_at'   => date('Y-m-d H:i:s'),
                                'operate_type' => 2
                            ];
                            CustomerIntegralLogRepository::addLog($integralLog);
                        }
                        // 添加交易流水
                        if ($orderData['financeData']) {
                            CustomerFinanceLogRepository::deleteByOrderId($orderId);
                            CustomerFinanceLogRepository::addLog($orderData['financeData']);
                        }
                        if (isset($orderData['financeData']['amount']) && $orderData['financeData']['amount']) {
                            UserRepository::subAmountMoney($userId, $orderData['financeData']['amount']);
                        }
                        $require = self::getRequirements($orderId);
                        RequirementsRepository::addRequirement($require);
                        if ($userInfo->phone) {
                            // 生成待入账返现余额
                            CustomerIncomeService::addWaitAccount($orderId);
                            self::userCouponApply($userId, $orderInfo->order_id);
                        }
                        $result['price'] = round($orderInfo->final_price + $orderInfo->fare, $currency->digit);
                    } else {
                        return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later', $errData);
                    }
                    break;
            }
            DB::commit();
            return ApiResponse::success($result);
        } catch (\Exception $exception) {
            CLogger::getLogger('order_payment', 'orders')->info($exception->getMessage());
            // dd($exception);
            ding('订单' . $orderId . '支付失败-' . $exception->getMessage());
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later', $errData);
        }
    }

    /**
     * @function 购物车paypal支付处理
     * @param $request
     * @return mixed
     */
    public static function cartPayPal($request)
    {
        $userId = UsersService::getUserId();
        $addressId = $request->input('address_id');
        $balance = $request->input('balance', false) == 'true';
        $payType = 2; //paypal支付
        $type = $request->input('type', 1);//订单来源
        $couponId = $request->input('coupon_id', 0);//优惠券ID
        $integral = false;//是否使用积分
        $localDate = $request->input('date', '');
        $currency_code = isset($request['currency_code']) ? $request['currency_code'] : 'USD';
        $currency = Currency::where('currency_code', $currency_code)->first();
        $cartInfo = CartsService::getCartInfo($userId);
        //生成订单，清空购物车
        $orderInfo = OrdersService::createOrder($cartInfo, $type, $couponId, $integral, $localDate, $currency,
            $addressId);
        if (!$orderInfo) {
            return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later');
        }
        $orderInfo = json_decode(json_encode($orderInfo));
        $finalAmount = round($orderInfo->final_price + $orderInfo->fare, $currency->digit);
        $orderData = self::payTransfer($orderInfo, $finalAmount, $payType, $balance);
        $orderId = $orderInfo->order_id;
        $errData = [
            'orderId' => $orderId
        ];
        try {
            DB::beginTransaction();

            OrdersRepository::orderUpdate($orderId, ['pay_type' => $payType]);
            // 添加支付信息
            if ($orderData['paymentData']) {
                CustomerOrderPaymentsRepository::deleteByOrderId($orderId);
                CustomerOrderPaymentsRepository::addLog($orderData['paymentData']);
            }
            $finalAmount = $orderData['finalAmount'];
            $result = [];
            $result['orderId'] = $orderId;
            $result['currency_code'] = $currency->currency_code;
            $result['currency_symbol'] = $currency->symbol;
            $result['price'] = $finalAmount;
            DB::commit();
            return ApiResponse::success($result);
        } catch (\Exception $exception) {
            CLogger::getLogger('order_payment', 'orders')->info($exception->getMessage());
            // dd($exception);
            ding('订单' . $orderId . '支付失败-' . $exception->getMessage());
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Payment failed, please try again later', $errData);
        }
    }

    private static function payTransfer($orderInfo, $finalAmount, $payType, $balance = false)
    {
        $orderId = $orderInfo->order_id;
        $result = [];
        $result['payType'] = $payType;
        $result['balance'] = 0;
        $result['finalAmount'] = $finalAmount;
        $financeData = [];
        $paymentData = [];
        $userId = UsersService::getUserId();
        $currency = CurrencyRepository::getByCurrencyCode($orderInfo->currency_code);
        // 使用余额
        if ($balance) {
            $balance_rate = config('common.balance_rate');
            $userBalance = UsersService::getUserInfo($userId)->amount_money;
            if ($userBalance > 0) {
                if ($userBalance >= ($finalAmount * $balance_rate) / $currency->rate) {
                    $result['balance'] = round($finalAmount * $balance_rate / $currency->rate, 2);
                    $result['finalAmount'] = round($finalAmount * (1 - $balance_rate), $currency->digit);
                } else {
                    $result['balance'] = $userBalance;
                    $result['finalAmount'] = round($finalAmount - $userBalance * $currency->rate, $currency->digit);
                }
                $financeData['order_id'] = $orderId;
                $financeData['turnover_id'] = client_finance_sn($userId, 1);
                //这里以美元计算扣减的余额，所以保留两位小数
                $financeData['amount'] = round($result['balance'], 2);
                $financeData['currency_code'] = $currency->currency_code;
                $financeData['local_amount'] = round($result['balance'] * $currency->rate, $currency->digit);
                $financeData['order_amount'] = $currency->symbol . round(($orderInfo->final_price + $orderInfo->fare),
                        $currency->digit);
                $financeData['user_id'] = $userId;
                $financeData['created_at'] = date('Y-m-d H:i:s');
                $financeData['operate_type'] = 1;
                $balancePaymentTmp['order_id'] = $orderId;
                $balancePaymentTmp['payment'] = 1;
                $balancePaymentTmp['amount'] = $result['balance'];
                $balancePaymentTmp['created_at'] = date('Y-m-d H:i:s');
                $paymentData[] = $balancePaymentTmp;
            }
        }
        $paymentTmp['order_id'] = $orderId;
        $paymentTmp['amount'] = $result['finalAmount'];
        $paymentTmp['payment'] = $payType;
        $paymentTmp['created_at'] = date('Y-m-d H:i:s');
        $paymentData[] = $paymentTmp;
        $result['financeData'] = $financeData;
        $result['paymentData'] = $paymentData;
        return $result;
    }

    private static function getStripePaySource($request)
    {
        $source = $request->input('source', '');
        if ($source) {
            $userId = UsersService::getUserId();
            $cardInfo = UserCardsRepository::getCardInfoByCardToken($userId, $source);
            $number = $cardInfo->card_number;
        } else {
            $number = $request->input('number', '');
        }
        $exp = $request->input('exp', '');
        $expArr = explode('/', $exp);
        $exp_year = $expArr[1];
        $exp_month = $expArr[0];
        $cvc = $request->input('cvc', '');
        return StripeService::createToken($number, $exp_month, $exp_year, $cvc);
    }

    private static function paypalPay($amount, $orderId, $type, $currency = 'USD')
    {
        $payment = PaypalService::pay($amount, $orderId, $type, $currency);
        if ($payment) {
            if ($payment->getState() == 'failed') {
                CLogger::getLogger('paypal-error', 'pay')->info(json_encode($payment));
                return false;
            }
            return $payment;
        }
        return $payment;
    }

    public static function sign($orderInfo)
    {
        $userId = $orderInfo->customer_id;
        try {
            DB::beginTransaction();
            // 修改订单状态
            OrdersRepository::orderUpdate($orderInfo->order_id, ['status' => 5, 'sign_at' => date('Y-m-d H:i:s')]);
            if ($waitAccounts = CustomerIncomeRepository::getWaitAccountByOrderId($orderInfo->order_id)) {
                foreach ($waitAccounts as $waitAccount) {
                    // 状态变更
                    $waitAccount->status = 2;
                    $waitAccount->account_at = Carbon::now()->toDateTimeString();
                    $waitAccount->save();
                    // 增加余额及累计收益
                    UserRepository::addIncome($waitAccount->income_user_id, $waitAccount->amount);
                    // 增加余额明细
                    CustomerFinanceLogRepository::deleteByOrderId($orderInfo->order_id);
                    $type = CustomerFinanceLogRepository::$incomeFinance[$waitAccount->type];
                    $financeData = [
                        'turnover_id'   => client_finance_sn($userId, $type),
                        'order_id'      => $orderInfo->order_id,
                        'amount'        => $waitAccount->amount,
                        'currency_code' => 'USD',
                        'user_id'       => $waitAccount->income_user_id,
                        'from_user_id'  => $waitAccount->from_user_id,
                        'order_amount'  => $waitAccount->order_amount,
                        'operate_type'  => $type,
                        'created_at'    => Carbon::now()->toDateTimeString(),
                    ];
                    CustomerFinanceLogRepository::addLog($financeData);
                }
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            ding('订单' . $orderInfo->order_id . '签收失败-' . $exception->getMessage());
            CLogger::getLogger('order-sign', 'orders')->info($exception->getMessage());
            return false;
        }
    }

    public static function paypalExecute($userId, $paymentId, $payerId)
    {
        $paymentInfo = CustomerOrderPaymentsRepository::getOrderPayment($paymentId);
        if (!$paymentInfo) {
            return ApiResponse::failure(g_API_ERROR, 'Payment Id dose not exists');
        }
        $orderInfo = OrdersRepository::getOrderInfoByOrderId($paymentInfo->order_id);
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->status != 1) {
            return ApiResponse::failure(g_API_ERROR, 'Payment Id error');
        }
        $orderBalancePayment = CustomerOrderPaymentsRepository::getOrderBalancePayment($orderInfo->order_id);
        $userInfo = UsersService::getUserInfo($userId);
        if ($orderBalancePayment) {
            $balance = $userInfo->amount_money;
            if ($balance < $orderBalancePayment->amount) {
                return ApiResponse::failure(g_API_ERROR, 'Order pay failed, try again later');
            }
        }
        $result = PaypalService::execute($paymentId, $payerId);
        if ($result || env('PAYPAL_ENV', '') == 'test') {
            if ($result->getState() == 'failed' && env('PAYPAL_ENV', '') != 'test') {
                CLogger::getLogger('paypal-error', 'pay')->info($result->getFailureReason());
                return ApiResponse::failure(g_API_ERROR, 'Order pay failed, try again later');
            }
            if ($result->getState() == 'approved' || env('PAYPAL_ENV', '') == 'test') {
                try {
                    DB::beginTransaction();
                    OrdersRepository::orderUpdate($orderInfo->order_id,
                        ['status' => 3, 'pay_at' => date('Y-m-d H:i:s')]);
                    $require = self::getRequirements($orderInfo->order_id);
                    RequirementsService::addRequirement($require);
                    if ($orderInfo->integral) {
                        $integralLog = [
                            'notes_id'     => account_sn($userId),
                            'integral'     => $orderInfo->integral,
                            'user_id'      => $userId,
                            'order_id'     => $orderInfo->order_id,
                            'created_at'   => date('Y-m-d H:i:s'),
                            'operate_type' => 2
                        ];
                        CustomerIntegralLogRepository::addLog($integralLog);
                    }
                    if ($orderBalancePayment) {
                        $financeLog = [
                            'turnover_id'  => tran_sn($userId, 1),
                            'order_id'     => $orderInfo->order_id,
                            'amount'       => $orderBalancePayment->amount,
                            'user_id'      => $userId,
                            'created_at'   => date('Y-m-d H:i:s'),
                            'operate_type' => 1
                        ];
                        CustomerFinanceLogRepository::addLog($financeLog);
                        UserRepository::subAmountMoney($userId, $orderBalancePayment->amount);
                    }
                    if ($userInfo->phone) {
                        self::userCouponApply($userId, $orderInfo->order_id);
                        // 生成待入账返现余额
                        CustomerIncomeService::addWaitAccount($orderInfo->order_id);
                    }
                    DB::commit();
                    $currency = CurrencyRepository::getByCurrencyCode($orderInfo->currency_code);
                    $result = [
                        'order_id'        => $orderInfo->order_id,
                        'price'           => round($orderInfo->final_price + $orderInfo->fare, $currency->digit),
                        'currency_symbol' => $currency->symbol
                    ];
                    return ApiResponse::success($result);
                } catch (\Exception $exception) {
                    DB::rollBack();
                    ding('订单' . $paymentInfo->order_id . '-paypal支付成功,数据写入失败-' . $exception->getMessage());
                    CLogger::getLogger('order-pay', 'payment')->info("paypal支付成功，数据写入失败" . $exception->getMessage());
                    return ApiResponse::failure(g_API_ERROR, 'Order pay failed');
                }
            }
        }
        return ApiResponse::failure(g_API_ERROR, 'Order pay failed, try again later');
    }

    /**
     * @function paypal sdk支付二次验证
     * @param $userId
     * @param $paymentId
     * @param $orderInfo
     * @return mixed
     */
    public static function paypalExec($userId, $paymentId, $orderInfo)
    {
        // 余额支付金额
        $orderBalancePayment = CustomerOrderPaymentsRepository::getOrderBalancePayment($orderInfo->order_id);
        $userInfo = UsersService::getUserInfo($userId);
        if ($orderBalancePayment) {
            $balance = $userInfo->amount_money;
            if ($balance < $orderBalancePayment->amount) {
                return ApiResponse::failure(g_API_ERROR, 'Order pay failed, try again later');
            }
        }
        // paypal支付金额
        $orderPaypalPayment = CustomerOrderPaymentsRepository::getOrderPaypalPayment($orderInfo->order_id);
        if (!$orderPaypalPayment) {
            return ApiResponse::failure(g_API_ERROR, 'Order pay failed, try again later');
        }
        // 获取paypal返回的支付信息
        $result = PaypalService::validateExecInfo($paymentId, $orderPaypalPayment);
        if ($result['status'] == 200 || env('PAYPAL_ENV', '') == 'test') {
            try {
                DB::beginTransaction();
                OrdersRepository::orderUpdate($orderInfo->order_id, ['status' => 3, 'pay_at' => date('Y-m-d H:i:s')]);
                $require = self::getRequirements($orderInfo->order_id);
                RequirementsService::addRequirement($require);
                if ($orderInfo->integral) {
                    $integralLog = [
                        'notes_id'     => account_sn($userId),
                        'integral'     => $orderInfo->integral,
                        'user_id'      => $userId,
                        'order_id'     => $orderInfo->order_id,
                        'created_at'   => date('Y-m-d H:i:s'),
                        'operate_type' => 2
                    ];
                    CustomerIntegralLogRepository::addLog($integralLog);
                }
                if ($orderBalancePayment) {
                    $financeLog = [
                        'turnover_id'  => tran_sn($userId, 1),
                        'order_id'     => $orderInfo->order_id,
                        'amount'       => $orderBalancePayment->amount,
                        'user_id'      => $userId,
                        'created_at'   => date('Y-m-d H:i:s'),
                        'operate_type' => 1
                    ];
                    CustomerFinanceLogRepository::addLog($financeLog);
                    UserRepository::subAmountMoney($userId, $orderBalancePayment->amount);
                }
                if ($userInfo->phone) {
                    self::userCouponApply($userId, $orderInfo->order_id);
                    // 生成待入账返现余额
                    CustomerIncomeService::addWaitAccount($orderInfo->order_id);
                }
                DB::commit();
                $currency = Currency::where('currency_code', $orderInfo->currency_code)->first();
                $result = [
                    'order_id'        => $orderInfo->order_id,
                    'price'           => round($orderInfo->final_price + $orderInfo->fare, $currency->digit),
                    'currency_symbol' => $currency->symbol,
                    'usd_price'       => round(($orderInfo->final_price + $orderInfo->fare) / $currency->rate, 2)
                ];
                return ApiResponse::success($result);
            } catch (\Exception $exception) {
                DB::rollBack();
                ding('订单' . $orderInfo->order_id . '-paypal支付成功,数据写入失败-' . $exception->getMessage());
                CLogger::getLogger('order-pay', 'payment')->info("paypal支付成功，数据写入失败" . $exception->getMessage());
                return ApiResponse::failure(g_API_ERROR, 'Order pay failed');
            }
        }
        return ApiResponse::failure(g_API_ERROR, 'Order pay failed, try again later');
    }

    public static function cyberSource(
        $userInfo,
        $orderInfo,
        $cards,
        $address,
        $amount,
        $addressInfo,
        $orderGoodsInfo,
        $oldOrders,
        $currency
    ) {
        return CyberSourceService::transact($userInfo, $orderInfo, $cards, $address, $amount, $addressInfo,
            $orderGoodsInfo, $oldOrders, $currency);
    }

    public static function getCyberSourceResult($orderId, $request, $amount, $addressInfo, $currency, $cardInfo)
    {
        $result = [];
        $source = $request->input('source', '');
        $userId = UsersService::getUserId();
        $userInfo = UsersService::getUserInfo($userId);
        if ($source) {
            $cards = UserCardsRepository::getCardInfoByCardToken($userId, $source);
            $address = UserCardsAddressRepository::getAddress($userId, $cards->id);
            $number = $cards->card_number;
        } else {
            $number = $request->input('number', '');
            $firstname = $request->input('firstname', '');
            if ($firstname) {
                $lastname = $request->input('lastname', '');
                $phone = $request->input('iphone', '');
                $country = $request->input('country', '');
                $state = $request->input('state', '');
                $city = $request->input('city', '');
                $postCode = $request->input('postalcode', '');
                $street = $request->input('street', '');
                $suburb = $request->input('suburb', '');
                $email = $request->input('email', '');
                $address = json_decode(json_encode([
                    'firstname'      => $firstname,
                    'lastname'       => $lastname,
                    'street_address' => $street,
                    'city'           => $city,
                    'state'          => $state,
                    'postcode'       => $postCode,
                    'country'        => $country,
                    'email'          => $email
                ]));
            } else {
                $addressId = $request->input('address_id');
                $address = AddressRepository::getAddressInfo($addressId);
                $address->email = $userInfo->email;
            }
            $result['address'] = $address;
        }
        $exp = $request->input('exp', '');
        $expArr = explode('/', $exp);
        $exp_year = $expArr[1];
        $exp_month = $expArr[0];
        $cards = json_decode(json_encode([
            'number' => $number,
            'expm'   => $exp_month,
            'expy'   => $exp_year,
            'brand'  => $cardInfo->brand
        ]));
        $orderInfo = OrdersRepository::getOrderInfoByOrderId($orderId);
        $orderGoodsInfo = OrdersRepository::getOrderGoodsInfoByOrderId($orderId);
        $oldOrders = OrdersRepository::isHasSuccessOrderInHalfYear($userId);
        $cyberResult = self::cyberSource($userInfo, $orderInfo, $cards, $address, $amount, $addressInfo,
            $orderGoodsInfo, $oldOrders, $currency);
        $result['cyberSource'] = $cyberResult;
        return $result;
    }

    private static function getRequirements($orderId)
    {
        $require = [];
        $orderGoods = OrderGoodsRepository::getOneGoodByOrderId($orderId)->toArray();
        foreach ($orderGoods as $orderGood) {
            $requireTmp = [
                'sku_id'     => $orderGood['sku_id'],
                'num'        => $orderGood['num'],
                'type'       => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $require[] = $requireTmp;
        }
        return $require;
    }

    private static function getReturn($orderId)
    {
        $require = [];
        $orderGoods = OrderGoodsRepository::getOneGoodByOrderId($orderId);
        foreach ($orderGoods as $orderGood) {
            $requireTmp = [
                'sku_id'     => $orderGood->sku_id,
                'num'        => $orderGood->num,
                'type'       => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $require[] = $requireTmp;
        }
        return $require;
    }

    /**
     * 活动优惠券领取
     */
    private static function userCouponApply($userId, $orderId)
    {
        $orderGoods = OrderGoodsRepository::getOneGoodByOrderId($orderId);
        $promotionIds = array_pluck($orderGoods, 'activity_id');
        $promotions = PromotionsRepository::getReturnPromotionByIds($promotionIds);
        if ($promotions->count()) {
            $couponIds = [];
            $couponData = [];
            foreach ($promotions as $promotion) {
                $rule = json_decode($promotion->rule, true);
                foreach (explode(',', $rule['ids']) as $item) {
                    $couponIds[] = $item;
                }
            }
            $coupons = CouponRepository::getCouponByIds($couponIds);
            foreach ($coupons as $coupon) {
                $couponTmp = [
                    'user_id'             => $userId,
                    'coupon_id'           => $coupon->id,
                    'code_receive_status' => 2,
                    'code_use_status'     => 1,
                    'code_received_at'    => date('Y-m-d H:i:s'),
                    'created_at'          => date('Y-m-d H:i:s'),
                    'updated_at'          => date('Y-m-d H:i:s')
                ];
                if ($coupon->use_type == 2) {
                    $couponTmp['code_used_start_date'] = $coupon->coupon_use_startdate;
                    $couponTmp['code_used_end_date'] = $coupon->coupon_use_enddate;
                } else {
                    $couponTmp['code_used_start_date'] = date('Y-m-d H:i:s');
                    $couponTmp['code_used_end_date'] = date('Y-m-d H:i:s', strtotime("{$coupon->use_days} day"));
                }
                $couponData[] = $couponTmp;
            }
            if ($couponData) {
                CouponRepository::couponCodeInsert($couponData);
            }
        }
    }

    /**
     * 订单取消
     * @param $orderInfo
     * @return bool
     */
    public static function cancel($orderInfo)
    {
        try {
            DB::beginTransaction();
            OrdersRepository::orderUpdate($orderInfo->order_id, ['status' => 6]);
            $orderGoods = OrderGoodsRepository::getOneGoodByOrderId($orderInfo->order_id);
            $orderGoodStock = [];
            $orderGoodSkuStock = [];
            foreach ($orderGoods as $orderGood) {
                // 恢复库存
                if (isset($orderGoodStock[$orderGood->good_id])) {
                    $orderGoodStock[$orderGood->good_id] += $orderGood->num;
                } else {
                    $orderGoodStock[$orderGood->good_id] = $orderGood->num;
                }
                if (isset($orderGoodSkuStock[$orderGood->sku_id])) {
                    $orderGoodSkuStock[$orderGood->sku_id] += $orderGood->num;
                } else {
                    $orderGoodSkuStock[$orderGood->sku_id] = $orderGood->num;
                }
                if ($orderGood->activity_id) {
                    PromotionsRepository::subProductBuyNum($orderGood->activity_id,
                        [$orderGood->good_id => $orderGood->num]);
                }
            }
            ProductsRepository::addProductStock($orderGoodStock);
            ProductsRepository::addProductSkuStock($orderGoodSkuStock);
            ProductsRepository::addAuditProductStock($orderGoodStock);
            ProductsRepository::addAuditProductSkuStock($orderGoodSkuStock);
            if ($orderInfo->code_id) {
                CouponRepository::couponCodeReset($orderInfo->code_id);
            }
            if ($orderInfo->integral > 0) {
                UserRepository::addIntegral($orderInfo->customer_id, $orderInfo->integral);
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            ding('订单' . $orderInfo . '取消失败-' . $exception->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public static function getUserOrdersCounts($userId)
    {
        $orders = OrdersRepository::getUserOrdersCounts($userId);
        return array_pluck($orders, 'orders', 'status');
    }

    /**
     * @function 计算促销商品价格
     * @param $promotion
     * @param $promotionsGoodTotalPrice
     * @param $promotionsGoodTotal
     * @param $promotionGoodsNumPrice
     * @return float|string
     */
    public static function calculationPromotionPrice(
        $promotion,
        $promotionsGoodTotalPrice,
        $promotionsGoodTotal,
        $promotionGoodsNumPrice
    ) {
        $discount_amount = 0.00;
        switch ($promotion->activity_type) {
            case 'reduce': //满减
                $reduceInfo = getReduce($promotionsGoodTotalPrice, $promotion);
                $reducePrice = isset($reduceInfo['reduce']) ? $reduceInfo['reduce'] : '0.00';//满减金额
                $isSatisfy = $reduceInfo['isSatisfy'];
                //活动优惠总金额
                $discount_amount += $reducePrice;
                break;
            case 'return': //满返
                $returnInfo = getReturn($promotionsGoodTotalPrice, $promotion);
                $isSatisfy = $returnInfo['isSatisfy'];

                break;
            case 'discount': //多件多折
                $discountInfo = getDiscount($promotionsGoodTotal, $promotionsGoodTotalPrice, $promotion);
                $discountPrice = isset($discountInfo['discount']) ? $discountInfo['discount'] : '0.00';//多件多折优惠金额
                $isSatisfy = $discountInfo['isSatisfy'];
                $discount_amount += $discountPrice;
                break;
            case 'wholesale': //X元n件
                $wholesaleInfo = getWholesale($promotionsGoodTotal, $promotionsGoodTotalPrice, $promotionGoodsNumPrice,
                    $promotion);
                $wholesalePrice = isset($wholesaleInfo['wholesale']) ? $wholesaleInfo['wholesale'] : '0.00';//X元n件优惠金额
                $isSatisfy = $wholesaleInfo['isSatisfy'];
                $discount_amount += $wholesalePrice;
                break;
            case 'give': //买n免1
                $giveInfo = getGive($promotionsGoodTotal, $promotionGoodsNumPrice, $promotion);
                $givePrice = isset($giveInfo['give']) ? $giveInfo['give'] : '0.00';//买n免1优惠金额
                $isSatisfy = $giveInfo['isSatisfy'];
                $discount_amount += $givePrice;
                break;
            case '': // 无类型促销
                break;
        }
        return $discount_amount;
    }

    /**
     * @function 购物车支付信息验证
     * @param $request
     * @return bool|mixed
     */
    public static function codPayValidator($request)
    {
        $currency_code = $request->input('currency_code', 'USD');
        $currency = CurrencyRepository::getByCurrencyCode($currency_code);
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $sku_id = $request->input('sku_id', 0);
        if (!$sku_id) {
            return ApiResponse::failure(g_API_ERROR, 'please select a product');
        }
        $skuInfo = ProductsSkuRepository::getSkuById($sku_id);
        if (!$skuInfo) {
            return ApiResponse::failure(g_API_ERROR, 'please select a product');
        }
        if ($skuInfo->good_stock < 1) {
            return ApiResponse::failure(g_API_ERROR, 'sale out');
        }
        return false;
    }


    /**
     * @function 购物车支付处理
     * @param $request
     * @return mixed
     */
    public static function codPay($request)
    {
        $currency_code = $request->input('currency_code', 'USD');
        $currency = CurrencyRepository::getByCurrencyCode($currency_code);
        if (!$currency) {
            return ApiResponse::failure(g_API_ERROR, "currency does not exist");
        }
        $skuInfo = ProductsSkuRepository::getSkuById($request->input('sku_id'));
        $next_good = ProductsRepository::getNextCodProductId($skuInfo->good_id);
        $type = $request->input('type', 1);//订单来源
        $order_id = order_sn('WWCOD', $type);
        $orderData = [
            'order_id'        => $order_id,
            'total_price'     => round($skuInfo->price * $currency->rate, $currency->digit),
            'pay_type'        => 1, //货到付款
            'currency_code'   => $currency_code,
            'from_type'       => $type,
            'status'          => 2, //待发货
            'user_local_time' => $request->input('date', ''),
            'created_at'      => Carbon::now()->toDateTimeString(),
        ];
        $attrs = ProductsRepository::getAttrAndValuesByIds(explode(',', $skuInfo->value_ids));
        $attrValues = [];
        foreach ($attrs as $attr) {
            $attrValues[$attr->attr_name] = $attr->value_name;
        }
        $attr_value = json_encode($attrValues);
        $orderGoodData = [
            'order_id'   => $order_id,
            'good_id'    => $skuInfo->good_id,
            'sku_id'     => $skuInfo->id,
            'value_ids'  => $skuInfo->value_ids,
            'attr_value' => $attr_value,
            'unit_price' => round($skuInfo->price * $currency->rate, $currency->digit),
            'num'        => 1,
            'created_at' => Carbon::now()->toDateTimeString(),
        ];
        $addressData = [
            'name'           => $request->input('name', ''),
            'country'        => $request->input('country', ''),
            'state'          => $request->input('state', ''),
            'city'           => $request->input('city', ''),
            'street_address' => $request->input('street', ''),
            'suburb'         => $request->input('suburb', ''),
            'postcode'       => $request->input('postalcode', ''),
            'phone'          => $request->input('phone', ''),
            'created_at'     => Carbon::now()->toDateTimeString(),
        ];
        //生成订单
        try {
            DB::beginTransaction();
            // 生成地址
            $address = CodOrderAddress::create($addressData);
            $order['address_id'] = $address->id;
            // 生成订单
            CodOrder::create($orderData);
            CodOrderGood::insert($orderGoodData);
            DB::commit();
            return ApiResponse::success(compact('order_id', 'next_good', 'currency_code'));
        } catch (\Exception $exception) {
            DB::rollBack();
            CLogger::getLogger('order_payment', 'orders')->info($exception->getMessage());
            ding('cod订单生成失败-' . $exception->getMessage());
            return ApiResponse::failure(g_API_ERROR, 'system error, please try again later', compact('next_good'));
        }
    }
}