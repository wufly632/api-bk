<?php
namespace App\Modules\Orders\Repositories;

use App\Models\Product\ProductSku;

class GoodSkuRepository
{
    public static function getSkuInfo($skuId)
    {
        return ProductSku::find($skuId);
    }
}