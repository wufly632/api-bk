<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/19
 * Time: 下午9:22
 */

namespace App\Modules\Orders\Repositories;

use App\Models\Customer\FinanceLog;
use App\Modules\Users\Repositories\CustomerIncomeRepository;

class CustomerFinanceLogRepository
{
    // 交易流水类型
    const TYPE_CONSUMPTION = 1;
    const TYPE_SHOPPING_REWARD = 2;
    const TYPE_FANS_SHOPPING = 3;
    const TYPE_FANS_REWARD = 4;
    const TYPE_REGISTATION_REWARD = 5;
    const TYPE_INVITE_FANS = 6;

    static public $incomeFinance = [
        CustomerIncomeRepository::TYPE_SHOPPING_REWARD => self::TYPE_SHOPPING_REWARD,
        CustomerIncomeRepository::TYPE_FANS_SHOPPING => self::TYPE_FANS_SHOPPING,
        CustomerIncomeRepository::TYPE_FANS_REWARD => self::TYPE_FANS_REWARD,
        CustomerIncomeRepository::TYPE_INVITE_FANS => self::TYPE_INVITE_FANS
    ];

    public static function addLog($log)
    {
        return FinanceLog::create($log);
    }

    public static function deleteByOrderId($orderId)
    {
        return FinanceLog::where('order_id', $orderId)->delete();
    }

    public static function getList($userId)
    {
        return FinanceLog::where('user_id', $userId)->orderByDesc('id')->paginate(20);
    }
}