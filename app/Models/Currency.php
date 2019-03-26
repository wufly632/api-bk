<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currency';

    public static function getSymbolByCode($code)
    {
        return self::where('currency_code',$code)->first()->symbol ?? '';
    }

    /**
     * @function cdn加速
     * @param $item
     * @return \Illuminate\Contracts\Routing\UrlGenerator|mixed|string
     */
    public function getNationalFlagAttribute($item)
    {
        return cdnUrl($item);
    }
}
