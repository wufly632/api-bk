<?php

namespace App\Modules\Home\Repositories;

use App\Models\Website\Icon;
use Carbon\Carbon;

class IconRepository
{
    public static function getAllIcon($field = ['*'])
    {
        $nowTime = Carbon::now()->toDateTimeString();
        return Icon::select(['src', 'category_id', 'title'])
            ->where('start_at', '<=', $nowTime)
            ->where('end_at', '>', $nowTime)
            ->orderByDesc('sort')
            ->limit(4)->get();
    }
}