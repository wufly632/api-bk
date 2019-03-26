<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

class MobileCategory extends Model
{
    protected $table = 'website_mobile_categorys';

    /**
     * @function cdn加速
     * @param $item
     * @return \Illuminate\Contracts\Routing\UrlGenerator|mixed|string
     */
    public function getImageAttribute($item)
    {
        return cdnUrl($item);
    }
}