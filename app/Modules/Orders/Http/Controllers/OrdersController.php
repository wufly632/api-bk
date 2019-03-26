<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Exceptions\ParamErrorException;
use App\Models\Currency;
use App\Modules\Carts\Services\CartsService;
use App\Modules\Orders\Repositories\OrderGoodsRepository;
use App\Modules\Orders\Services\OrdersService;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Users\Services\SmsServices;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class OrdersController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $userId = UsersService::getUserId();
        $orderData = OrdersService::orderList($userId, $request);
        $orderData['status'] = $request->input('type', 0);
        return ApiResponse::success($orderData);
    }


    public function detail(Request $request)
    {
        $userId = UsersService::getUserId();
        $orderId = $request->input('order_id', '');
        if (!$orderId) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Order dose not exists');
        }
        $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->is_del) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Order dose not exists');
        }
        $result = OrdersService::orderDetail($orderInfo);
        return ApiResponse::success($result);
    }

    public function delete(Request $request)
    {
        $userId = UsersService::getUserId();
        $orderId = $request->input('order_id', '');
        if (!$orderId) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Order dose not exists');
        }
        $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->is_del) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Order dose not exists');
        }
        if (OrdersService::deleteOrder($orderInfo->id)) {
            return ApiResponse::success('');
        }
        return ApiResponse::failure(g_API_ERROR, 'failed to delete the order, try again later');
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
        if (!$carts->count()) {
            return ApiResponse::failure(g_API_ERROR, 'There are currently no items in your Shopping Cart');
        }
        $currency_code = isset($request->currency_code) ? $request->currency_code : 'USD';
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $type = $request->input('type', 0);
        $couponId = $request->input('coupon_id', 0);
        $integral = $request->input('integral', false) == 'true';
        $localDate = $request->input('date', null);
        if (!OrdersService::checkoutValidator($request, $carts)) {
            return ApiResponse::failure(g_API_ERROR, 'Information error, order failed');
        }
        if (!OrdersService::productStockValidator($carts)) {
            return ApiResponse::failure(g_API_ERROR, 'Insufficient inventory, order failed');
        }
        $address_id = $request->input('address_id', '');
        $result = OrdersService::createOrder($carts, $type, $couponId, $integral, $localDate, $currency, $address_id);
        if ($result) {
            return ApiResponse::success($result);
        }
        return ApiResponse::failure(g_API_ERROR, '下单失败');
    }

    /**
     * 确认支付
     * @param Request $request
     * @return mixed
     */
    public function payment(Request $request)
    {
        $result = [];
        $userId = UsersService::getUserId();
        $orderId = $request->input('order_id', '');
        $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->status != 1 || $orderInfo->is_del) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Order dose not exists');
        }
        $address = UsersService::getDefaultAddress($userId);
        $cards = UsersService::getCards($userId);
        $addressData = [];
        $cardData = [];
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
        $result['ordergoods'] = [];
        $orderGoodInfo = OrderGoodsRepository::getOrderGoodInfoByOrderIds([$orderInfo->order_id]);
        $skuIds = array_pluck($orderGoodInfo, 'sku_id');
        $skuInfos = ProductsRepository::getProductSkuInfoBySkus($skuIds);
        foreach ($orderGoodInfo as $orderGood) {
            $orderGoodTmp = [];
            if ($orderGood->order_id == $orderInfo->order_id) {
                $orderGoodTmp['id'] = $orderGood->good_id;
                $orderGoodTmp['price'] = $orderGood->unit_price;
                $orderGoodTmp['num'] = $orderGood->num;
                $orderGoodTmp['props'] = json_decode($orderGood->attr_value, true);
                foreach ($skuInfos as $skuInfo) {
                    if ($skuInfo->id == $orderGood->sku_id) {
                        $orderGoodTmp['name'] = $skuInfo->good_en_title;
                        $orderGoodTmp['img'] = $skuInfo->icon;
                        break;
                    }
                }
                $result['ordergoods'][] = $orderGoodTmp;
            }
        }
        $currencyInfo = Currency::where('currency_code', $orderInfo->currency_code)->first();
        $result['user_address'] = $addressData;
        $result['money'] = round(UsersService::getUserInfo($userId)->amount_money * $currencyInfo->rate,
            $currencyInfo->digit);
        $result['price'] = $orderInfo->final_price;
        $result['shipping'] = $orderInfo->fare;
        $result['order_time'] = $orderInfo->created_at;
        $paytime = (time() - strtotime($orderInfo->created_at));
        $orderTmp['paytime'] = $paytime > 30 * 60 ? 0 : (30 * 60 - $paytime);
        $result['cards'] = $cardData;
        if ($currencyInfo) {
            $result['currency_symbol'] = $currencyInfo->symbol;
        } else {
            $result['currency_symbol'] = '$';
        }
        $result['session_id'] = strtoupper(\Session::getId());
        $result['org_id'] = env('CYBS_ORG_ID', '');
        \Cache::set(CYBS_PAY_SESSION_ID . '_' . $userId, $result['session_id'], 30);
        $result['session_id'] = env('CYBS_MERCHANT_ID') . $result['session_id'];
        return ApiResponse::success($result);
    }

    /**
     * @function 购物车支付
     * @param Request $request
     * @return array|bool|mixed
     */
    public function cartPay(Request $request)
    {
        if ($result = OrdersService::cartPayValidator($request)) {
            return $result;
        }
        return OrdersService::cartPay($request);
    }

    /**
     * @function 购物车paypal支付
     * @param Request $request
     * @return array|bool|mixed
     */
    public function cartPayPal(Request $request)
    {
        request()->offsetSet('pay_type', 2);
        if ($result = OrdersService::cartPayValidator($request)) {
            return $result;
        }
        return OrdersService::cartPayPal($request);
    }

    /**
     * 支付(订单支付)
     * @param Request $request
     * @return array|bool|mixed
     */
    public function pay(Request $request)
    {
        if ($result = OrdersService::orderPayValidator($request)) {
            return $result;
        }
        return OrdersService::orderPay($request);
    }

    /**
     * 订单paypal支付
     * @param Request $request
     * @return array|bool|mixed
     */
    public function paypal(Request $request)
    {
        request()->offsetSet('pay_type', 2);
        if ($result = OrdersService::orderPayValidator($request)) {
            return $result;
        }
        return OrdersService::orderPayPal($request);
    }

    public function sign(Request $request)
    {
        $orderId = $request->input('order_id', '');
        if (!$orderId) {
            return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        }
        $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
        $userId = UsersService::getUserId();
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->is_del) {
            return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        }
        if ($orderInfo->status != 4) {
            return ApiResponse::failure(g_API_ERROR, 'The order can not be sign');
        }
        if (OrdersService::sign($orderInfo)) {
            return ApiResponse::success('');
        }
        return ApiResponse::failure(g_API_ERROR, 'Order sign failed try again later');
    }

    public function cancel(Request $request)
    {
        $userId = UsersService::getUserId();
        $orderId = $request->input('order_id', '');
        if (!$orderId) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Order dose not exists');
        }
        $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->is_del) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Order dose not exists');
        }
        if ($orderInfo->status != 1) {
            return ApiResponse::failure(g_API_ERROR, 'This order can not be canceled');
        }
        if (OrdersService::cancel($orderInfo)) {
            return ApiResponse::success('');
        }
        return ApiResponse::failure(g_API_ERROR, 'Order cancel failed try again later');
    }

    /**
     * @function cod模式支付
     * @param Request $request
     * @return mixed
     */
    public function codPay(Request $request)
    {
        if ($result = OrdersService::codPayValidator($request)) {
            return $result;
        }
        return OrdersService::codPay($request);
    }

    /**
     * @function 游客查询订单详情
     * @param Request $request
     * @return mixed
     */
    public function smdDetail(Request $request)
    {
        try {
            $this->smsValidate();
            $orderId = $request->input('order_id', '');
            $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
            if (!$orderInfo) {
                return ApiResponse::failure(g_API_ERROR, 'order does not exist');
            }
            $order_address = $orderInfo->address;
            if (isset($order_address->phone) && $order_address->phone != trim(request()->get('mobile'))) {
                return ApiResponse::failure(g_API_ERROR, 'mobile does not match');
            }
            $result = OrdersService::orderDetail($orderInfo);
            return ApiResponse::success($result);
        } catch (ParamErrorException $paramErrorException) {
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'request failed');
        }
    }

    /**
     * @function 验证短信验证码
     * @return bool
     * @throws ParamErrorException
     */
    private function smsValidate()
    {
        $calling_code = trim(request()->get('calling_code'));
        $mobile = trim(request()->get('mobile'));
        $verify_code = trim(request()->get('verify_code'));
        $mobileWithCode = $calling_code . $mobile;
        if (!$calling_code) {
            throw new ParamErrorException('Please provide Telephone Number');
        }

        if (!$mobile) {
            throw new ParamErrorException('Please provide Telephone Number');
        }
        if (!$verify_code) {
            throw new ParamErrorException('Please provide Verification Code');
        }
        if (!app(SmsServices::class)->checkCode($mobileWithCode, $verify_code, 'cat_order')) {
            throw new ParamErrorException('Verification Code error');
        }
        return true;
    }
}
