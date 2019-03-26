<?php
/**
 * Created by api.
 * User: Bruce.He
 * Date: 2018/4/27
 * Time: 22:28
 */

namespace App\Modules\Users\Services;

use App\Assistants\CLogger;
use App\Modules\Orders\Services\StripeService;
use App\Modules\Users\Repositories\UserCardsRepository;
use Illuminate\Support\Facades\DB;

class UserCardsService
{
    public static function getCardList($userId)
    {
        return UserCardsRepository::getCardList($userId);
    }

    public static function getUserCardById($cardId)
    {
        return UserCardsRepository::getCardById($cardId);
    }

    public static function cardDelete($cardId)
    {
        return UserCardsRepository::cardDelete($cardId);
    }

    public static function getUserCardAddress($cardId)
    {
        return UserCardsRepository::getUserCardAddress($cardId);
    }

    public static function setDefault($userId, $cardId)
    {
        return UserCardsRepository::setDefault($userId, $cardId);
    }

    public static function update($request)
    {
        $cardId = $request->input('card_id');
        $number = $request->input('number', '');
        $exp = $request->input('exp', '/');
        $expArr = explode('/', $exp);
        $exp_year = $expArr[1];
        $exp_month = $expArr[0];
        $cvc = $request->input('cvc', '');
        $is_default = $request->input('is_default', 0);
        if ($result = StripeService::createToken($number, $exp_month, $exp_year, $cvc)) {
            $cardData = [
                'card_id' => $result['card']->id,
                'card_number' => $number,
                /* 'card_expm' => $exp_month,
                'card_expy' => $exp_year,
                'card_cvc' => $cvc,
                'is_default' => $is_default ? 1 : 0*/
            ];
        } else {
            return false;
        }
        if ($request->input('firstname')) {
            $addressData = [
                'firstname' => $request->input('firstname'),
                'lastname' => $request->input('lastname'),
                'phone' => $request->input('iphone'),
                'country' => $request->input('country'),
                'state' => $request->input('state'),
                'city' => $request->input('city'),
                'postcode' => $request->input('postalcode'),
                'street_address' => $request->input('street'),
                'suburb' => $request->input('suburb', ''),
                'email' => $request->input('email')
            ];
        }
        try {
            DB::beginTransaction();
            UserCardsRepository::update($cardId, $cardData);
            if (isset($addressData)) UserCardsRepository::addressUpdate($cardId, $addressData);
            if ($cardData['is_default']) {
                $userId = UsersService::getUserId();
                UserCardsRepository::setDefault($userId, $cardId);
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            CLogger::getLogger('cards-edit', 'cards')->info($exception->getMessage());
            return false;
        }
    }

    public static function insertCard($request)
    {
        $number = $request->input('number', '');
        $exp = $request->input('exp', '/');
        $expArr = explode('/', $exp);
        if (count($expArr) != 2) {
            return false;
        }
        $exp_year = $expArr[1];
        $exp_month = $expArr[0];
        $cvc = $request->input('cvc', '');
        $is_default = $request->input('is_default', 0);
        $userId = UsersService::getUserId();
        if ($result = StripeService::createToken($number, $exp_month, $exp_year, $cvc)) {
            $cardData = [
                'card_id' => $result['card']->id,
                'user_id' => $userId,
                'card_number' => $number,
                'card_expm' => $exp_month,
                'card_expy' => $exp_year,
                'card_cvc' => $cvc,
                'is_default' => $is_default ? 1 : 0
            ];
        } else {
            return false;
        }
        if ($request->input('firstname')) {
            $addressData = [
                'firstname' => $request->input('firstname'),
                'lastname' => $request->input('lastname'),
                'phone' => $request->input('iphone'),
                'country' => $request->input('country'),
                'state' => $request->input('state'),
                'city' => $request->input('city'),
                'postcode' => $request->input('postalcode'),
                'street_address' => $request->input('street'),
                'suburb' => $request->input('suburb', ''),
                'email' => $request->input('email')
            ];
        }
        try {
            DB::beginTransaction();
            $cardId = UserCardsRepository::insert($cardData);
            if (isset($addressData)) UserCardsRepository::addressInsert($cardId, $addressData);
            if ($cardData['is_default']) {
                UserCardsRepository::setDefault($userId, $cardId);
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            CLogger::getLogger('cards-edit', 'cards')->info($exception->getMessage());
            return false;
        }
    }
}