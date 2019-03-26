<?php


namespace App\Modules\Orders\Repositories;

use App\Models\Product\SkuImage;


class GoodSkuImagesRepository
{
    public static function getSkuImage($skuId)
    {
        return SkuImage::where('sku_id', $skuId)->first();
    }
}