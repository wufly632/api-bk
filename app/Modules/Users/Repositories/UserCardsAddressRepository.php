<?php
/**
 * Created by PhpStorm.
 * User: wmj
 * Date: 2018/5/9
 * Time: 17:06
 */

namespace App\Modules\Users\Repositories;

use App\Models\Customer\CardsAddress;
use Illuminate\Support\Facades\DB;

class UserCardsAddressRepository
{
    /**
     * 获取一个人的具体账单的地址
     * @param $userId
     * @param $cardId
     * @return mixed
     */
    public static function getAddress($userId, $cardId)
    {
        return CardsAddress::where('user_id', $userId)
            ->where('card_id', $cardId)
            ->first();
    }

    /**
     * 新建账单地址
     * @param $cardAddress
     * @return mixed
     */
    public static function insert($cardAddress)
    {
        return CardsAddress::create($cardAddress);
    }
}