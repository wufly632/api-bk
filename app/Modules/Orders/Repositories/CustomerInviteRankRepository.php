<?php


namespace App\Modules\Orders\Repositories;

use App\Models\Customer\CustomerInviteRank;
use Illuminate\Support\Facades\Redis;

class CustomerInviteRankRepository
{

    /**
     * 查找或者更新
     * @param $userId
     */
    public static function firstOrCreate($userId)
    {
        $rank = CustomerInviteRank::firstOrCreate(['user_id' => $userId]);
        $rank->count++;
        $rank->save();
    }

    /**
     * 获取
     * @param $userId
     * @return mixed
     */
    public static function get($userId)
    {
        return CustomerInviteRank::where('user_id', $userId)->first();
    }
}