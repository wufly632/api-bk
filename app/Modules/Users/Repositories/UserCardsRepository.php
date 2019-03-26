<?php


namespace App\Modules\Users\Repositories;

use App\Models\Customer\Card;
use App\Models\Customer\CardsAddress;
use Illuminate\Support\Facades\DB;

class UserCardsRepository
{
    public static function getCardInfoByCardToken($userId, $id)
    {
        return Card::find($id);
    }

    public static function insert($card)
    {
        return Card::create($card)->id;
    }

    /**
     * 获取银行卡
     * @param $cardId
     * @return mixed
     */
    public static function getCardById($cardId)
    {
        return Card::find($cardId);
    }

    /**
     * @param $userId
     * @return mixed
     */
    public static function getCardList($userId)
    {
        return Card::paginate(10);
    }

    /**
     * 删除
     * @param $cardId
     * @return int
     */
    public static function cardDelete($cardId)
    {
        $card = Card::find($cardId);
        $card->is_del = 1;
        return $card->save();
    }

    public static function getUserCardAddress($cardId)
    {
        return CardsAddress::where('card_id', $cardId)
            ->first();
    }

    public static function setDefault($userId, $cardId)
    {
        Card::where('id', $cardId)
            ->update(['is_default' => 1]);
        Card::whereNotIn('id', [$cardId])
            ->update(['is_default' => 0]);
    }

    public static function update($cardId, $cardData)
    {
        return Card::where('id', $cardId)->update($cardData);
    }

    public static function addressUpdate($cardId, $addressData)
    {
        return CardsAddress::where('card_id', $cardId)
            ->update($addressData);
    }

    public static function addressInsert($cardId, $addressData)
    {
        $addressData['card_id'] = $cardId;
        return CardsAddress::create($addressData);
    }
}