<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/28
 * Time: 下午5:11
 */

namespace App\Modules\Orders\Services;


use App\Assistants\CLogger;
use Stripe\Charge;
use Stripe\Error\Card;
use Stripe\Error\InvalidRequest;
use Stripe\Error\RateLimit;
use Stripe\Stripe;
use Stripe\Token;

class StripeService
{
    public static function pay($amount, $source, $currency = 'usd')
    {
        try {
            self::setApiKey();
            $result = Charge::create([
                'amount'      => $amount,
                'currency'    => $currency,
                'description' => 'Example charge',
                'source'      => $source
            ]);
            return $result;
        } catch (Card $exception) {
            CLogger::getLogger('stripe', 'pay')->info($exception->getMessage());
            return false;
        } catch (RateLimit $exception) {
            CLogger::getLogger('stripe', 'pay')->info($exception->getMessage());
            return false;
        } catch (InvalidRequest $exception) {
            CLogger::getLogger('stripe', 'pay')->info($exception->getMessage());
            return false;
        } catch (\Exception $exception) {
            CLogger::getLogger('stripe', 'pay')->info($exception->getMessage());
            return false;
        }

    }

    public static function createToken($number, $exp_month, $exp_year, $cvc)
    {
        self::setApiKey();
        try {
            $result = Token::create([
                'card' => [
                    'number'    => $number,
                    'exp_month' => $exp_month,
                    'exp_year'  => $exp_year,
                    'cvc'       => $cvc
                ]
            ]);
            return ['token' => $result->id, 'card' => $result->card];
        } catch (\Exception $exception) {
            CLogger::getLogger('stripe', 'pay')->info($exception->getMessage());
            return false;
        }
    }

    private static function setApiKey()
    {
        Stripe::setApiKey(env('STRIPE_API_SECRET_KEY', ''));
    }
}