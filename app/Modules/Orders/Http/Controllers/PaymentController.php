<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Models\Currency;
use App\Modules\Orders\Services\OrdersService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    public function paypalSuccess(Request $request)
    {
        $userId = UsersService::getUserId();
        $paymentId = $request->input('payment_id', '');
        $payerId = $request->input('payer_id', '');
        if (!$paymentId) return ApiResponse::failure(g_API_ERROR, 'Payment Id can not be null');
        if (!$payerId) return ApiResponse::failure(g_API_ERROR, 'Payer Id can not be null');
        return OrdersService::paypalExecute($userId, $paymentId, $payerId);
    }

    public function paypalFailure(Request $request)
    {

    }

    public function getPaymentStatus(Request $request)
    {
        $orderId = $request->input('order_id', '');
        if (!$orderId) return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
        $userId = UsersService::getUserId();
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->is_del) return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        $data = [];
        $data['orderstatus'] = 'success';
        $currency = Currency::where('currency_code', $orderInfo->currency_code)->first();
        $data['price'] = round($orderInfo->final_price + $orderInfo->fare, $currency->digit);
        $data['usd_price'] = round($data['price'] / $currency->rate, 2);
        if ($orderInfo->status == 1) $data['orderstatus'] = 'failed';
        $data['order_id'] = $orderInfo->order_id;
        $data['currency_symbol'] = Currency::getSymbolByCode($orderInfo->currency_code);
        return ApiResponse::success($data);
    }

    /**
     * @function paypal sdk二次支付
     * @param Request $request
     */
    public function paypalExec(Request $request)
    {
        $userId = UsersService::getUserId();
        $orderId = $request->input('order_id', '');
        if (!$orderId) return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        $orderInfo = OrdersService::getOrderInfoByOrderId($orderId);
        if (!$orderInfo || $orderInfo->customer_id != $userId || $orderInfo->is_del) return ApiResponse::failure(g_API_ERROR, 'Order dose not exists');
        $paymentId = $request->input('payment_id', '');
        if (!$paymentId) return ApiResponse::failure(g_API_ERROR, 'Payment Id can not be null');
        return OrdersService::paypalExec($userId, $paymentId, $orderInfo);
    }
}
