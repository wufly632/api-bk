<?php
/**
 * Created by patpat.
 * User: zhijian.zhang
 * Date: 2018/5/8
 * Time: 11:33
 */

namespace App\Modules\Products\Repositories;

use App\Models\Product\ProductSku;
use App\Models\Product\SkuImage;

class ProductsSkuRepository
{
    public static function getSkuById($skuId)
    {
        $sku = ProductSku::find($skuId);
        return $sku;
    }

    public static function getSkus($productId)
    {
        return ProductSku::where('good_id', $productId)
            ->orderByDesc('sort')
            ->get();
    }

    public static function getSkuImages($productId)
    {
        return SkuImage::where('good_id', $productId)
            ->orderByDesc('sort')
            ->get();
    }

    public static function getSkuByIds($skuIds)
    {
        return ProductSku::whereIn('id', $skuIds)
            ->get()->toArray();
    }
}