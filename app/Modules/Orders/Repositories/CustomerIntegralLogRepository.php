<?php


namespace App\Modules\Orders\Repositories;

use App\Models\Customer\IntegralLog;

class CustomerIntegralLogRepository
{
    public static function addLog($log)
    {
        return IntegralLog::create($log);
    }

    public static function getList($userId)
    {
        return IntegralLog::where('user_id', $userId)->orderByDesc('created_at')->paginate(20);
    }
}