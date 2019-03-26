<?php

namespace App\Modules\Coupon\Repositories;

use App\Models\Coupon\Code;

class CodeRepository
{
    public static function batchCreate($data)
    {
        Code::insert($data);
    }
}