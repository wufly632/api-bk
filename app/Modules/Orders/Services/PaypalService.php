<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/28
 * Time: 下午5:10
 */

namespace App\Modules\Orders\Services;


use App\Assistants\CLogger;
use App\Services\ApiResponse;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;

class PaypalService
{
    public static function pay($amount, $orderId, $type, $currency = 'USD')
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        $redirectUrls = new RedirectUrls();
        if ($type == 2) {
            $successUrl = env('PAYPAL_H5_SUCCESS_URL', '');
            $cancelUrl = env('PAYPAL_H5_CANCEL_URL', '');
        } else {
            $successUrl = env('PAYPAL_PC_SUCCESS_URL', '');
            $cancelUrl = env('PAYPAL_PC_CANCEL_URL', '');
        }
        $redirectUrls->setReturnUrl($successUrl . "&orderId={$orderId}")->setCancelUrl($cancelUrl . "&orderId={$orderId}");
        $amountObj = new Amount();
        $amountObj->setCurrency($currency)->setTotal($amount);
        $transaction = new Transaction();
        $transaction->setAmount($amountObj)->setDescription('');
        $payment = new Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions([$transaction]);
        try {
            $apiContext = self::getApiContext();
            if (env('APP_ENV') == 'production') {
                $apiContext->setConfig(['mode' => 'LIVE']);
            }
            $payment->create($apiContext);
            return $payment;
        } catch (PayPalConnectionException $exception) {
            CLogger::getLogger('paypal-error', 'pay')->info($exception->getData());
            return false;
        }
    }

    public static function execute($paymentId, $payerId)
    {
        try {
            $apiContext = self::getApiContext();
            if (env('APP_ENV') == 'production') {
                $apiContext->setConfig(['mode' => 'LIVE']);
            }
            $payment = Payment::get($paymentId, $apiContext);
            $execution = new PaymentExecution();
            $execution->setPayerId($payerId);
            $result = $payment->execute($execution, $apiContext);
            return $result;
        } catch (PayPalConnectionException $exception) {
            CLogger::getLogger('paypal-error', 'pay')->info($exception->getData());
            return false;
        }
    }

    /**
     * @function 查询paypal支付信息
     * @param $paymentId
     * @return bool|Payment
     */
    public static function getExecInfo($paymentId)
    {
        try {
            $apiContext = self::getApiContext();
            if (env('APP_ENV') == 'production') {
                $apiContext->setConfig(['mode' => 'LIVE']);
            }
            $payment = Payment::get($paymentId, $apiContext);
            return $payment;
        } catch (PayPalConnectionException $exception) {
            wwerror($paymentId . '-paypal支付信息查询失败.' . $exception->getMessage());
            return false;
        }
    }

    /**
     * @function paypal支付状态验证
     * @param $paymentId
     * @param $orderPaypalPayment
     * @return mixed
     */
    public static function validateExecInfo($paymentId, $orderPaypalPayment)
    {
        $execInfo = self::getExecInfo($paymentId);
        $execAmount = collect($execInfo->getTransactions())->map(function ($item) {
            return $item->getAmount()->getTotal();
        })->sum();
        if ($execInfo->state == 'approved') {
            // 验证订单号和金额
            if ($orderPaypalPayment->order_id && $orderPaypalPayment->amount == $execAmount) {
                return ApiResponse::success('success');
            }
        }
        return ApiResponse::failure(g_API_STATUS, 'pay failed');
    }

    private static function getApiContext()
    {
        return new ApiContext(new OAuthTokenCredential(env('PAYPAL_API_ID', ''), env('PAYPAL_API_SECRET', '')));
    }

}