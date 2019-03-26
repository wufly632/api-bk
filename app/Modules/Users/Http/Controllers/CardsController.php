<?php

namespace App\Modules\Users\Http\Controllers;

use App\Modules\Users\Services\UserCardsService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CardsController extends Controller
{
    public function index()
    {
        $result = [];
        $userId = UsersService::getUserId();
        if (!$userId) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'User not found');
        }
        $cards = UserCardsService::getCardList($userId);
        $cardResult = [];
        foreach ($cards->items() as $item) {
            $cardTmp = [
                'id'         => $item->id,
                'number'     => $item->hidden_card_number,
                'is_default' => $item->is_default ? true : false
            ];
            $cardResult[] = $cardTmp;
        }
        $result['cards'] = $cardResult;
        $result['total_page'] = $cards->lastPage();
        return ApiResponse::success($result);
    }

    public function info(Request $request)
    {
        $result = $this->cardValidator($request);
        if ($result) {
            return $result;
        }
        $cardId = $request->input('card_id', 0);
        $cardInfo = UserCardsService::getUserCardById($cardId);
        $cardAddress = UserCardsService::getUserCardAddress($cardId);
        $card = [
            'id'         => $cardInfo->id,
            'number'     => $cardInfo->hidden_card_number,
            'exp'        => "{$cardInfo->card_expm}/{$cardInfo->card_expy}",
            'cvc'        => $cardInfo->card_cvc,
            'is_default' => $cardInfo->is_default ? true : false
        ];
        $address = [
            'firstname'  => $cardAddress->firstname,
            'lastname'   => $cardAddress->lastname,
            'iphone'     => $cardAddress->phone,
            'country'    => $cardAddress->country,
            'state'      => $cardAddress->state,
            'city'       => $cardAddress->city,
            'postalcode' => $cardAddress->postcode,
            'street'     => $cardAddress->street_address,
            'suburb'     => $cardAddress->suburb,
            'email'      => $cardAddress->email
        ];
        return ApiResponse::success(['card' => $card, 'address' => $address]);
        // return ApiResponse::success('');
    }

    public function setDefault(Request $request)
    {
        /*$result = $this->cardValidator($request);
        if($result) return $result;
        $cardId = $request->input('card_id');
        $userId = UsersService::getUserId();
        if(UserCardsService::setDefault($userId, $cardId))
        {
            return $this->index();
        }
        return ApiResponse::failure(g_API_ERROR, '');*/
        return ApiResponse::success('');
    }

    public function delete(Request $request)
    {
        $result = $this->cardValidator($request);
        if ($result) {
            return $result;
        }
        $cardId = $request->input('card_id', 0);
        if (UserCardsService::cardDelete($cardId)) {
            return $this->index();
        }
        return ApiResponse::failure(g_API_ERROR, 'card delete failed');
    }

    public function edit(Request $request)
    {
        $result = $this->cardValidator($request);
        if ($result) {
            return $result;
        }
        if (UserCardsService::update($request)) {
            return ApiResponse::success();
        }
        return ApiResponse::failure(g_API_ERROR, 'Card edit failed');
        // return ApiResponse::success('');
    }

    public function add(Request $request)
    {
        /*if(UserCardsService::insertCard($request)){
            return ApiResponse::success();
        }
        return ApiResponse::failure(g_API_ERROR, 'Card add failed');*/
        return ApiResponse::success('');
    }

    public function cardValidator($request)
    {
        $cardId = $request->input('card_id', 0);
        if (!$cardId) {
            return ApiResponse::failure(g_API_ERROR, 'Card Id can not be null');
        }
        $cardInfo = UsersService::getUserCardById($cardId);
        $userId = UsersService::getUserId();
        if (!$cardInfo || $cardInfo->user_id != $userId) {
            return ApiResponse::failure(g_API_ERROR, 'Cards dose not exits');
        }
        return false;
    }

}
