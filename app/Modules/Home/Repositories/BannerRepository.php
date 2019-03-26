<?php

namespace App\Modules\Home\Repositories;

use App\Models\Website\Banner;
use Carbon\Carbon;

class BannerRepository
{
    public static function getBanners($fields = ['*'], $type = 2)
    {
        $nowTime = Carbon::now()->toDateTimeString();
        return Banner::select($fields)
            ->where('start_at', '<=', $nowTime)
            ->where('end_at', '>', $nowTime)
            ->where('type', $type)//2移动设备
            ->where('currency_code', request()->input('currency_code', 'USD'))
            ->orderByDesc('sort')
            ->get();
    }
}