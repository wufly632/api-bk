<?php

namespace App\Modules\Users\Repositories;

use App\Models\Currency;

class CurrencyRepository
{
    public static function getByCurrencyCode($currencyCode)
    {
        return Currency::where('currency_code', $currencyCode)->first();
    }
}